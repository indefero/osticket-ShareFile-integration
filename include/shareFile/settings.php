<?php
namespace shareFile;

//server Variables
    //Is this instance in DEV or PROD?
    const SF_IN_DEV = false;

    //Server address
    const SERVER_ADDRESS= "http://xxx/osticket";


//Variables for the email account that recieves responses from ShareFile
	//Credentials
	   const EMAIL_USERNAME			     = "email";
       const EMAIL_ADDRESS               = "email@account.com";
       const EMAIL_PASSWORD  		     = "pw";

	//Server Info
	   const EMAIL_HOST   				 = "mb1";
	   const EMAIL_PORT   				 = 110;
	   const EMAIL_FOLDER   			 = "Inbox";

    //Email of admin who recieves 'FATAL' alerts and notifications of file uploads to unassigned tickets
       const ADMINISTRATOR_EMAIL = "admin@account.com";
       const EMAIL_FATAL_TO_ADMIN = true;

//Variables for emails sent from email account
    //basic message sent when sending an email regarding an uploaded file
    const EMAIL_SEND_MESSAGE_SUCCESS_TAG = "[FILE UPLOAD SUCCESS]";

    //basic message sent when sending an email regarding an uploaded file Failed
    const EMAIL_SEND_MESSAGE_FAIL_TAG    = "[FILE UPLOAD FAILURE]";

//Sharefile website variables
	//ShareFile account Info
	   const SF_HOSTNAME 				= "account";
	   const SF_USERNAME 				= "shareFile@account.com";
	   const SF_PASSWORD 				= "pw";

    //Authy
        const SF_CLIENT_ID 			= "id";
        const SF_CLIENT_SECRET 		= "secret";

	//The folder id in ShareFile that will contain the OSTicket subfolders which store the uploaded data along with it's actual name
	const SF_ROOT_FOLDER 				= "root-folder-id-lvjn3ik43jg";
	//The actual folder name is included in the auto-generated ShareFile emails; It's used to find the ticket ID when an email is being parse
    //make sure that the name is fairly unique and isn't similar to common HTML/CSS tags or values
	const SF_ROOT_FOLDER_NAME			= "OSTicket";

	const SF_ROOT_FOLDER_DEV 			= "root-folder-id-dev-aonh4nboi54";
	const SF_ROOT_FOLDER_NAME_DEV		= "OSTicket_DEV";
    //how many days to wait before a link expires
    const SF_EXPIRATION_DATE_DAYS       = 7;

    //Amount of time curl waits before aborting the download
    const SF_CURL_TIMEOUT = 60 * 3; //3 minutes

//OSTicket Variables
	const OST_USERNAME	                = 'OSTicketUsername';
 	const OST_PASSWORD	                = 'OSTicketPW';

//Logging Variables -- Ensure that all of that paths specified have the correct permissions to allow PHP to access, change, and create new files
	const ERROR_LOG_PATH 			= "C:\\inetpub\\wwwroot\\osticket\\include\\shareFile\\logs";

//Path where failed emails are saved to (typically inside of the errorlog folder)
	const EMAIL_FAIL_EXPORT_PATH   	= "C:\\inetpub\\wwwroot\\osticket\\include\\shareFile\\logs\\EmailsFailed";
//boolean value that decides whether failed emails should be saved to file
	const EMAIL_FAIL_EXPORT	        = true;

?>
