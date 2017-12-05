<?php
namespace shareFile;
require_once('prepend.php');

if(!defined('INCLUDE_DIR')) die('!');

require_once(INCLUDE_DIR.'class.draft.php');
require_once(INCLUDE_DIR.'class.ticket.php');

require_once(SF_SETTINGS_PATH);
require_once(INCLUDE_DIR.'shareFile/shareFileFunctions.php');
require_once(INCLUDE_DIR.'shareFile/ErrorLog.php');
require_once(INCLUDE_DIR.'shareFile/ticketAnalysis.php');

function GenerateSFLinkFromTicketID($ticketID){
    $token = GetShareFileToken();
    if (!$token) {
        throw new Exception( "<b>Cannot acquire ShareFile Token, please contact helpdesk to resolve this issue</b>" );
    }
	//osticket folder
	$folder = GetRootDir($token, SF_IN_DEV, false);
	if($folder == NULL){
        throw new Exception( "<b>Cannot generate link, RootDir doesn't exist, please contact helpdesk to resolve this issue</b>" );
	}

	//folder named after $ticketId inside osticket
	$folder = CreateFolder($token, $folder->Id, strval($ticketID));
    if($folder == NULL){
        throw new Exception(  "<b>Cannot generate link, Folder named " . (strval($ticketID)) . " could not be added to root folder; Please contact helpdesk to resolve this issue</b>");
    }

	$share = CreateShareRequest($token, SF_HOSTNAME, $folder);

	return '<a href="'.$share->Uri.'">Please upload your file by clicking here</a>';
}

/*
function ticketAnalysisTestFunction($ticketID){
    $ticket = \Ticket::lookup($ticketID);
    $replies = GetReplies($ticket);

    echo "All Replies: <br/>";
    $int = 0;
    foreach($replies as $reply){
        echo "Entry" . $int;
        echo "<br/>";
        echo $reply->getBody()->getClean();
        echo "<br/>";
        $int = $int + 1;
    }

    $int = 0;
    echo "GetSFLinkPosts <br/>";
    $filteredReplies = GetSFLinkPosts($replies);
    foreach($filteredReplies as $reply){
        echo "Entry" . $int;
        echo "<br/>";
        echo $reply->getBody()->getClean();
        echo PHP_EOL;
        $int = $int + 1;
    }

    $lastReply = GetLastReply($filteredReplies);
    echo "Last reply: <br/>" . $lastReply->getBody();


    $owner = GetLastEntry(GetSFLinkPosts(GetEntries($ticket)))->getStaff()->getEmail();
    echo "Ticket owner email will be: " . $owner;
}
*/

function GenerateSFLinkFromTicketIDWeb(){
    $UNSAFE_ticketID    = $_POST['id'];
    $ticketID           = filter_var($UNSAFE_ticketID,  FILTER_SANITIZE_NUMBER_INT);
    $ticketIDValidate   = filter_var($ticketID,         FILTER_VALIDATE_INT);

    if (($ticketID == "false") or ($ticketID == NULL) or ($ticketIDValidate == false)) {
        echo "<b>Cannot generate link (no valid ticket ID in URL), please click <u>Ticket#</u> at the top of the page and try again</b>";
        return;
    }

    try{
        echo GenerateSFLinkFromTicketID($ticketID);
    }
    catch(Excpetion $e){
        echo 'Exception: ',  $e->getMessage(), "\r\n";
        C_SF_ErrorLog::WriteError($e->getMessage(), C_SF_ErrorLog::FATAL);
    }
}

/* if started on the web...*/
if ( isset($_POST["id"]) && !empty($_POST["id"]) ) {
    GenerateSFLinkFromTicketIDWeb();
}
?>
