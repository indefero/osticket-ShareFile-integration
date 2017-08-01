<?php
namespace shareFile;
require_once('prepend.php');

if(!defined('INCLUDE_DIR')) die('!');
require_once(INCLUDE_DIR.'class.draft.php');
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'shareFile/handleEmails.php');
require_once(INCLUDE_DIR.'shareFile/ErrorLog.php');
require_once(SF_SETTINGS_PATH);

$mbox 	= EmailPOP3Login(EMAIL_HOST, EMAIL_PORT, EMAIL_USERNAME, EMAIL_PASSWORD, EMAIL_FOLDER, false);
$list 	= EmailPOP3ListMessages($mbox);

if(count($list)>0){
	ProcessEmails($mbox, $list);
}

?>
