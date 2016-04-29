<?php
if (empty($_POST['subtitles'])) {
    throw new Exception('subtitles is empty:params='.print_r($_POST,true), 501);
}
if (!preg_match('/^<tt/', $_POST['subtitles'])) {
    throw new Exception('not start with tt tag:params='.print_r($_POST,true), 502);
}

header( 'Content-Type: text/javascript; charset=utf-8' );
echo json_encode(array(
    'ok' => print_r($_POST,true)
));
