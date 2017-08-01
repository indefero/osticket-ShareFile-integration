<?php
namespace shareFile;
require_once('prepend.php');

if(!defined('INCLUDE_DIR')) die('!');
require_once(INCLUDE_DIR.'class.draft.php');
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'shareFile/shareFileFunctions.php');
require_once(INCLUDE_DIR.'shareFile/ErrorLog.php');
require_once(SF_SETTINGS_PATH);
require_once(INCLUDE_DIR.'shareFile/PHPMailer/PHPMailerAutoload.php');


const EMAIL_PROCESS_ERROR_INVALID = 0;
const EMAIL_PROCESS_ERROR_WRONG_DEVPROD = 1;

function EmailPOP3Login($host,$port,$user,$pass,$folder="INBOX",$ssl=false){
    $ssl = ($ssl==false) ? "/novalidate-cert":"";
	$finalString= "{"."$host:$port/pop3$ssl"."}$folder";

	$mbox = imap_open($finalString, $user, $pass, NULL, 10);
	if ($mbox == false) {
        throw new Exception(    "Opening mailbox failed\n"
                                .implode(", ", imap_errors())
                                .implode(", ", imap_alerts())
                                ."\nUsername is: " . $user
                                ."\nPW is: " . $pass
                                ."\nFolder is: " . $folder
                            );
	}
    imap_errors();
    imap_alerts();
    return ($mbox);
}

function EmailPOP3stat($connection){
    $check = imap_mailboxmsginfo($connection);
    return ((array)$check);
}

function EmailPOP3SendMail($recipient, $subject, $message, $retryAttempts=6){
    $mail = new \PHPMailer;
    //$mail->SMTPDebug = 4;                               // Enable verbose debug output
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = EMAIL_HOST;                             // Specify main SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    //$mail->SMTPSecure = false;
    $mail->Username = EMAIL_USERNAME;                     // SMTP username
    $mail->Password = EMAIL_PASSWORD;                     // SMTP password
    //$mail->SMTPAutoTLS = false;
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 587;                                    // port to connect to
    //$mail->Port = 25;                                    // port to connect to

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom(EMAIL_ADDRESS, EMAIL_USERNAME);
    $mail->addAddress($recipient);                           // Add a recipient
    $mail->Subject = $subject;
    $mail->Body    = $message;
    $mail->isHTML(true);                                  // Set email format to HTML

    while($retryAttempts > 0){
        if(!$mail->send()) {
            $retryAttempts-=1;
            //1.0 seconds * random
            $sleepValue = 1000000 * rand(15, 30);
            C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . ">\r\n Message could not be sent. Mailer Error: ". $mail->ErrorInfo.
            "\r\n    Trying again; ".$retryAttempts." Attempts remaining", C_SF_ErrorLog::WARN);
            usleep($sleepValue);
        } else {
            return true;
        }
    }
    C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . ">\r\n Message could not be sent. Mailer Error: ". $mail->ErrorInfo,
    C_SF_ErrorLog::ERROR);
    return false;
}

function EmailPOP3ListMessages($connection){
    $mailboxInfo = imap_check($connection);
    //range is between 1 and number of messages in mbox (all messages)
    $range = "1:".$mailboxInfo->Nmsgs;
    //return empty array if there are no messages
    if ($mailboxInfo->Nmsgs == 0) { return array();}

    $response = imap_fetch_overview($connection, $range);
    $result = NULL;
    foreach ($response as $msg) $result[$msg->msgno]=(array)$msg;
        return $result;
}

function EmailPOP3RetrieveMessage($connection,$messageID){
    return(imap_fetchheader($connection,$messageID,FT_PREFETCHTEXT));
}


function EmailParseGetPart($mbox,$mid,$p,$partno) {
    // $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
    global $htmlmsg,$plainmsg,$charset,$attachments;


    // DECODE DATA
    $data = ($partno)?
        imap_fetchbody($mbox,$mid,$partno):  // multipart
        imap_body($mbox,$mid);  // simple
    // Any part may be encoded, even plain text messages, so check everything.
    if ($p->encoding==4)
        $data = quoted_printable_decode($data);
    elseif ($p->encoding==3)
        $data = base64_decode($data);


    // PARAMETERS
    // get all parameters, like charset, filenames of attachments, etc.
    $params = array();
    if (array_key_exists('parameters',$p))
        foreach ($p->parameters as $x)
            $params[strtolower($x->attribute)] = $x->value;
    if (array_key_exists('dparameters',$p))
        foreach ($p->dparameters as $x)
            $params[strtolower($x->attribute)] = $x->value;




    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if (array_key_exists('filename',$params) || array_key_exists('name', $params) ) {
        // filename may be given as 'Filename' or 'Name' or both
        $filename = (array_key_exists('filename',$params)) ? $params['filename'] : $params['name'];
        // filename may be encoded, so see imap_mime_header_decode()
        $attachments[$filename] = $data;  // this is a problem if two files have same name
    }


    // TEXT
    if ($p->type==0 && $data) {
        // Messages may be split in different parts because of inline attachments,
        // so append parts together with blank row.
        if (strtolower($p->subtype)=='plain'){
            $plainmsg = $plainmsg . trim($data) ."\n\n";
		}
        else{
           $htmlmsg = $htmlmsg . $data ."<br><br>";
		}
        $charset = $params['charset'];  // assume all parts are same charset
    }

    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    elseif ($p->type==2 && $data) {
        $plainmsg = $plainmsg . $data."\n\n";
    }

    // SUBPART RECURSION
    if (array_key_exists('parts',$p)) {
        foreach ($p->parts as $partno0=>$p2)
            EmailParseGetPart($mbox,$mid,$p2,$partno.'.'.($partno0+1));  // 1.2, 1.2.1, etc.
    }
}

function EmailParseGetMSG($mbox,$mid) {
    // input $mbox = IMAP stream, $mid = message id
    // output all the following:
    global $charset,$htmlmsg,$plainmsg,$attachments;
    $htmlmsg = $plainmsg = $charset = '';
    $attachments = array();

    // HEADER
    $h = imap_header($mbox,$mid);
    // add code here to get date, from, to, cc, subject...

    // BODY
    $structure = imap_fetchstructure($mbox,$mid);

    if (!array_key_exists('parts',$structure)){  // if message has no parts
        EmailParseGetPart($mbox,$mid,$structure,0);  // pass 0 as part-number
    }
    else {  // multipart: cycle through each part
        foreach ($structure->parts as $partno0=>$p)
            EmailParseGetPart($mbox,$mid,$p,$partno0+1);
    }
    return $htmlmsg;
}
function EmailExport($mbox, $msgno, $fpath){
	global $charset,$htmlmsg,$plainmsg,$attachments;
	$messageNo = $msgno;
	//$header = EmailPOP3RetrieveMessage($mbox, $messageNo);
	EmailParseGetMSG($mbox, $messageNo);
	$emailName = "\\" . date("[M_d_Y(h_i_A)]") . "___" . $msgno . ".html";
	$fpath = $fpath . $emailName;

	$result = file_put_contents($fpath, $htmlmsg);

	if($result == false){
		C_SF_ErrorLog::WriteError($fpath);
		C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . "> Couldn't Export Email to path");
	}
	return $result;
}

//returns array a[0]=string ticketID, a[1]=string shareID, a[2]=fullname
function EmailParseGetData($mbox, $msgno){
	global $charset,$htmlmsg,$plainmsg,$attachments;
	$messageNo = $msgno;
	//$header = EmailPOP3RetrieveMessage($mbox, $messageNo);
	EmailParseGetMSG($mbox, $messageNo);

	$emailString = trim($htmlmsg);
    $folderName_PROD =  SF_ROOT_FOLDER_NAME;
    $folderName_DEV =  SF_ROOT_FOLDER_NAME_DEV;
    $substring_PROD = $folderName_PROD .  " &gt;";
    $substring_DEV = $folderName_DEV .  " &gt;";
    $folderName = $folderName_PROD;
    $substring = $substring_PROD;
    if(SF_IN_DEV){
        $folderName =  $folderName_DEV;
        $substring = $substring_DEV;
    }

    $contains_PROD = strpos($emailString, $substring_PROD);
    $contains_DEV = strpos($emailString, $substring_DEV);

    if($contains_PROD==false && SF_IN_DEV==false){
        if($contains_DEV==true){
            return array(null, EMAIL_PROCESS_ERROR_WRONG_DEVPROD);
        }
        return array(null, EMAIL_PROCESS_ERROR_INVALID);
    }
    if($contains_DEV==false && SF_IN_DEV==true){
        if($contains_PROD==true){
            return array(null, EMAIL_PROCESS_ERROR_WRONG_DEVPROD);
        }
        return array(null, EMAIL_PROCESS_ERROR_INVALID);
    }

	$ticketID=0;

	//move past the substring
	$offset = strlen ($folderName) + 6;
	$ticketIDStart= strpos($emailString, $substring)+ $offset;

    //end at whatever tag comes first, </a> or </span>
	$end1= strpos($emailString, "</a>", $ticketIDStart) ;
    $end2= strpos($emailString, "</span>", $ticketIDStart) ;
    $ticketIDEnd = ($end1<$end2) ? $end1 : $end2;

	$ticketID=substr($emailString, $ticketIDStart, ($ticketIDEnd - $ticketIDStart));

	$substring="https://keystonecollects.sharefile.com/d/";
	$shareIDStart= strpos($emailString, $substring) + strlen ($substring);
    //end at whatever comes first, </b>, ", or </span>
    $end1 = strpos($emailString, "</b>", $shareIDStart);
    $end2 = strpos($emailString, '"', $shareIDStart);
    $end3 = strpos($emailString, '</span>', $shareIDStart);

	$shareIDEnd = ($end1<$end2) ? $end1 : $end2;
    $shareIDEnd = ($shareIDEnd<$end3) ? $shareIDEnd : $end3;

	$shareID=substr($emailString, $shareIDStart, ($shareIDEnd - $shareIDStart));

    C_SF_ErrorLog::WriteError("Ticket Pulled from email: ".$ticketID." ShareID: ".$shareID, C_SF_ErrorLog::INFO);

	return array((int)$ticketID, $shareID);
}

function EmailClose($mbox){
	//CL_EXPUNGE deletes all messages marked for deletion
	imap_close($mbox, CL_EXPUNGE);
}

function GetTicketUserID($ticketID){
    $ticket 	= \Ticket::lookup($ticketID);
    return $ticket->getOwner();
}

function GetTicketUserEmail($ticketID){
    $ticket 	= \Ticket::lookup($ticketID);
    return $ticket->getEmail();
}

function GetTicketAssignee($ticketID){
    $ticket 	= \Ticket::lookup($ticketID);
    return $ticket->getAssignee();
}

function GetTicketAssigneeEmail($ticketID){
    $staff = GetTicketAssignee($ticketID);
    $email = NULL;
    if($staff == false){
        $email = ADMINISTRATOR_EMAIL;
    }
    else{
        $email = $staff->getEmail();
    }

    return $email;
}

//returns new message ID from OSTicket or NULL
function UploadShareFilesToOsTicket($shareID, $ticketID, $message){
	$errors		= NULL;
	$user 		= \StaffAuthenticationBackend::process(OST_USERNAME, OST_PASSWORD, $errors);
	$ticket 	= \Ticket::lookup($ticketID);
	if($ticket==NULL){
		C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . ">\r\n Couldn't get Ticket from ticket ID: ".$ticketID);
		return NULL;
	}

    C_SF_ErrorLog::WriteError("Attempting to upload attachment to OSTicket", C_SF_ErrorLog::INFO);

	//Get file from shareFile
	$token = GetShareFileToken();

	$data = DownloadFilesFromShare($token, $shareID);

	$fileData = $data[0];
	$fileName = $data[1];

	//Upload Response to Ticket with file attachments
	$vars 		= array(
		"response" 			=> $message,
		"poster" 			=> $user,
		"staffId" 			=> $user->getId(),
		"attachments" 		=> array(
								array(
									"data" => $fileData,
									"name" => $fileName
								)
							)
	);

    $HTML_TICKET_LINK= "<b><a href=".SERVER_ADDRESS . "/scp/tickets.php?id=".$ticketID.">Kit</a></b>";

	//If the file cannot be read, then carry on posting a message anyway
	//if we recieved a valid ticket ID then it's clear that SOMETHNG was meant to be uploaded
	if( ($fileData==NULL) ){
		C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . ">\r\n Couldn't get file from share ID: ".$shareID."\r\n    for ticket#: " .$ticketID);
		$response = "";
		if($fileName==1)		{$response = "User uploaded file to ShareFile, but OSTicket could not download it because it is too large"
												."\r\n - Helpdesk can grab the file for you";}
		else if($fileName==2)	{$response = "User uploaded file to ShareFile, but it was flagged as Malware";}
		else					{$response = "User Uploaded File to ShareFile, but it could not be saved to OSTicket";}
		$vars["attachments"] = NULL;
		$vars["response"]	 = $response;

        EmailPOP3SendMail(GetTicketAssigneeEmail($ticketID), EMAIL_SEND_SUBJECT, EMAIL_SEND_MESSAGE_FAIL_TAG.
            " File has failed being uploaded to the following ticket, please contact help desk:".$HTML_TICKET_LINK);
	}
    else{
        C_SF_ErrorLog::WriteError("Attempting to send email", C_SF_ErrorLog::INFO);
        EmailPOP3SendMail(GetTicketAssigneeEmail($ticketID), EMAIL_SEND_SUBJECT, EMAIL_SEND_MESSAGE_SUCCESS_TAG.
            " File has been uploaded to the following ticket:".$HTML_TICKET_LINK);
        C_SF_ErrorLog::WriteError("Email sent", C_SF_ErrorLog::INFO);
    }

    /*
    Commented out, will reopen ticket
    if($ticket->isOpen() == false ){
        C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . ">\r\n Trying to reopen closed ticket...", C_SF_ErrorLog::INFO);
        $setStatusReturn = $ticket->setStatus("open");
        C_SF_ErrorLog::WriteError("Set Status function returned [" . $setStatusReturn . "]", C_SF_ErrorLog::INFO);
    }*/
    C_SF_ErrorLog::WriteError("Attempting to post reply", C_SF_ErrorLog::INFO);
	$newMessage = $ticket->postReply($vars, $errors);
	return $newMessage;
}

function ProcessEmails($mbox, $list){
	$uploadMessage  = "\r\nFile has been uploaded\r\n";

	//Implement way to check and see if email being processed is actually from sharefile
	foreach ($list as $index => $message){
		//Get email belonging to person who uploaded the file
		$boolSuccess = false;
		$messageNumber=$message["msgno"];
		$data = EmailParseGetData($mbox, $messageNumber);
		$ticketID = $data[0];
		$shareID  = $data[1];

		$message = NULL;
		if(($ticketID!=null)and($ticketID!="")and($shareID!=null)and($shareID!="")){
			$message = UploadShareFilesToOsTicket($shareID, (int)$ticketID, $uploadMessage);
		}
        else{
            $message  = $data[1];
        }

		if ($message === EMAIL_PROCESS_ERROR_INVALID || $message === null){
			C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . "> Invalid email recieved");
		}
        elseif ($message === EMAIL_PROCESS_ERROR_WRONG_DEVPROD){
            //ignore message, continue to next message
            C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . "> EMAIL_PROCESS_ERROR_WRONG_DEVPROD", C_SF_ErrorLog::DEBUG);
            continue;
        }
		else{
			$boolSuccess = true;
		}
		//Mark email for deletion
		if($boolSuccess == true){
			imap_delete($mbox, $messageNumber);
		}
		else{
			//If email cannot be processed, save it at the failExportPath
			if(EMAIL_FAIL_EXPORT == true){
				EmailExport($mbox, $messageNumber, EMAIL_FAIL_EXPORT_PATH);
			}
			imap_delete($mbox, $messageNumber);
		}
	}

	EmailClose($mbox);
}
?>
