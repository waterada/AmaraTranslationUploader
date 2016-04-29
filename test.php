<?php
require_once dirname(__FILE__).'/AmaraTranslationUploader.php';
$BASE_URL = "http://waterada.raindrop.jp/AmaraTranslationUploader";

$output = array();
$output[] = '[act] '.defval($_POST['act'], '(blank)');

$basic = array(
    "headers"  => array("X-apikey" => "****", "X-api-username" => "wataru_terada"),
    "videoId"  => defval($_POST["videoId"], "1"),
    "userName" => "wataru_terada",
);
$output[] = '-------------';
$output[] = '[basic]';
$output[] = print_r($basic, true);

$translation_json = defval($_POST['translation'], json_encode(array(
    'subtitles' => array(
        array("start" => "00:00:01,000", "end" => "00:00:02,000", "text" => "a1", "paragraph" => "true"),
        array("start" => "00:00:02,000", "end" => "00:00:03,000", "text" => "a2"),
        array("start" => "00:00:03,000", "end" => "00:00:04,000", "text" => "b1", "paragraph" => "true"),
        array("start" => "00:00:04,000", "end" => "00:00:05,000", "text" => "b2"),
        array("start" => "00:00:05,000", "end" => "00:00:06,000", "text" => "b3"),
        array("start" => "00:00:06,000", "end" => "00:00:07,000", "text" => "c1", "paragraph" => "true"),
        array("start" => "23:00:07,000", "end" => "23:00:08,000", "text" => "c2"),
    ),
    'title' => 'TITLE1',
    'description' => 'DESC1',
    'speakerName' => 'Wataru',
)));

try {
    switch ($_POST['act']) {
        case 'test_uploader': //uploader を実際に呼んでみる(TESTモード)
            $Socket = new AmaraTranslationUploaderSocket();
            $actual = $Socket->fetch($BASE_URL.'/upload.php', array(
                "method" => "post",
                "payload"=> array(
                    "basic"=> json_encode($basic),
                    "translation"=> $translation_json,
                    "TEST_MODE" => "1",
                ),
            ));
            break;
        case 'test_uploader_real': //uploader を実際に呼んでみる(本気モード)
            $Socket = new AmaraTranslationUploaderSocket();
            $actual = $Socket->fetch($BASE_URL.'/upload.php', array(
                "method" => "post",
                "payload" => array(
                    "basic"=> json_encode($basic),
                    "translation"=> $translation_json,
                ),
            ));
            break;
        case 'test_amara_auth': //amaraのAPIを呼んでみる
            $AmaraTranslationUploader = new AmaraTranslationUploader();
            $actual = $AmaraTranslationUploader->_checkAuth($basic);
            break;
        default:
            //何もしない
            $actual = null;
    }
} catch (AmaraTranslationUploaderException $e) {
    $actual = $e->getJsonMessage();
}
$output[] = '-------------';
$output[] = '[actual]';
$output[] = print_r(json_decode($actual, true), true);
$output[] = print_r($actual, true);

function defval(&$val, $def) { return (isset($val) ? $val : $def); }
?>
<html>
<body>

<form action="test.php" method="post">
    videoId: <br/>
    <input type="text" name="videoId" value="<? echo htmlspecialchars($basic["videoId"]) ?>"> (1:テストで権限あり, 2:テストで権限なし, Ep8l0V0imNN5:実在で権限なし)<br/>
    <br/>

    translation:<br/>
    <textarea cols="80" rows="20" name="translation"><?php echo htmlspecialchars($translation_json); ?></textarea><br/>

    <hr/>

    <h2>uploader を実際に呼んでみる(TESTモード)</h2>
    <input type="submit" name="act" value="test_uploader">

    <hr/>

    <h2>uploader を実際に呼んでみる(本気モード)</h2>
    <input type="submit" name="act" value="test_uploader_real">

    <hr/>

    <h2>amaraのAPIを呼んでみる</h2>
    <input type="submit" name="act" value="test_amara_auth">

    <hr/>
    <br/>

    <h2>結果出力</h2>
    <pre><? echo htmlspecialchars(implode("\n",$output)); ?></pre>
</form>

</body>
</html>
