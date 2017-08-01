<?php
namespace shareFile;

require_once('prepend.php');
require_once(SF_SETTINGS_PATH);
require_once(INCLUDE_DIR.'shareFile/PHPMailer/PHPMailerAutoload.php');

class C_SF_ErrorLog{

    const FATAL = 0;
    const ERROR = 1;
    const WARN = 2;
    const INFO = 3;
    const DEBUG = 4;
    const TRACE = 5;

    const SEVERITY_TEXT = array(
        0 => "FATAL",
        1 => "ERROR",
        2 => "WARN",
        3 => "INFO",
        4 => "DEBUG",
        5 => "TRACE"
    );

    private static function AlertAdmin($recipient, $subject, $message, $retryAttempts=6){
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

    public static function GetFilePath(){
        $fileName = date("Y_M_d");
        $filePath = ERROR_LOG_PATH."\\".$fileName.".txt";
        return $filePath;
    }

	public static function WriteError($message, $severity = 2){
        $filePath = self::GetFilePath();
        $data="";
        if(file_exists($filePath)){
            $data = file_get_contents($filePath);
        }
		$currentTime =  date("[h:i A]");
        $newError = $currentTime." [".
                    self::SEVERITY_TEXT[$severity]
                    ."] ".$message."\r\n";

        $data =   $data.$newError;
		file_put_contents($filePath, $data);

        if($severity == self::FATAL && EMAIL_FATAL_TO_ADMIN){
            self::AlertAdmin(
                ADMINISTRATOR_EMAIL,
                "ShareFile_OSTicket_Integration - FATAL error occured!",

                $currentTime." [".
                    self::SEVERITY_TEXT[$severity]
                    ."] ".$message."\r\n"
                );
        }
        return $newError;
	}

    public static function ClearErrorFile(){
        file_put_contents(self::GetFilePath(), "");
    }

    public static function DEBUG_ErrorsOccured($severity){
        $contents = file_get_contents(self::GetFilePath());
        return substr_count($contents, '['.self::SEVERITY_TEXT[$severity].']');
    }

    public static function DEBUG_AllErrorsOccured(){
        $errorCount = 0;
        for( $i=0; $i<count(self::SEVERITY_TEXT); $i++ ) {
            $errorCount = $errorCount + self::DEBUG_ErrorsOccured($i);
        }
        return $errorCount;
    }
}

?>
