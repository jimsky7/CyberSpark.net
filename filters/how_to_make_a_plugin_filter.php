<?php
/*


***************************************************************************************
***************************************************************************************
CyberSpark.net monitoring-alerting system

***************************************************************************************
CyberSpark 'big picture' notes:

The CyberSpark service is started (by 'rc' or by 'service') and runs as a daemon from cyberspark.php until it quits or is terminated. It starts up child processes (AKA
'daemons' or 'threads' in some of our other documentation) by launching cybersparkd.php, which each read their own 'properties' files to determine how to configure themselves and which URLs to look at or spider. This process cyberspark.php itself keeps running in order to monitor the child processes and to restart them if they fail (or are terminated by a sysadmin). The system is designed to send notifications by email, write entries to stdout, and can write log files.

The CyberSpark 'daemons' (cybersparkd.php) are fired up by the parent daemon, and they keep running until terminated by an appropriate SIGnal, by the server being turned off, or by a KILL. In the case of graceful terminations, they save their data and write log entries as they shut down.

Each daemon or thread runs periodically (timing comes from its properties file), then sleeps for a short time. When active, it reads its properties file, setting options and then finding URLs to sniff. For each "url=..." line it is given a URL, various 'filter' names, and email addresses to notify. The 'filter' names tell the daemon what kind of analysis to perform for each particular URL. The filter names correspond to PHP files in the /filters directory. Upon startup, each daemon examines the 'filters' directory filenames, and attempts to initialize each filter.

The file you are reading describes how a 'filter' file is structured.

***************************************************************************************
To make a new plugin "filter":

Filters are "plugin" in the sense that you can create a new PHP filter file and drop it into the /filters directory without upsetting the daemons. You can then alter the "url=..." lines in the properties file, restart an individual daemon, and the daemon can immediately use the new filter.

Using the file you are reading as a sample, you can modify to create your own filter.

Create a function with the same name as the file (but not the ".php" extension).
//   This function will be called once when the main script has read parameters
//   but has not yet performed a scan.  This function must register a callback
//   which will become its "filter."
//   The main function may install callbacks for three events this way:
//     registerFilterHook($name, 'init', 'callback', $rank)  	// called once when main script initialized
//     registerFilterHook($name, 'scan', 'callback', $rank)  	// called for each URL scanned
//     registerFilterHook($name, 'destroy', 'callback', $rank)	// called before main script shuts down
// The callback functions get 3 args when called:
//  $content
//    The content of the URL being spidered.  Could be null if the callback
//      is for an 'init' or 'destroy' event
//  $args
//    which is an asociative array containing various arguments/parameters from
//    the main script.
//    ['oncedaily'] if isset() then perform tasks that should only be performed
//      infrequently (only once or twice a day)
//    ['code'] the result code of the HTTP request - might indicate failure of an
//      GET (such as a 404 result) in which case $content will not be set.
//    ['url'] the URL that is being checked
//    ['conditions'] any conditions for this URL from the properties file
//	  ['verbose'] if caller wants lots of feedback rather than less
//  $privateStore
//    Private persistent storage maintained on behalf of the filter and preserved between
//      executions of the main script (AKA "the daemon").  This is persistent storage for 
//      this plugin. Each daemon has its own storage, but filters must use the associative
//      index properly so as not to interfere with each other.
//    The callback returns three results - the first is a message to be displayed or 
//      included in an email, the second indicates the 'type' of alert generated,
//      and the third is an associative array which will be preserved and
//      passed in again as the argument "$store" ... the code must respect this store
//      and index it appropriately so as not to mess with data belonging to other
//      plugin filters.

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "include/echolog.inc";

*/
?>
<?php 

// CyberSpark system variables, definitions, declarations
include_once "cyberspark.config.php";
include_once "include/echolog.inc";


//// how_to_make_a_plugin_filterScan()
//// This function is called when a URL is being scanned and when 'length' has been
//// specified as a filter for the URL (on the line in the properties file).
//// $content contains the result of the HTTP GET on the URL.
//// $args holds arguments/parameters/properties from the daemon
//// $privateStore is an associative array of data which contains, if indexed properly,
////   persistent data related to the URL being scanned.
function how_to_make_a_plugin_filterScan($content, $args, $privateStore) {
	$filterName = "how_to_make_a_plugin_filter";
	$result   = "OK";						// default result
	$url = $args['url'];					// the actual URL, not its contents


	//// Do everything you want below here
	$message = "Build the text for your email alert here.";

	//// Example of using private data...
	//   Get $privateStore properly, based on filter name and URL being checked
	//   You can define any number of last-position values ... don't use 'sampledata' use
	//   something meaningful for your filter.
	if (isset($privateStore[$filterName][$url]['SAMPLEDATA'])) {
		// Get value from previous run
		$sampleData = $privateStore[$filterName][$url]['SAMPLEDATA'];
	}
	else {
		// Set up value because this URL has not been seen before
		$sampleData = '';
		$privateStore[$filterName][$url]['SAMPLEDATA'] = $sampleData;
	}

	//// If something changed, for example, return status
	if (strcasecmp($sampleData, 'foo') != 0) {
		$result = "Changed";	// This causes status to be reported in email
		$newValue = 'foo';		// you'd normally calculate something for the new value
		$message = "The value of sampleData has changed from $sampleData to $newValue.";
	}
	
	//// Must return three things always
	//   Note that $message is created here and if everything's OK, it doesn't
	//     actually have to contain anything.
	//   $result may be "OK" "Changed" or "Critical"
	//   $privateStore returns to the daemon and persists between invocations of filter
	return array($message, $result, $privateStore);
	
}

//// how_to_make_a_plugin_filterInit()
//// This function is called by the daemon once when it is first loaded.
//// It returns a message, but doesn't touch the private date (in $privateStore).
function how_to_make_a_plugin_filterInit($content, $args, $privateStore) {
	$filterName = "how_to_make_a_plugin_filter";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the 
	//   main script, and  available only for use by this plugin filter.
	$message = "[$filterName] Just reporting in. URL is " . $args['url'];
	$result   = "OK";

	return array($message, $result, $privateStore);
	
}

//// how_to_make_a_plugin_filterDestroy()
//// This function is called as the daemon is shutting down. We get an instance of
//// the daemon's "private" database in $privateStore, which we simply pass back. We also
//// pass back a message saying that this filter has properly shut down.
function how_to_make_a_plugin_filterDestroy($content, $args, $privateStore) {
	$filterName = "length";
	// $content is the URL being checked right now
	// $args are arguments/parameters/properties from the main PHP script
	// $privateStore is my own private and persistent store, maintained by the main script, and
	//   available only for use by this plugin filter.
	$message = "[$filterName] Shut down.";
	$result   = "OK";
	return array($message, $result, $privateStore);
	
}

//// how_to_make_a_plugin_filter()
//// This function is called to "instantiate" the filter. Actually all we do here is
////   register three hooks with the daemon so it knows who we are and how to notify us
////   that it is starting up, shutting down, or wants us to filter a URL.
//// (Note that the name "how_to_make_a_plugin_filter" matches the filename
////    "how_to_make_a_plugin_filter.php" -- this is required.)
function how_to_make_a_plugin_filter($args) {
	$filterName = "how_to_make_a_plugin_filter";
 	if (!registerFilterHook($filterName, 'scan', $filterName.'Scan', 10)) {
		echo "The filter '$filterName' was unable to add a 'Scan' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'init', $filterName.'Init', 10)) {
		echo "The filter '$filterName' was unable to add an 'Init' hook. \n";	
		return false;
	}
	if (!registerFilterHook($filterName, 'destroy', $filterName.'Destroy', 10)) {
		echo "The filter '$filterName' was unable to add a 'Destroy' hook. \n";	
		return false;
	}
	return true;
}

?>