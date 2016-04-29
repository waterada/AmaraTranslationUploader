<?php

class AmaraTranslationUploader {
    public $URL_GET_AUTH;
    public $URL_POST_TO_AMARA;

    public function __construct() {
        $this->Socket = new AmaraTranslationUploaderSocket();
        $this->URL_GET_AUTH = "https://www.amara.org/api2/partners/teams/ted/tasks/";
        $this->URL_POST_TO_AMARA = "https://www.amara.org/api2/partners/videos/__VIDEO__/languages/__LANG__/subtitles/";
    }

    public function doPost() {
        $basic = json_decode($_POST['basic'], true);
        $translation = json_decode($_POST['translation'], true);

        try {
            // check
            $this->_checkParams($basic, $translation);
            $this->_checkAuth($basic);

            // make dfxp
            $dfxp = $this->_makeTranslationDfxp($translation['subtitles']);

            // post dfxp
            $resPost = $this->_postTranslationToAmara($basic, $dfxp, $translation);

            // return
            $this->Socket->returnJson(json_encode($resPost));
        } catch (AmaraTranslationUploaderException $e) {
            $this->Socket->returnJson($e->getJsonMessage());
        }
    }

    public function _checkParams($basic, $translation) {
        if (empty($basic['headers'])) {
            throw new AmaraTranslationUploaderException("Invalid parameters:headers");
        }
        if (empty($basic['videoId'])) {
            throw new AmaraTranslationUploaderException("Invalid parameters:videoId");
        }
        if (empty($basic['userName'])) {
            throw new AmaraTranslationUploaderException("Invalid parameters:userName");
        }
        if (empty($translation['subtitles'])) {
            throw new AmaraTranslationUploaderException("Invalid parameters:subtitles");
        }
    }

    public function _checkAuth($basic) {
        //タスクが自分のものか
        //https://www.amara.org/api2/partners/teams/ted/tasks/?video_id=****&open&limit=200&assignee=wataru_terada&language=ja
        $url = $this->URL_GET_AUTH;
        $url .= "?format=json"
            . "&video_id=" . $basic['videoId']
            . "&language=ja"
            . "&open"
            . "&assignee=" . $basic['userName']
            . "&limit=1";
        $hasTask = $this->Socket->fetch($url, array("method" => "get", "headers" => $basic['headers']));
        if (empty($hasTask['objects'])) {
            throw new AmaraTranslationUploaderException("You don't have this task, so you cannot export this translation to Amara.");
        }
        return $hasTask;
    }

    function _makeTranslationDfxp($subtitles) {
        $dfxp = array();

        //header
        $dfxp[] ='<tt xmlns="http://www.w3.org/ns/ttml" xmlns:tts="http://www.w3.org/ns/ttml#styling" xml:lang="">';
        $dfxp[] = '  <head>';
        $dfxp[] = '    <metadata xmlns:ttm="http://www.w3.org/ns/ttml#metadata">';
        $dfxp[] = '      <ttm:title/>';
        $dfxp[] = '      <ttm:description/>';
        $dfxp[] = '      <ttm:copyright/>';
        $dfxp[] = '    </metadata>';
        $dfxp[] = '    <styling xmlns:tts="http://www.w3.org/ns/ttml#styling">';
        $dfxp[] = '      <style xml:id="amara-style" tts:color="white" tts:fontFamily="proportionalSansSerif" tts:fontSize="18px" tts:textAlign="center"/>';
        $dfxp[] = '    </styling>';
        $dfxp[] = '    <layout xmlns:tts="http://www.w3.org/ns/ttml#styling">';
        $dfxp[] = '      <region xml:id="amara-subtitle-area" style="amara-style" tts:extent="560px 62px" tts:padding="5px 3px" tts:backgroundColor="black" tts:displayAlign="after"/>';
        $dfxp[] = '    </layout>';
        $dfxp[] = '  </head>';
        $dfxp[] = '  <body region="amara-subtitle-area">';
        $dfxp[] = '    <div>';

        //body
        $first = true;
        foreach ($subtitles as $s) {
            if ($first == false && !empty($s['paragraph'])) {
                $dfxp[] = '    </div>';
                $dfxp[] = '    <div>';
            }
            $s_start = preg_replace('/,/', '.', $s['start']);
            $s_end = preg_replace('/,/', '.', $s['end']);
            $s_text = htmlspecialchars($s['text'], ENT_COMPAT, 'UTF-8');
            $s_text = preg_replace('/\\n/', '<br/>', $s_text);
            $dfxp[] = '      <p begin="' . $s_start . '" end="' . $s_end . '">' . $s_text . '</p>';
            $first = false;
        }

        //footer
        $dfxp[] = '    </div>';
        $dfxp[] = '  </body>';
        $dfxp[] = '</tt>';

        return implode("\n", $dfxp);
    }

    function _postTranslationToAmara($basic, $dfxp, $translation) {
        // payload
        $payload = array(
            "sub_format"  => "dfxp",
            "subtitles"   => $dfxp,
            "title"       => $translation['title'],
            "description" => $translation['description'],
        );
        if (!empty($translation['speakerName']) && $translation['speakerName'] !== "undefined") {
            $payload['metadata'] = json_encode(array("speaker - name" => $translation['speakerName']));
        }

        // post
        $url = $this->URL_POST_TO_AMARA;
        $url = str_replace('__VIDEO__', $basic['videoId'], $url);
        $url = str_replace('__LANG__', $basic['lang'], $url);
        $opt = array(
            "method"      => "post",
            "headers"     => $basic['headers'],
            "payload"     => $payload,
            //,muteHttpExceptions: true
        );
        $resUpload = $this->Socket->fetch($url, $opt);
        return array("success" => $resUpload);
    }
}

class AmaraTranslationUploaderException extends RuntimeException {
    public function getJsonMessage() {
        return json_encode(array('err' => $this->getMessage()));
    }
}

class AmaraTranslationUploaderSocket {

    private $testMode = false;
    public function initTest() {
        return $this->testMode = true;
    }

    public function returnJson($obj) {
        header( 'Content-Type: text/javascript; charset=utf-8' );
        echo json_encode($obj);
    }

    public function fetch($url, $opt) {
        try {
            // 通信パラメタ設定
            $ch = curl_init();
            // URL
            curl_setopt($ch, CURLOPT_URL, $url);
            // method
            if ($opt['method'] === "post") {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            // ヘッダー
            $headers = array();
            if (!empty($opt['headers'])) {
                foreach ($opt['headers'] as $k => $v) {
                    $headers[] = $k . ": " . $v;
                }
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            // postパラメータ
            if (!empty($opt['payload'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['payload']);
            }
            // 実行結果を標準出力でなく取り出すように
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//if (preg_match('/test_post_to_amara/',$url)) {
//    echo "<pre>";
//    print_r($headers);
//    print_r($opt['payload']);
//    print_r($opt['method']);
//    echo "</pre>";
//    return "out";
//}

            // 呼び出し
            $result = curl_exec($ch);
            // エラー情報取得
            $errno = curl_errno($ch);
            $error_message = curl_error($ch);
            // 通信をクローズ
            curl_close($ch);
        } catch (Exception $e) {
            throw new AmaraTranslationUploaderException($e->getMessage());
        }
        if (!empty($errno)) {
            throw new AmaraTranslationUploaderException($errno . ":" . $error_message);
        }
        $_result = json_decode($result, true);
        if (empty($_result)) {
            throw new AmaraTranslationUploaderException(print_r($result, true));
        }
        return $_result;
    }
}
