<?php
namespace shareFile;

require_once('prepend.php');

if(!defined('INCLUDE_DIR')) die('!');
require_once(INCLUDE_DIR.'class.draft.php');
require_once(INCLUDE_DIR.'class.ticket.php');

require_once(SF_SETTINGS_PATH);
require_once(INCLUDE_DIR.'shareFile/ErrorLog.php');

const SF_DOWNLOAD_ERROR_TOO_LARGE = 1;
const SF_DOWNLOAD_ERROR_MALWARE = 2;
const SF_DOWNLOAD_ERROR_OTHER = 3;

if(!defined ("SF_CERT_PATH")){
	define ("SF_CERT_PATH", INCLUDE_DIR.'/shareFile/certs/shareFileCert.pem');
}

//For finding the max download size defined in php.ini
function ParseSize($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}

//Global variable for cookies
$G_ini_post_max_size = ParseSize(ini_get('post_max_size'));
$G_DOWNLOAD_FAILED=false;

function CurlSetupBasic($uri, $header){
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $uri);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); //Seconds to wait before a connection is established in seconds
	curl_setopt($ch, CURLOPT_TIMEOUT, SF_CURL_TIMEOUT); //Timeout for the entire curl transfer

	//security, uses default certificate authorities; Guards against man in the middle attacks
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_CAINFO, SF_CERT_PATH);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	if($header!=NULL){curl_setopt($ch, CURLOPT_HTTPHEADER, $header);}

    return $ch;
}
function CurlSetupDelete($uri, $header, $headerFunction){
    $ch = CurlSetupBasic($uri, $header);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

    return $ch;
}

function CurlSetup($uri, $postFields, $header, $headerFunction, $post=TRUE){
	$ch = CurlSetupBasic($uri, $header);
	if($postFields!=NULL){curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);}
	if($post==TRUE){curl_setopt($ch, CURLOPT_POST, TRUE);}

	if($headerFunction != NULL){
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, $headerFunction);
	}

	return $ch;
}

function CurlExecute(&$ch){
	$curl_response = curl_exec ($ch);
	if (curl_errno($ch)) {
		$errorString = "<" . __FILE__ . " :: " . __FUNCTION__ . ">\r\n";
		$errorString = $errorString . "Curl error: " . curl_error($ch);
		C_SF_ErrorLog::WriteError($errorString);
	}
	return $curl_response;
}

class C_SF_TOKEN{
		const FILEPATH = INCLUDE_DIR."shareFile\\files\\sfLoginToken.txt";

		//These members exist in the SF_TOKEN
		public $access_token;
		public $refresh_token;
		public $token_type;
		public $apicp;
		public $appcp;
		public $subdomain;
		public $expires_in;

		//This was added for the sake of knowing when to use the refresh_token
		public $timeCreated;

        //Handle to file where Token results are stored
		private $fHandle;

		public function __construct() {
			$this->ShareFileAuthenticate();
		}

		private function ShareFileAuthenticate() {
			$uri = "https://".SF_HOSTNAME.".sharefile.com/oauth/token?";
			$body_data = array( "grant_type"	=> "password",
								"client_id"		=> SF_CLIENT_ID,
								"client_secret"	=> SF_CLIENT_SECRET,
								"username"		=> SF_USERNAME,
								"password"		=> SF_PASSWORD);
			$data = http_build_query($body_data);

			$ch = CurlSetup($uri, $data, array('Content-Type:application/x-www-form-urlencoded'), NULL);
			$curl_response = CurlExecute($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close ($ch);

			$token = NULL;
			$rlToken = NULL;
			if ($http_code == 200) {
				$token = json_decode($curl_response);
				$this->timeCreated = time();
				$this->CopyToken($token);
			}
		}

		/*private function Refresh(){
			$uri = "https://".SF_HOSTNAME.".sharefile.com/oauth/token?";
			$body_data = array( "grant_type"	=> "refresh_token",
								"refresh_token"	=> $this->refresh_token,
								"client_id"		=> SF_CLIENT_ID,
								"client_secret"	=> SF_CLIENT_SECRET
								);
			$data = http_build_query($body_data);

			$ch = CurlSetup($uri, $data, array('Content-Type: application/x-www-form-urlencoded'), NULL);
			$curl_response = CurlExecute($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close ($ch);

			$token = NULL;
			$rlToken = NULL;
			if ($http_code == 200) {
				$token = json_decode($curl_response);
				$this->timeCreated = time();
				$this->CopyToken($token);
				return true;
			}
			if($http_code == 401) {
				return false;
			}
		}

        private function ShouldRefresh(){
        	$expireTime = $this->timeCreated + $this->expires_in;
        	$currentTime = time();

        	return ($expireTime <= currentTime);
        }
        */


        private function CopyToken($token){
        	$this->access_token    = $token->access_token;
        	$this->refresh_token   = $token->refresh_token;
        	$this->token_type 	   = $token->token_type;
        	$this->apicp 		   = $token->apicp;
        	$this->appcp 		   = $token->appcp;
        	$this->subdomain 	   = $token->subdomain;
        	$this->expires_in 	   = $token->expires_in;
        }
}
function GetHostnameFromToken($token) {
    return $token->subdomain.".sf-api.com";
}
function GetAuthorizationHeaderFromToken($token) {
    return array("Authorization: Bearer ".$token->access_token);
}

function DeleteItem($token, $itemID){
    //https://account.sf-api.com/sf/v3/Items(id)?singleversion=false&forceSync=false
    $uri = "https://" . SF_HOSTNAME . ".sf-api.com/sf/v3/Items(".$itemID.")?singleversion=false&forceSync=true";
    $headers = GetAuthorizationHeaderFromToken($token);

    $ch 			= CurlSetupDelete($uri, $headers, NULL);
	$curl_response 	= CurlExecute($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $success=false;
    echo $http_code;
    if (($http_code == 200)or($http_code == 204)) {
        $success = true;
    }
    else{
        C_SF_ErrorLog::WriteError("Couldn't delete item\r\n    URI IS: ".$uri);
    }

	curl_close ($ch);
    return $success;
}

function GetRootDir($token, $IN_DEV, $get_children=FALSE) {
	//this returns the shared folders \ osticket item
    $folderID = SF_ROOT_FOLDER;
    if($IN_DEV == true){
        $folderID = SF_ROOT_FOLDER_DEV;
    }

    $uri = "https://" . GetHostnameFromToken($token) . "/sf/v3/Items(" . $folderID . ")?includeDeleted=false";

    $headers = GetAuthorizationHeaderFromToken($token);

    $ch 			= CurlSetup($uri, NULL, $headers, NULL, false);
    $curl_response  = CurlExecute($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close ($ch);

    $root = NULL;
    if ($http_code == 200) {
      $root = json_decode($curl_response);
    }
    else{
        C_SF_ErrorLog::WriteError("Couldn't get root dir. \r\n    URI IS: ".$uri."\r\n    IN_DEV is ".$IN_DEV);
    }

	return $root;
}

function GetChildren($token, $parentID){
	$uri = "https://" . SF_HOSTNAME . ".sf-api.com/sf/v3/Items(".$parentID.")/Children?includeDeleted=false";
	$headers = GetAuthorizationHeaderFromToken($token);

	$ch 			= CurlSetup($uri, NULL, $headers, NULL, false);
	$curl_response 	= CurlExecute($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close ($ch);


	$children = NULL;
    if ($http_code == 200) {
        $children = json_decode($curl_response);
    }
    else{
        C_SF_ErrorLog::WriteError("Couldn't get Children \r\n    URI IS: ".$uri);
    }

	return $children;
}
function CreateFolder($token, $parentID, $name){
	if(($name==NULL)or($name=="")){return NULL;}
    $name = strval($name);

	//check to see if folder exists first, if so, then return it
	$children = GetChildren($token, $parentID);
	if($children != NULL){
		foreach ($children->value as $child){
			if($child->Name==$name){
				return $child;
			}
		}
	}

	//will not overwrite folders of the same name
	$uri = "https://" . SF_HOSTNAME . ".sf-api.com/sf/v3/Items(".$parentID.")/Folder?overwrite=false&passthrough=false";
	$body_data = array("Name"=>$name,"Description"=>"OSTicket Ticket#");
    $data = http_build_query($body_data);
	$headers = GetAuthorizationHeaderFromToken($token);

	$ch 			= CurlSetup($uri, $data, $headers, NULL);
	$curl_response 	= CurlExecute($ch);
    $http_code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close ($ch);

	$folder = NULL;
    if ($http_code == 200) {
        $folder = json_decode($curl_response);
    }
    else{
        C_SF_ErrorLog::WriteError("Couldn't create folder
            \r\n    URI IS: ".$uri.
            "\r\n    FolderName is ".$name.
            "\r\n    VarDump of response: ".var_dump($curl_response));
    }

	return $folder;
}

function CreateShareRequest($token, $accountName, $parentFolder){
	$uri = "https://".$accountName.".sf-api.com/sf/v3/Shares?notify=true";
    $currentTimeInSeconds = time();
    //x days each consist of 24 hours each consist of 60 mins each consist of 60 secs
    $expirationDate = $currentTimeInSeconds + (SF_EXPIRATION_DATE_DAYS * 24 * 60 * 60);
    $formattedDate = date('Y-m-d', $expirationDate);

	$body_data = array(	"notify"			=>"true",
						"NotifyOnUpload"	=>"true",
						"ShareType"			=>"Request",
						"Parent"			=>array ("Id"=>$parentFolder->Id),
						"Title"				=>"OSTicket File Upload",
                        "ExpirationDate"    =>$formattedDate,
						"RequireLogin"		=>"false",
						"RequireUserInfo"	=>"false"
						);
    $data = http_build_query($body_data);
	$headers = GetAuthorizationHeaderFromToken($token);

	$ch 			= CurlSetup($uri, $data, $headers, NULL);
	$curl_response 	= CurlExecute($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close ($ch);

	$share = NULL;
    if ($http_code == 200) {
        $share = json_decode($curl_response);
    }

	return $share;
}

function GetShare($token, $id){
	//https://account.sf-api.com/sf/v3/Shares(id)
	$uri = "https://" . SF_HOSTNAME . ".sf-api.com/sf/v3/Shares(".$id.")";

	$headers = GetAuthorizationHeaderFromToken($token);

	$ch 			= CurlSetup($uri, NULL, $headers, NULL, false);
	$curl_response 	= CurlExecute($ch);
    $http_code 		= curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close ($ch);

	$share = NULL;
    if ($http_code == 200) {
        $share = json_decode($curl_response);
    }

	return $share;
}

function CurlProgressFunction($resource, $downloadSize = 0, $downloaded = 0, $uploadSize = 0, $uploaded = 0){
    // If $Downloaded exceeds maximum, returning non-0 breaks the connection!
	global $G_ini_post_max_size, $G_DOWNLOAD_FAILED;
	/*
	$debugString = "";
	$debugString = $debugString . "[VARS]";
	$debugString = $debugString . "|DL SIZE: ".$downloadSize."\r\n";
	$debugString = $debugString . "|Dwnlded: ".$downloaded."\r\n";
	$debugString = $debugString . "|UL SIZE: ".$uploadSize."\r\n";
	$debugString = $debugString . "|UPLODED: ".$uploaded."\r\n";

	$debugString = $debugString . "[MAX SIZE]";
	$debugString = $debugString . "|-B: ".$G_ini_post_max_size."\r\n";
	$debugString = $debugString . "|KB: ".($G_ini_post_max_size/1024)."\r\n";
	$debugString = $debugString . "|MB: ".(($G_ini_post_max_size/1024)/1024)."\r\n";

	C_SF_ErrorLog::WriteError($debugString);*/

	if	($downloaded > $G_ini_post_max_size){
		$G_DOWNLOAD_FAILED=true;
		C_SF_ErrorLog::WriteError("<" . __FILE__ . " :: " . __FUNCTION__ . "> Size of file exceeds defined maximum in php.ini - post_max_size = ".$G_ini_post_max_size);
		return 1;
	}
    return 0;
}

//returns array; a[0]=fileData, a[1]=fileName
function DownloadFilesFromShare($token, $shareID){
	global $G_DOWNLOAD_FAILED, $G_ini_post_max_size;

	//https://account.sf-api.com/sf/v3/Shares(shareid)/Download
	$uri = "https://" . SF_HOSTNAME . ".sf-api.com/sf/v3/Shares(".$shareID.")/Download";
	$headers = GetAuthorizationHeaderFromToken($token);

	$ch = CurlSetup($uri, NULL, $headers, NULL, false);

	//follow redirects
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	//Get Header
	curl_setopt($ch, CURLOPT_HEADER, TRUE);
	// We need progress updates to break the connection mid-way
	curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);		//size of buffer to download
	curl_setopt($ch, CURLOPT_NOPROGRESS, false);	//enable progress
	curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'shareFile\CurlProgressFunction');

	$curl_response 	= CurlExecute($ch);

	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$curl_header = substr($curl_response, 0, $header_size);
	$body = substr($curl_response, $header_size);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error_number = curl_errno($ch);
    $curl_error = curl_error($ch);

	$headers = explode("\n", $curl_header);
	$filename="";
	foreach($headers as $header) {
		if (stripos($header, 'Content-Disposition:') !== false) {
			//found the header containing the filename, now parse the string
			//"Content-Disposition: attachment;filename="<fn>";

			$substring="filename=";
			$filenameStart= strpos($header, $substring)+10;
			$filenameEnd= strpos($header, '"', $filenameStart);
			$filename=substr($header, $filenameStart, ($filenameEnd - $filenameStart));
		}
	}

	curl_close ($ch);

	$file = $body;
	//echo var_dump($curl_header);
	//file_put_contents("C:\\inetpub\\wwwroot\\osticket\\Ryan\\temp\\test2.jpg", $file);
	$returnError=0;
	if ($curl_error_number) {

		$errorString = "<" . __FILE__ . " :: " . __FUNCTION__ . ">";

		$errorString = $errorString . "\r\n   File couldn't be downloaded from share with ID: " . $shareID;
		//what is 28?
		if($curl_error_number==42){
			$errorString = $errorString . "\r\n   The file is too large";
			$returnError=SF_DOWNLOAD_ERROR_TOO_LARGE;
			$G_DOWNLOAD_FAILED=true;
		}
		else if($curl_error_number==56){
			$errorString = $errorString . "\r\n   The file was recognized as malware";
			$G_DOWNLOAD_FAILED=true;
			$returnError=SF_DOWNLOAD_ERROR_MALWARE;
		}
		else if($curl_error_number==28){
			$errorString = $errorString . "\r\n   The file had an unknown problem and timed out";
			$G_DOWNLOAD_FAILED=true;
			$returnError=SF_DOWNLOAD_ERROR_OTHER;
		}
		$errorString = $errorString . "\r\n ErrorNO: ". $curl_error_number;
		C_SF_ErrorLog::WriteError($errorString);
	}

	if($G_DOWNLOAD_FAILED==true){
		$G_DOWNLOAD_FAILED=false;
		return array(null, $returnError);
	}
	return array($file, $filename);
}

function GetShareFileToken(){
	//global $G_SF_hostname, $G_SF_username, $G_SF_password, $G_SF_client_id, $G_SF_client_secret;
	//$token = ShareFileAuthenticate($G_SF_hostname, $G_SF_client_id, $G_SF_client_secret, $G_SF_username, $G_SF_password);
	$token = new C_SF_TOKEN();

	return $token;
}
?>
