<?php
require_once('sharefile/prepend.php');
require_once(SF_SETTINGS_PATH);

class User{
    private $_email;
    private $_id;

    public function __construct($email, $id = 31337){
        $this->_email = $email;
        $this->_id = $id;
    }

    public function getEmail(){
        return $this->_email;
    }
    public function getId(){
        return $this->_id;
    }
}

class StaffAuthenticationBackend{
    public static function process($username, $pw, $errors){
        if( ($username == sharefile\OST_USERNAME) && ($pw == sharefile\OST_PASSWORD) ){
            return new User(shareFile\EMAIL_ADDRESS);
        }
    }
}

class Ticket{
    private $_ticketID;
    private $_assignee;
    public $_replies;
    public static $_testTicket;
    public static $_testTicketID = 31337;

    const USER_EMAIL = "ralloyd@keystonecollects.com";

    public static function staticInit(){
        self::$_testTicket = new Ticket(self::$_testTicketID);
    }
    public static function Lookup($ticketID){
        if($ticketID == self::$_testTicketID){
            return self::$_testTicket;
        }
        return null;
    }

    public function __construct ($ticketID){
        $this->_ticketID = $ticketID;
        $this->_assignee = new User(shareFile\EMAIL_ADDRESS);
        $this->_replies = array();
    }

    public function getAssignee(){
        return $this->_assignee;
    }

    public function getOwner(){
        return array();
    }

    public function getEmail(){
        return Ticket::USER_EMAIL;
    }

    public function postReply($replyVars, $errors){
        array_push($this->_replies, $replyVars);
    }
}
Ticket::staticInit();
?>
