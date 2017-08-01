<?php
namespace shareFile;
use PHPUnit\Framework\TestCase;

require_once(INCLUDE_DIR.'shareFile/generateSFLink.php');
require_once(INCLUDE_DIR.'shareFile/handleEmails.php');
require_once(SF_SETTINGS_PATH);

class SFTest extends TestCase{
    const EMAIL_BODY_PATH = INCLUDE_DIR.'shareFile/test/shareFileTestEmails';
    const EMAIL_HEADER = "NEW SHAREFILE ITEM (TEST)";
    private $mbox;
    private $messagesList;

    private function RefreshLogin(){
        try{
            $this->mbox 	         = EmailPOP3Login(EMAIL_HOST, EMAIL_PORT, EMAIL_USERNAME, EMAIL_PASSWORD, EMAIL_FOLDER, false);
            $this->messagesList 	 = EmailPOP3ListMessages($this->mbox);
            //$this->assertGreaterThan(1, count($list));
        }
        catch(Exception $e){
            echo $e->getMessage();
            $this->assertTrue(false);
        }
    }

    public function setUp(){
        //reset ticket state
        \Ticket::staticInit();
        $this->RefreshLogin();
    }

    public function SendEmail($htmlMessage, $correctTicketID, $correctShareID){
        $this->assertEquals(0, count($this->messagesList));
        echo "Sending Email \n";
        $this->assertTrue(EmailPOP3SendMail(EMAIL_ADDRESS, SFTest::EMAIL_HEADER,$htmlMessage));

        sleep(1);
        echo "Sent Email \n";
        echo "...Parsing \n";

        $this->RefreshLogin();
        $this->assertEquals(1, count($this->messagesList));
        //message number to be processed (only on in the mailbox, #1)
        $messageNumber=1;
        $data = EmailParseGetData($this->mbox, $messageNumber);

    	$ticketID = $data[0];
    	$shareID  = $data[1];
        $this->assertEquals($correctTicketID, $ticketID);
        $this->assertEquals($correctShareID, $shareID);
        echo "Parsed \n";

        //got the relevent information from the email, can delete it now
        $deleteResult = imap_delete($this->mbox, $messageNumber);
        $this->assertTrue($deleteResult);
        //close and expunge messages that were marked by imap_delete
        EmailClose($this->mbox);
        //wait for message to delete
        echo "Deleting Email \n";
        sleep(1);
        //refresh and reopen mbox
        $this->RefreshLogin();
        $this->assertEquals(0, count($this->messagesList));
        echo "Deleted Email \n";

        return $data;
    }

    /**
    * @group usesEmail
    */
    public function testDevProdDemarcation(){
        //is in prod, should ignore dev emails
        $this->assertEquals(0, count($this->messagesList));


        $htmlMessage = file_get_contents(SFTest::EMAIL_BODY_PATH.'/workingFile.html');//success
        $this->assertTrue(EmailPOP3SendMail(EMAIL_ADDRESS, SFTest::EMAIL_HEADER,$htmlMessage));

        $htmlMessage = file_get_contents(SFTest::EMAIL_BODY_PATH.'/workingFile_DEV.html');//ignore
        $this->assertTrue(EmailPOP3SendMail(EMAIL_ADDRESS, SFTest::EMAIL_HEADER,$htmlMessage));
        $this->RefreshLogin();
        $this->assertEquals(2, count($this->messagesList));

        include(INCLUDE_DIR.'shareFile/pingEmailServer.php');

        //only prod emails should upload a message to the ticket
        $ticket = \Ticket::$_testTicket;
        $this->assertEquals(1, count($ticket->_replies));
        //Examine replies attachments
        $attachmentCount=0;
        for($i=0; $i<count($ticket->_replies); $i++){
            $reply = $ticket->_replies[$i];
            if($reply["attachments"]!=NULL){
                $attachmentCount+=1;
            }
        }
        $this->assertEquals(1, $attachmentCount);

        //should be two emails, original dev email and upload notificaiton email
        $this->RefreshLogin();
        $this->assertEquals(2, count($this->messagesList));
        $failCount=0;
        $successCount=0;
        for($i=1; $i<=count($this->messagesList); $i++){
            $message = EmailParseGetMSG($this->mbox, $i);
            if(strpos ($message, EMAIL_SEND_MESSAGE_FAIL_TAG)!=false){
                $failCount+=1;
            }
            elseif(strpos ($message, EMAIL_SEND_MESSAGE_SUCCESS_TAG)!=false){
                $successCount+=1;
            }
            imap_delete($this->mbox, $i);
        }
        //one success, zero fails
        $this->assertEquals(1, $successCount);
        $this->assertEquals(0, $failCount);
        EmailClose($this->mbox);
        $this->assertEquals(0, C_SF_ErrorLog::DEBUG_ErrorsOccured(C_SF_ErrorLog::FATAL));
    }
    /**
    * @group usesEmail
    */
    public function testEmailParseOSTicketUpload(){
        $this->assertEquals(0, count($this->messagesList));

        $htmlMessage = file_get_contents(SFTest::EMAIL_BODY_PATH.'/workingFile.html');//success
        $this->assertTrue(EmailPOP3SendMail(EMAIL_ADDRESS, SFTest::EMAIL_HEADER,$htmlMessage));
        $htmlMessage = file_get_contents(SFTest::EMAIL_BODY_PATH.'/tooLarge.html');//fail
        $this->assertTrue(EmailPOP3SendMail(EMAIL_ADDRESS, SFTest::EMAIL_HEADER,$htmlMessage));
        $htmlMessage = file_get_contents(SFTest::EMAIL_BODY_PATH.'/workingFile.html');//success
        $this->assertTrue(EmailPOP3SendMail(EMAIL_ADDRESS, SFTest::EMAIL_HEADER,$htmlMessage));
        $this->RefreshLogin();

        $this->assertEquals(3, count($this->messagesList));

        include(INCLUDE_DIR.'shareFile/pingEmailServer.php');

        //all emails should upload a message to the ticket
        $ticket = \Ticket::$_testTicket;
        $this->assertEquals(3, count($ticket->_replies));
        //Examine replies attachments
        $attachmentCount=0;
        for($i=0; $i<count($ticket->_replies); $i++){
            $reply = $ticket->_replies[$i];
            if($reply["attachments"]!=NULL){
                $attachmentCount+=1;
            }
        }
        $this->assertEquals(2, $attachmentCount);

        //ensure an email was sent for each attempted upload
        $this->RefreshLogin();
        $this->assertEquals(3, count($this->messagesList));
        $failCount=0;
        $successCount=0;
        for($i=1; $i<=count($this->messagesList); $i++){
            $message = EmailParseGetMSG($this->mbox, $i);
            if(strpos ($message, EMAIL_SEND_MESSAGE_FAIL_TAG)!=false){
                $failCount+=1;
            }
            elseif(strpos ($message, EMAIL_SEND_MESSAGE_SUCCESS_TAG)!=false){
                $successCount+=1;
            }
            imap_delete($this->mbox, $i);
        }
        //two successes, one fail
        $this->assertEquals(2, $successCount);
        $this->assertEquals(1, $failCount);

        EmailClose($this->mbox);
        $this->assertEquals(0, C_SF_ErrorLog::DEBUG_ErrorsOccured(C_SF_ErrorLog::FATAL));
    }

    /**
    * @dataProvider shareFileDownloadProvider
    * @depends testEmailParseOSTicketUpload
    * @group usesEmail
    */
    public function testShareFileDownload($correctFileName, $htmlMessagePath, $correctTicketID, $correctShareID, $shouldSucceed, $errorCodeExpected=0){
        $emailBody = file_get_contents($htmlMessagePath);
        $data = $this->SendEmail($emailBody, $correctTicketID, $correctShareID);
        $ticketID = $data[0];
    	$shareID  = $data[1];

        //Get file from shareFile
        echo "Downloading File \n";
    	$token = GetShareFileToken();
    	$data = DownloadFilesFromShare($token, $shareID);
        $fileData = $data[0];
    	$fileName = $data[1];
        //if error has occured
        if($fileData == null){
            $this->assertTrue(!$shouldSucceed);
            $this->assertEquals($data[1], $errorCodeExpected);
            return;
        }
        $this->assertEquals($correctFileName, $fileName);

        //create/open and overwrite file
        echo "Saving File \n";
        $filePath = INCLUDE_DIR.'shareFile\test\downloadedFiles\\'.$fileName;
        $fstream = fopen($filePath, 'c');
        fwrite($fstream, $fileData);

        //make sure that this test was meant to succeed
        $this->assertTrue($shouldSucceed);
    }

    public function shareFileDownloadProvider(){
        return [
            'Working File'  => ["workingFile.jpg",
                                SFTest::EMAIL_BODY_PATH.'/workingFile.html',
                                \Ticket::$_testTicketID, 'se35e6852b9546f18',
                                true
                            ],
            'Too Big File'  => ["TooLarge.7z",
                                SFTest::EMAIL_BODY_PATH.'/tooLarge.html',
                                \Ticket::$_testTicketID, 's130b9d15090466ca',
                                false,
                                SF_DOWNLOAD_ERROR_TOO_LARGE
                                ]
        ];
    }
}
?>
