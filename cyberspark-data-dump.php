#!/usr/bin/php -q
<?php
/****
	Read and display cyberspark data for one thread.
	Run from command line (substitute your ID for "CS0-0" in command:
		cd /usr/local/cyberspark
		php ./cyberspark-data-dump.php --id CS0-0
	The script will dump/display/echo all CS and filter data for the agent ID.
****/

///////////////////////////////////////////////////////////////////////////////////
// Local (your installation) variables, definitions, declarations
include_once "cyberspark.config.php";
// CyberSpark system variables, definitions, declarations
include_once "cyberspark.sysdefs.php";

declare(ticks = 1);					// allows shutdown functions to work
include_once "include/shutdown.php";
include_once "include/startup.php";
include_once "include/functions.php";

///////////////////////////////////////////////////////////////////////////////////
// 
$ID			= INSTANCE_ID;			// this "sniffer" ID (like "CS9-0")
$identity	= DEFAULT_IDENTITY;		// from config
$userAgent  = DEFAULT_USERAGENT;	// from config
$maxDataSize= MAX_DATA_SIZE;		// maximum serialized data file size = 10MB
$dataDir	= DATA_DIR;				// where data will live
$propsDir	= PROPS_DIR;			// where properties files live
$filtersDir	= FILTERS_DIR;			// where the scanning filters live
$logDir		= LOG_DIR;				// where the csv logs will be written
$propsFileName= "";					// exact name of the properties file
$storeFileName=	DATA_EXT;			// filename for the data store
$logFileName  = LOG_EXT;			// filename for log
$logHandle	= null;					// handle for log file (used as a global elsewhere)
$path		= APP_PATH;				// path to the executing script (WITHOUT script file name)
$scriptName	= "";					// this script's name, picked up from $argv[0]
$isDaemon   = false;				// true if running in 'daemon' mode
$running	= false;
$time		= DEFAULT_LOOP_TIME;	// default minutes between loops
$notify		= DEFAULT_NOTIFY_HOUR;	// default "midnight hour" is "23	"
$messageSeed= "";					// initial portion of message
$host		= "";					// human-readable host name
$loop		= 0;
$crashes	= 0;
$properties = array();					// properties as returned by getProperties()
$timeout    = DEFAULT_SOCKET_TIMEOUT;	// socket timeout in seconds for HTTP GET (from config)
	
// Data store for "private" information that filters want to retain between runs.
// The main CyberSpark system can also persist data here.
//   ...as    $store['cyberspark']['key'] = value;
$store		= null;					// persistent storage for filters

// Google Safe Browsing interface parameters
$gsbServer = GSB_SERVER;			// from config

///////////////////////////////////////////////////////////////////////////////////
// Filter-related stuff
// Filters are *.php files within the filters/ subdirectory that contain code to
//   be applied to the URLs we examine.  See filters/basic.php for internal
//   documentation about how to write and "call" filters.
include "include/classdefs.php";
$filters = array();					// this array is numerically ordered/indexed
									// and contains all filter information.
	
///////////////////////////////////////////////////////////////////////////////////
// Email-related 
// All SMTP parameters are initially from the config, although the properties
//   file can supplant them.
// You can use gmail or you can use any other server you wish to define
//   that uses regular SMTP or secure (SSL) SMTP on any port you choose.
$smtpServer = SMTP_SERVER;					// default from config (properties can override)
$user		= SMTP_USER;					// default from config (properties can override)
$pass		= SMTP_PASSWORD;				// default from config (properties can override)
$from		= EMAIL_FROM;					// default from config (properties can override)
$smtpPort	= SMTP_PORT;					// default from config (properties can override)
$replyTo	= EMAIL_REPLYTO;				// default from config
$to			= EMAIL_TO;						// default from config (properties can override)
$abuseTo	= EMAIL_ABUSETO;				// default from config
	
///////////////////////////////////////////////////////////////////////////////////
// include supporting code
include_once "include/store.php";
include_once "include/args.php";
include_once "include/properties.php";
include_once "include/mail.php";
include_once "include/http.php";
include_once "include/scan.php";
include_once "include/filters.php";
include_once "include/echolog.php";

///////////////////////////////////////////////////////////////////////////////////
// initialization
// Get the filesystem path to this file (only the PATH) including an ending "/"
// NOTE: This overrides the APP_PATH from the config file, which will be unused.
$path 		 = substr(__FILE__,0,strrpos(__FILE__,'/',0)+1);	// including the last "/"
$dataDir 	 = $path . $dataDir;
$propsDir	 = $path . $propsDir;
$filtersDir  = $path . $filtersDir;
$logDir 	 = $path . $logDir;
$scriptName	 = $argv[0];

///////////////////////////////////////////////////////////////////////////////////
// Parse the command-line arguments
list($isDaemon, $ID) = getArgs($argv);
$propsFileName		 = $propsDir . $ID . PROPS_EXT;
$storeFileName		 = $dataDir  . $ID . DATA_EXT;
$logFileName		 = $logDir   . $ID . LOG_EXT;
$pidFileName		 = $path . $ID . PID_EXT;
$heartbeatFileName	 = $path . $ID . HEARTBEAT_EXT;
$urlFileName         = $path . $ID . URL_EXT;
$running = true;
$pipes = null;
$logTransportProcess = null;

echo "»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»\n";
echo "Dump of data for $ID\nFile is $dataDir$ID.db";

$contents = file_get_contents("$dataDir$ID.db");

$store = unserialize($contents);

echo "\n";
echo "»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»\n";
print_r($store);

echo "\n";
echo "»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»»\n";



	exit;


///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 
///////////////////////////////// 


?>