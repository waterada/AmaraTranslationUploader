<?php
require_once 'C:/ws_php/enq-author/app/vendor/autoload.php';
require_once dirname(__FILE__).'/AmaraTranslationUploader.php';

/**
 * Class AmaraTranslationUploaderTest
 *
 * c:\waterada\AmaraTranslationUploader>php C:\ws_php\enq-author\app\vendor\phpunit\phpunit\phpunit.php AmaraTranslationUploaderTest
 *
 * @property AmaraTranslationUploaderTestSocket $Socket
 * @property AmaraTranslationUploader           $AmaraTranslationUploader
 */
class AmaraTranslationUploaderTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->Socket = new AmaraTranslationUploaderTestSocket();
        $this->AmaraTranslationUploader = new AmaraTranslationUploader();
        $this->AmaraTranslationUploader->Socket = $this->Socket;
    }

    //エラーでないこと
    public function test__checkParams_ok() {
        $this->AmaraTranslationUploader->_checkParams(array('headers' => '1', 'videoId' => '1', 'userName' => '1'), array('subtitles' => '1'));
    }

    public function provider__checkParams() {
        return array(
            array(array('headers' => '', 'videoId' => '1', 'userName' => '1'), array('subtitles' => '1'), 'Invalid parameters:headers'),
            array(array('headers' => '1', 'videoId' => '', 'userName' => '1'), array('subtitles' => '1'), 'Invalid parameters:videoId'),
            array(array('headers' => '1', 'videoId' => '1', 'userName' => ''), array('subtitles' => '1'), 'Invalid parameters:userName'),
            array(array('headers' => '1', 'videoId' => '1', 'userName' => '1'), array('subtitles' => ''), 'Invalid parameters:subtitles'),
        );
    }

    /**
     * @dataProvider provider__checkParams
     */
    public function test__checkParams_ng($basic, $translation, $expected) {
        try {
            $this->AmaraTranslationUploader->_checkParams($basic, $translation);
        } catch (AmaraTranslationUploaderException $e) {
            $this->assertJsonStringEqualsJsonString('{"err":"'.$expected.'"}', $e->getJsonMessage(), $expected);
            return;
        }
        $this->fail('No Error!', $expected);
    }

    public function test__checkAuth__ok() {
        $this->Socket->fetchRes = array('objects' => array(array('key' => 'my task')));
        $this->AmaraTranslationUploader->_checkAuth(array('headers' => '[HEAD1]', 'videoId' => '[VID1]', 'userName' => '[USER1]'));
        $this->assertContains('video_id=[VID1]', $this->Socket->fetchUrl);
        $this->assertContains('assignee=[USER1]', $this->Socket->fetchUrl);
        $this->assertEquals('[HEAD1]', $this->Socket->fetchOpt['headers']);
    }

    public function test__checkAuth__ng() {
        try {
            $this->Socket->fetchRes = array('objects' => array()); //no tasks
            $this->AmaraTranslationUploader->_checkAuth(array('headers' => '[HEAD2]', 'videoId' => '[VID2]', 'userName' => '[USER2]'));
            $this->assertContains('video_id=[VID2]', $this->Socket->fetchUrl);
            $this->assertContains('assignee=[USER2]', $this->Socket->fetchUrl);
            $this->assertEquals('[HEAD2]', $this->Socket->fetchOpt['headers']);
        } catch (AmaraTranslationUploaderException $e) {
            $this->assertContains('cannot export', $e->getMessage());
            return;
        }
        $this->fail('No Error!');
    }

    public function provider__makeTranslationDfxp() {
        return array(
            array("1 paragraph", array(
                array("start" => "01:00:00,123", "end" => "01:00:00,456", "text" => "aaa"),
            ), 1, '<p begin="01:00:00.123" end="01:00:00.456">aaa</p>'),
            array("3 paragraphs", array(
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "a1", "paragraph" => "true"),
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "a2"),
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "b1", "paragraph" => "true"),
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "b2"),
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "b3"),
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "c1", "paragraph" => "true"),
                array("start" => "23:00:00,123", "end" => "23:00:00,456", "text" => "c2"),
            ), 3, '<p begin="23:00:00.123" end="23:00:00.456">c2</p>'),
            array("escape", array(
                array("start" => "00:00:00,000", "end" => "00:00:00,000", "text" => "A<B>C&D\"E'F\nA<B>C&D\"E'F\nG", "paragraph" => "true"),
            ), 1, '<p begin="00:00:00.000" end="00:00:00.000">A&lt;B&gt;C&amp;D&quot;E\'F<br/>A&lt;B&gt;C&amp;D&quot;E\'F<br/>G</p>'),
        );
    }

    /**
     * @dataProvider provider__makeTranslationDfxp
     */
    public function test__makeTranslationDfxp($TITLE, $subtitles, $divCount, $p) {
        $actual = $this->AmaraTranslationUploader->_makeTranslationDfxp($subtitles);
        $this->assertSelectCount('tt > body > div', $divCount, $actual, $TITLE, false);
        $this->assertContains($p, $actual, $TITLE);
    }

    public function test__postTranslationToAmara__with_speakerName() {
        $this->Socket->fetchRes = 'ok';
        $actual = $this->AmaraTranslationUploader->_postTranslationToAmara(
            array('headers' => '[HEAD1]', 'videoId' => '[VID1]', 'lang' => '[ja]'),
            '<tt>...</tt>',
            array('title' => '[TITLE1]', 'description' => '[DESC1]', 'speakerName' => '[SP1]')
        );
        $this->assertContains('/videos/[VID1]/', $this->Socket->fetchUrl);
        $this->assertContains('/languages/[ja]/', $this->Socket->fetchUrl);
        $this->assertEquals('[HEAD1]', $this->Socket->fetchOpt['headers']);
        $payload = $this->Socket->fetchOpt['payload'];
        $this->assertEquals('<tt>...</tt>', $payload['subtitles']);
        $this->assertEquals('[TITLE1]', $payload['title']);
        $this->assertEquals('[DESC1]', $payload['description']);
        $this->assertEquals('[SP1]', $payload['metadata']['speaker - name']);
        $this->assertEquals('ok', $actual['success']);
    }

    public function provider_1() {
        return array(
            array(array('title' => '[TITLE1]', 'description' => '[DESC1]')),
            array(array('title' => '[TITLE1]', 'description' => '[DESC1]', 'speakerName' => '')),
            array(array('title' => '[TITLE1]', 'description' => '[DESC1]', 'speakerName' => null)),
            array(array('title' => '[TITLE1]', 'description' => '[DESC1]', 'speakerName' => 'undefined')),
        );
    }

    /**
     * @dataProvider provider_1
     */
    public function test__postTranslationToAmara__without_speakerName($translation) {
        $this->Socket->fetchRes = 'ok';
        $actual = $this->AmaraTranslationUploader->_postTranslationToAmara(
            array('headers' => '[HEAD1]', 'videoId' => '[VID1]', 'lang' => '[ja]'),
            '<tt>...</tt>',
            $translation
        );
        $this->assertContains('/videos/[VID1]/', $this->Socket->fetchUrl);
        $this->assertContains('/languages/[ja]/', $this->Socket->fetchUrl);
        $this->assertEquals('[HEAD1]', $this->Socket->fetchOpt['headers']);
        $payload = $this->Socket->fetchOpt['payload'];
        $this->assertEquals('<tt>...</tt>', $payload['subtitles']);
        $this->assertEquals('[TITLE1]', $payload['title']);
        $this->assertEquals('[DESC1]', $payload['description']);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertEquals('ok', $actual['success']);
    }
}


class AmaraTranslationUploaderTestSocket extends AmaraTranslationUploaderSocket {
    public $outputText = null;
    public function returnJson($obj) {
        ob_start();
        parent::returnJson($obj);
        $this->outputText = ob_get_contents();
    }

    public $fetchUrl = null;
    public $fetchOpt = null;
    public $fetchRes = null;
    public function fetch($url, $opt) {
        $this->fetchUrl = $url;
        $this->fetchOpt = $opt;
        return $this->fetchRes;
    }
}