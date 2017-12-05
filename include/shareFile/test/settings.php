<?php
namespace shareFile;

/**************************************************************
***************TESTING SETTINGS********************************
**************************************************************/

//server Variables
    //Is this instance in DEV or PROD?
    const SF_IN_DEV = false;

    //Server address
    const SERVER_ADDRESS= "http://kit/osticket";


//Variables for the email account that recieves responses from ShareFile
	//Credentials
        const EMAIL_USERNAME			 = "SharefileIncomingDEV";
        const EMAIL_ADDRESS              = "shareFileIncomingDEV@keystonecollects.com";
        const EMAIL_PASSWORD  		     = "[H4mmer&Na1L]";

	//Server Info
	   const EMAIL_HOST   				 = "kexca1";
	   const EMAIL_PORT   				 = 110;
	   const EMAIL_FOLDER   			 = "Inbox";

    //Email of admin who recieves 'FATAL' alerts
        const ADMINISTRATOR_EMAIL = "shareFileIncomingDEV@keystonecollects.com";
        const EMAIL_FATAL_TO_ADMIN = true;

//Variables for emails sent from email account
    //basic message subject sent when sending an email regarding an uploaded file
    const EMAIL_SEND_SUBJECT_SUCCESS = "Kit File Uploaded";

    //basic message subject sent when sending an email regarding an uploaded file Failed
    const EMAIL_SEND_SUBJECT_FAIL    = "Kil File Upload FAILED";

//Sharefile website variables
	//ShareFile account Info
	   const SF_HOSTNAME 				= "keystonecollects";
	   const SF_USERNAME 				= "ShareFileIncoming@keystonecollects.com";
	   const SF_PASSWORD 				= "[H4mmer&Na1L]";

    //Authy
        const SF_CLIENT_ID 			= "iCA4s8GVDzsRjW93PoyHuMH0DRF0uwaM";
        const SF_CLIENT_SECRET 		= "Sc1Di1fj8Jti63yxs5T8abNSdNEGgbAfFQ4YIj7COpAo0Ot9";

        //The folder id in ShareFile that will contain the OSTicket subfolders which store the uploaded data along with it's actual name
    	const SF_ROOT_FOLDER 				= "fo899e70-e757-405b-b8f8-0797f824a1bb";
        //The actual folder name is included in the auto-generated ShareFile emails; It's used to find the ticket ID when an email is being parse
        //make sure that the name is fairly unique and isn't similar to common HTML/CSS tags or values
    	const SF_ROOT_FOLDER_NAME			= "OSTicket_TESTING";

    	const SF_ROOT_FOLDER_DEV 			= "fo5f152e-ef4e-46e5-abeb-ebb16118897a";
    	const SF_ROOT_FOLDER_NAME_DEV		= "OSTicket_DEV_TESTING";
    //how many days to wait before a link expires
    const SF_EXPIRATION_DATE_DAYS       = 15;
	
    //Amount of time curl waits before aborting the download
    const SF_CURL_TIMEOUT = 60 * 3; //3 minutes

//OSTicket Variables
	const OST_USERNAME	                = 'webhelpdesk';
 	const OST_PASSWORD	                = 'Passw0rd';

//Logging Variables -- Ensure that all of that paths specified have the correct permissions to allow PHP to access, change, and create new files
	const ERROR_LOG_PATH 			= __DIR__."\\logs";

//Path where failed emails are saved to (typically inside of the errorlog folder)
	const EMAIL_FAIL_EXPORT_PATH   	=  __DIR__."\\logs\\EmailsFailed";
//boolean value that decides whether failed emails should be saved to file
	const EMAIL_FAIL_EXPORT	        = true;
	
	
	const SF_LOCK_FILE_PATH = __DIR__."\\shareFileRunning_LockFile.txt";

?>
