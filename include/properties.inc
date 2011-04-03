<?php
	/**
		CyberSpark.net monitoring-alerting system
		read a 'properties' file
	*/

///////////////////////////////////////////////////////////////////////////////////
// Read properties from a file and return associative array of those properties
function getProperties($propsFileName) {
		$properties = array();
		// These are the three subsections of a "URL=" line
		// url=uuuuu;ccccc=eeeee
		// Each of these is used by numerical index from zero through whatever and
		//   the index corresponds to their order in the properties file.
		$urls		= array();	// contains 'url' objects, one per monitored URL
		$iu			= 0;		// the index into the $urls array
		
		try {
//echo "Opening " . $propsFileName . "\n";
			if($readHandle = fopen($propsFileName, "r")) {
				while (!feof($readHandle)) {
					$line = fgets($readHandle);
					if (strlen($line) > 0) {
						if (strncmp(trim($line), "#", 1) != 0) {
							// split line at "="
							list($key, $value, $overflow) = explode("=", $line, 3);
							if (isset($key) && isset($value)) {
								$key = trim($key);
								$value = trim($value);
								$value = str_replace("\n", "", $value);
								$value = str_replace("\r", "", $value);
								if (strcasecmp($key, "url") == 0) {
									// url=uuuuu;ccccc=eeeee
									//   uuuuu represents the http://url
									//   ccccc represents a comma separated list of conditions
									//   eeeee represents a comma separated list of email addresses
									// NOTE: Upon completion, all conditions are LOWERCASE
									try {
										$urls[$iu] = new url();
										list($discard, $u) = explode("=", trim($line), 2);  	// ("url=",everythingelse)
										list($urls[$iu]->url, $ce)  = explode(";", $u, 2);		// (url,conditions+emails)
										list($urls[$iu]->conditions, $urls[$iu]->emails) = explode("=", $ce, 2);
										$urls[$iu]->conditions = strtolower($urls[$iu]->conditions);
										if (strlen($urls[$iu]->conditions) > 0) {
											$urls[$iu]->conditionsArray = explode(",", $urls[$iu]->conditions);
										}
									}
									catch (Exception $iux) {
									}
									$iu++;
								}
								elseif (strcasecmp($key, "message") == 0) {
									// message=
									$properties['messageseed'] = $value;
									if (isset($overflow)) {
										$properties['messageseed'] .= "=" . $overflow;
									}
								}
								elseif (strcasecmp($key, "subject") == 0) {
									// subject=
									$properties['subject'] = $value;
									if (isset($overflow)) {
										$properties['subject'] .= "=" . $overflow;
									}
								}
								elseif (strcasecmp($key, "notify") == 0) {
									// notify=
									$properties['notify'] = $value;
								}
								elseif (strcasecmp($key, "pager") == 0 || strcasecmp($key, "sms") == 0) {
									// pager=
									$properties['pager'] = $value;
								}
								elseif (strcasecmp($key, "to") == 0) {
									// to=
									$properties['to'] = $value;
								}
								elseif (strcasecmp($key, "from") == 0) {
									// from=
									$properties['from'] = $value;
//echo "Property From: " . $value . "\n";
								}
								elseif (strcasecmp($key, "user") == 0) {
									// user=
									$properties['user'] = $value;
								}
								elseif (strcasecmp($key, "pass") == 0 || strcasecmp($key, "password") == 0) {
									// pass=
									$properties['password'] = $value;
								}
								elseif (strcasecmp($key, "verbose") == 0) {
									// verbose=
									if (strcasecmp($value, "true") == 0) {
										$properties['verbose'] = true;
									}
									else {
										$properties['verbose'] = false;
									}
								}
								elseif (strcasecmp($key, "host") == 0) {
									// host=
									$properties['host'] = $value;
								}
								elseif (strcasecmp($key, "smtpserver") == 0) {
									// smtpServer=
									$properties['smtpserver'] = strtolower($value);		// all lowercase
								}
								elseif (strcasecmp($key, "useragent") == 0) {
									// useragent=
									$properties['useragent'] = $value;					// allow upperlower
								}
								elseif (strcasecmp($key, "time") == 0) {
									// time=minutes
									try {
										list($properties['time']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
								elseif (strcasecmp($key, "timeout") == 0) {
									// timeout=seconds
									try {
										list($properties['timeout']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
								elseif (strcasecmp($key, "smtpport") == 0) {
									// smtpServer=
									try {
										list($properties['smtpport']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
								elseif (strcasecmp($key, "slow") == 0) {
									// slow=
									try {
										list($properties['slow']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
							}
						}
					}
				}
				fclose($readHandle);
			}
		}
		catch (Exception $x) {
			$properties['error'] = $x-getMessage();
		}
		// Insert the URL= elements
		$properties['urls'] = $urls;
		return $properties;
}
	
///////////////////////////////////////////////////////////////////////////////////
// Do some error checking and adjust the properties so they're always safe to use
function fixProperties($properties) {

		if (($properties['smtpport'] == 465) && strncmp($properties['smtpserver'], "ssl://", 6) != 0) {
			$properties['smtpserver'] = "ssl://" . $properties['smtpserver'];
		}
		if (!isset($properties['slow'])) {
			$properties['slow'] = 30;
		}
		if (!isset($properties['timeout'])) {
			$properties['timeout'] = 60;
		}
		return $properties;
}

?>