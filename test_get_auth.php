<?php
if (empty($_GET['video_id'])) {
    throw new Exception('video_id is empty:params='.print_r($_POST,true), 501);
}

switch($_GET['video_id']) {
    case "1":
        header( 'Content-Type: text/javascript; charset=utf-8' );
        echo json_encode(array(
            'objects' => array(
                array("aa")
            )
        ));
        break;
    case "2":
        header( 'Content-Type: text/javascript; charset=utf-8' );
        echo json_encode(array(
            'objects' => array(
            )
        ));
        break;
    default:
        throw new Exception('video_id is not 1 or 2:params='.print_r($_POST,true), 502);
}
