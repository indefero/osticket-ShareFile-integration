<?php
namespace shareFile;
use PHPUnit\Framework\TestCase;

require_once(INCLUDE_DIR.'shareFile/generateSFLink.php');
require_once(INCLUDE_DIR.'shareFile/handleEmails.php');
require_once(SF_SETTINGS_PATH);

class SFErrorLogTest extends TestCase{
    public function setUp(){
        C_SF_ErrorLog::ClearErrorFile();
    }
    /**
    * @group usesEmail
    */
    public function testFatalError(){
        $mbox 	         = EmailPOP3Login(EMAIL_HOST, EMAIL_PORT, EMAIL_USERNAME, EMAIL_PASSWORD, EMAIL_FOLDER, false);
        $messagesList 	 = EmailPOP3ListMessages($mbox);
        $this->assertEquals(0, count($messagesList));

        C_SF_ErrorLog::WriteError("GAH", C_SF_ErrorLog::FATAL);

        $mbox 	         = EmailPOP3Login(EMAIL_HOST, EMAIL_PORT, EMAIL_USERNAME, EMAIL_PASSWORD, EMAIL_FOLDER, false);
        $messagesList 	 = EmailPOP3ListMessages($mbox);
        $this->assertEquals(1, count($messagesList));
        imap_delete($mbox, 1);
        imap_expunge($mbox);
    }

    public function testErrorLog(){
        $this->assertEquals(0, C_SF_ErrorLog::DEBUG_AllErrorsOccured());
        $testString1 = "TEST1";
        $result = C_SF_ErrorLog::WriteError($testString1);
        //check to make sure "WARN" is default severity value and is displayed correctly
        $this->assertTrue(!(strpos($result, "[WARN]")===false));
        $this->assertTrue(!(strpos($result, $testString1)===false));

        $testString2 = "TEST2";
        $result = C_SF_ErrorLog::WriteError($testString2, C_SF_ErrorLog::ERROR);
        $this->assertTrue(!(strpos($result, "[ERROR]")===false));
        $this->assertTrue(!(strpos($result, $testString2)===false));

        $this->assertEquals(1, C_SF_ErrorLog::DEBUG_ErrorsOccured(C_SF_ErrorLog::ERROR));
        $this->assertEquals(1, C_SF_ErrorLog::DEBUG_ErrorsOccured(C_SF_ErrorLog::WARN));
    }
}
?>
