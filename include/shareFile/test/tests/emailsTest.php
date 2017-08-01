<?php
namespace shareFile;
use PHPUnit\Framework\TestCase;

require_once(INCLUDE_DIR.'shareFile/generateSFLink.php');
require_once(INCLUDE_DIR.'shareFile/handleEmails.php');
require_once(SF_SETTINGS_PATH);

class SFEmailTest extends TestCase{
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
        $this->RefreshLogin();
    }
    /**
    * @group usesEmail
    */
    public function testLoadEmptyInbox(){
        $this->assertEquals(0, count($this->messagesList));
        EmailClose($this->mbox);
    }

    /**
    * @depends testLoadEmptyInbox
    * @group usesEmail
    */
    public function testSendEmailToInbox(){
        $ticketID=31337;
        $HTML_TICKET_LINK= "<b><a href=".SERVER_ADDRESS . "/scp/tickets.php?id=".$ticketID.">Kit</a></b>";
        echo "Sending Email \n";
        EmailPOP3SendMail(GetTicketAssigneeEmail($ticketID), EMAIL_SEND_SUBJECT, EMAIL_SEND_MESSAGE_SUCCESS_TAG." ".$HTML_TICKET_LINK);

        //wait for message to post
        sleep(1);
        EmailClose($this->mbox);

        $this->RefreshLogin();
        $this->assertEquals(1, count($this->messagesList));
        echo "Sent \n";
    }

    /**
    * @depends testSendEmailToInbox
    * @group usesEmail
    */
    public function testExportEmail(){
        $this->assertEquals(1, count($this->messagesList));
        $messageNumber = $this->messagesList[1]["msgno"];
        echo "Exporting Email \n";
        EmailExport($this->mbox, $messageNumber, INCLUDE_DIR.'shareFile\test\logs\EmailsFailed');
        echo "Exported Email \n";

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
    }
}
?>
