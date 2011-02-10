<?php
	/**
		CyberSpark.net monitoring-alerting system
		handle command-line arguments
	*/


function parseArgs($argv) {
	global $isDaemon;
	global $configTest;

	$i = 0;
	while ($i < count($argv)) {
		if($argv[$i] == '--help' || $argv[$i] == 'help' || count($argv) == 1) {
			giveHelp();
		}
		elseif ($argv[$i] == '--daemon') {
			$isDaemon = true;
echo "Daemon mode\n";
		}
		elseif ($argv[$i] == '--configtest') {
			$configTest = true;
echo "Configuration test (only)\n";
		}
		elseif ($argv[$i] == '--props' || $argv[$i] == '--properties') {
			if (($i+1) < count($argv)) {
				$propsFileName = $argv[$i+1];
				$i++;
			}
		}
echo "Configuration test (only)\n";
		}
		$i++;
	}
}

function giveHelp() {
	// '/help'  - - - - - - - - - - - -
	echo 'CyberSpark Monitoring-Alerting system
cyberspark.php --arg --arg --arg
--help
  Print this help message and quit.
--daemon 
  Run as a daemon (keep executing until killed)
  If this parameter is not present, just run one loop.
--configtest
  Read properties file and test to see if the configuration 
  will work.  Then quit without spidering any sites.
--props filename
--properties filename
  Specifies the file to be opened as "properties" for this script.
';
	exit;
}



?>