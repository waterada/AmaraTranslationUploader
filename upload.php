<?php
require_once dirname(__FILE__).'/AmaraTranslationUploader.php';

$AmaraTranslationUploader = new AmaraTranslationUploader();
if (!empty($_REQUEST['TEST_MODE'])) {
    $AmaraTranslationUploader->URL_GET_AUTH = "https://raindrop-waterada.ssl-lolipop.jp/AmaraTranslationUploader/test_get_auth.php";
    $AmaraTranslationUploader->URL_POST_TO_AMARA = "https://raindrop-waterada.ssl-lolipop.jp/AmaraTranslationUploader/test_post_to_amara.php";
}
$AmaraTranslationUploader->doPost();