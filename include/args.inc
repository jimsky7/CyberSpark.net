<?php
	/**
		CyberSpark.net monitoring-alerting system
		handle command-line arguments
		and set a few globals
	*/


///////////////////////////////// 
function getArgs($argv) {
	global $isDaemon;
	global $configTest;
//	global $propsFileName;
	global $ID;
	
	$i = 0;
	while ($i < count($argv)) {
		if($argv[$i] == '--help' || $argv[$i] == 'help' || count($argv) == 1) {
			giveHelp();
		}
		elseif (strcasecmp($argv[$i], '--daemon') == 0) {
			$isDaemon = true;
		}
		elseif (strcasecmp($argv[$i], '--configtest') == 0) {
			$configTest = true;
		}
		elseif (strcasecmp($argv[$i], '--id') == 0) {
			if (($i+1) < count($argv)) {
				$ID = $argv[++$i];
			}
		}
		$i++;
	}
}

///////////////////////////////// 
function giveHelp() {
	// '/help'  - - - - - - - - - - - -
	echo 'CyberSpark Monitoring-Alerting system
cyberspark.php --arg --arg --arg
--help
    Print this help message and quit.
--id
    The identifier for this thread, like "CS8-0" // among other 
    things this determines the properties file used.
--daemon 
    Run as a daemon (keep executing until killed)
    If this parameter is not present, just run one loop.
--configtest
    Read properties file and test to see if the configuration 
    will work.  Then quit without spidering any sites.
';
	exit;
}



?>