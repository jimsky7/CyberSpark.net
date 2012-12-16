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
			if($readHandle = fopen($propsFileName, 'r')) {
				while (!feof($readHandle)) {
					$line = fgets($readHandle);
					if (strlen($line) > 0) {
						$tline = trim($line);
						if (strncmp($tline, '#', 1) != 0) {
//echo "$tline \n";
							// split line at first "="
							if (strpos($tline, '=') !== false) {
								// First separate into key=value
								list($key, $value) = explode('=', $tline, 2);
							}
							else {
								$key = $tline;
								$value = '';
							}
//echo "$key : $value \n";
							if (isset($key) && isset($value)) {
								$key = trim($key);
								$value = trim($value);
								$value = str_replace(array("\n","\r"), "", $value);
								if (strcasecmp($key, 'url') == 0) {
									// url=uuuuu;ccccc=eeeee
									//   uuuuu represents the http://url (which might include "=" within it!)
									//   ccccc represents a comma separated list of conditions
									//   eeeee represents a comma separated list of email addresses
									// NOTE: Upon completion, all conditions are LOWERCASE
									try {
										$urls[$iu] = new url();
										list($discard, $u) = explode("=", trim($line), 2);  	// ("url=",everythingelse)
										// Break apart at the ";"
										list($urls[$iu]->url, $ce)  = explode(";", $u, 2);		// (url,conditions+emails)
										// Break apart the last portion into conditions and email addresses
										list($urls[$iu]->conditions, $urls[$iu]->emails) = explode("=", $ce, 2);
										// Force the conditions to lowercase
										$urls[$iu]->conditions = strtolower($urls[$iu]->conditions);
										if (strlen($urls[$iu]->conditions) > 0) {
											// Explode the conditions apart to make an array (of 'filter' names)
											$urls[$iu]->conditionsArray = explode(",", $urls[$iu]->conditions);
										}
									}
									catch (Exception $iux) {
									}
									$iu++;
								}
/////////// message=
/////////// (default message "starter")
								elseif (strcasecmp($key, 'message') == 0) {
									// message=
									$properties['messageseed'] = $value;
									if (isset($overflow)) {
										$properties['messageseed'] .= "=" . $overflow;
									}
								}
/////////// subject=
/////////// (default subject line for email messages)
								elseif (strcasecmp($key, 'subject') == 0) {
									// subject=
									$properties['subject'] = $value;
									if (isset($overflow)) {
										$properties['subject'] .= "=" . $overflow;
									}
								}
/////////// notify=
/////////// (the hour in which to send the 'OK' message)
								elseif (strcasecmp($key, 'notify') == 0) {
									// notify=
									$properties['notify'] = $value;
								}
/////////// pager=
/////////// (this is for short PAGER notifications < 160 chars) NOT USED ANY MORE IN THE CODE
								elseif (strcasecmp($key, 'pager') == 0 || strcasecmp($key, 'sms') == 0) {
									// pager=
									$properties['pager'] = $value;
								}
/////////// to=
/////////// (the default "To:" for emails)
								elseif (strcasecmp($key, 'to') == 0) {
									// to=
									$properties['to'] = $value;
/////////// from=
/////////// (the default "From:" for emails)
								}
								elseif (strcasecmp($key, 'from') == 0) {
									// from=
									$properties['from'] = $value;
//echo "Property From: " . $value . "\n";
								}
/////////// user=
/////////// (user name for SMTP login)
								elseif (strcasecmp($key, 'user') == 0) {
									// user=
									$properties['user'] = $value;
								}
/////////// pass=
/////////// password=
/////////// (password for SMTP login)
								elseif (strcasecmp($key, 'pass') == 0 || strcasecmp($key, 'password') == 0) {
									// pass=
									$properties['password'] = $value;
								}
/////////// verbose=
/////////// (if 'true' it causes more messages to be emitted, logged or emailed)
								elseif (strcasecmp($key, 'verbose') == 0) {
									// verbose=
									if (strcasecmp($value, 'true') == 0) {
										$properties['verbose'] = true;
									}
									else {
										$properties['verbose'] = false;
									}
								}
/////////// host=
/////////// (name of this host - used only in messages)
								elseif (strcasecmp($key, 'host') == 0) {
									// host=
									$properties['host'] = $value;
								}
/////////// smtpserver=
/////////// (name or IP address of SMTP server that will be used for notifications)
/////////// (if this begins "ssl://" then port 465 and SSL will be used to secure the connection)

								elseif (strcasecmp($key, 'smtpserver') == 0) {
									// smtpServer=
									$properties['smtpserver'] = strtolower($value);		// all lowercase
								}
/////////// useragent=
/////////// (a user agent string to be used when doing HTTP GET)
								elseif (strcasecmp($key, 'useragent') == 0) {
									// useragent=
									$properties['useragent'] = $value;					// allow upperlower
								}
/////////// time=
/////////// (number of MINUTES to wait before checking URLs again)
								elseif (strcasecmp($key, 'time') == 0) {
									// time=minutes
									try {
										list($properties['time']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
/////////// timeout=
/////////// (default timeout for HTTP GET - this does not work on Ubuntu 8.04 and 10.04)
								elseif (strcasecmp($key, 'timeout') == 0) {
									// timeout=seconds
									try {
										list($properties['timeout']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
/////////// smtpport=
/////////// (port number for outbound - 25 is regular, 587 is alternate, and 465 triggers an SSL connection)
								elseif (strcasecmp($key, 'smtpport') == 0) {
									// smtpServer=
									try {
										list($properties['smtpport']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
/////////// slow=
/////////// (a number of seconds - if the lag in an HTTP GET is lower than this, then we consider it a 
///////////   successful connection, and if higher then we consider it a "slow" connection)
								elseif (strcasecmp($key, 'slow') == 0) {
									// slow=
									try {
										list($properties['slow']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
/////////// load=
/////////// (an integer - if the LOADAVG is this or higher then consider sending a warning) 
/////////// This is used within the 'cyberscan' filter .
								elseif (strcasecmp($key, 'load') == 0) {
									// slow=
									try {
										list($properties['load']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
/////////// disk=
/////////// (an integer - if the percent space used on "/" is this or higher then consider sending a warning) 
/////////// This is used within the 'cyberscan' filter. 
								elseif (strcasecmp($key, 'disk') == 0) {
									// slow=
									try {
										list($properties['disk']) = sscanf($value, "%d");
									}
									catch (Exception $x) {
									}
								}
/////////// *=
/////////// All other values are thrown into the $properties array anyway, so they can be picked up as $args[] and
/////////// sent thru to some filter we have not yet dreamed up.
/////////// Note that the index "[]" will be lowercased, but the value will be left alone.
								else {
									$properties[strtolower($key)] = $value;
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