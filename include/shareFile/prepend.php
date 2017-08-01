<?php
if(defined("SF_TEST_MODE")){
    $SF_C_MAIN_INCLUDE_FILE = __DIR__ . '/test/includePath.php';
}
else{
    $SF_C_MAIN_INCLUDE_FILE = __DIR__ . '/../../main.inc.php';
}
require_once( $SF_C_MAIN_INCLUDE_FILE );


if(defined("SF_TEST_MODE")){
    define("SF_SETTINGS_PATH", INCLUDE_DIR.'shareFile/test/settings.php');
}
else{
    define("SF_SETTINGS_PATH", INCLUDE_DIR.'shareFile/settings.php');
}
?>
