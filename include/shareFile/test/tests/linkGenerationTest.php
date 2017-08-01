<?php
namespace shareFile;
use PHPUnit\Framework\TestCase;

require_once(INCLUDE_DIR.'shareFile/generateSFLink.php');
require_once(INCLUDE_DIR.'shareFile/handleEmails.php');
require_once(SF_SETTINGS_PATH);

class SFLinkTest extends TestCase{
    public function testDevProdDemarcation(){
        $token=GetShareFileToken();
        $prodFolder = GetRootDir($token, false);
        $devFolder = GetRootDir($token, true);

        $this->assertEquals($prodFolder->Id, SF_ROOT_FOLDER);
        $this->assertEquals($devFolder->Id, SF_ROOT_FOLDER_DEV);
    }
    public function testCreateDeleteFolder(){
        $token=GetShareFileToken();
        $prodFolder = GetRootDir($token, false);
        $devFolder = GetRootDir($token, true);

        //create den and prod folders, try to create again, then delete
        $folder = CreateFolder($token, $devFolder->Id, "TEST_FOLDER");
        $this->assertTrue($folder != null);
        //try to create again even though the folder exists, different code path taken
        $folder = CreateFolder($token, $devFolder->Id, "TEST_FOLDER");
        $this->assertTrue($folder != null);

        $this->assertTrue(DeleteItem($token, $folder->Id));

        $folder = CreateFolder($token, $prodFolder->Id, "TEST_FOLDER");
        $this->assertTrue($folder != null);
        $folder = CreateFolder($token, $prodFolder->Id, "TEST_FOLDER");
        $this->assertTrue($folder != null);

        $this->assertTrue(DeleteItem($token, $folder->Id));
    }

    public function testLinkGenerationFunctionWeb(){
        $_POST['id'] = \Ticket::$_testTicketID;

        //Trap 'echo' in output buffer
        ob_start();
        GenerateSFLinkFromTicketIDWeb();
        $result = ob_get_contents();
        ob_end_clean();

        //search for string to ensure that this is a link, check to make sure not false
        $this->assertTrue(!(strpos($result, "<a href=")===false) );
        //ensure link contains the share file site name
        $this->assertTrue(!(strpos($result, SF_HOSTNAME.".sharefile.com")===false) );
    }

    public function testLinkGenerationFunction(){
        $result = GenerateSFLinkFromTicketID(\Ticket::$_testTicketID);
        $this->assertTrue($result!=NULL);

        //search for string to ensure that this is a link, check to make sure not false
        $this->assertTrue(!(strpos($result, "<a href=")===false) );
        //ensure link contains the share file site name
        $this->assertTrue(!(strpos($result, SF_HOSTNAME.".sharefile.com")===false) );
    }
}
?>
