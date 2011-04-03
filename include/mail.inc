<?php
	/**
		CyberSpark.net monitoring-alerting system
		send email message
	*/

// PEAR mail pagkage
require_once 'Mail.php';
// CyberSpark stuff
require_once 'include/echolog.inc';
include_once "include/functions.inc";

/////////////////////////////////////////////////////////
function textMail($to, $from, $replyTo, $abuseTo, $subject, $message, $smtpServer, $smtpPort, $user, $password) {
	global $identity;
	
	try {
		if (!isset($subject)) {
			$subject = 'CyberSpark alert';
		}

		$params = array();
		$params['host'] = $smtpServer;
		$params['port'] = $smtpPort;
		$params['auth'] = true;
		$params['username'] = $user;
		$params['password'] = $password;
		$params['timeout'] = 30;
		$PEARmailer =& Mail::factory ('smtp', $params);
	
		$headers = array();
		$headers['To'] = $to;
		$headers['From'] = $from;
		if (isset($replyTo)) {
			$headers['Reply-To'] = $replyTo;
			$headers['Return-Path'] = $replyTo;
		}
		$headers['Subject'] = $subject;
		$headers['MIME-Version'] = "1.0";
		$headers['Content-type'] = "text/plain; charset=\"US-ASCII\"";
		$headers['Date'] = date("r");
		$headers['X-Mailer'] = $identity;
		$headers['X-Report-abuse'] = "Please report abuse to " . $abuseTo;

		// Send the message
		$toList = explode(",", $to);
		foreach ($toList as $toMail) {
                        // Send alert unless destination is "none"
                        // Although nonsensical, "none" can be intermixed with other destinations
                        if (strcasecmp($toMail, 'none') != 0) {
                            // Regular destinatioms...
			    $send = $PEARmailer->send($toMail, $headers, $message);  
			    if(PEAR::isError($send)) { 
				$message .= "\nException sending an alert: " . $send->getMessage() . "\n";
				writeLogAlert("textMail() failed. Exception: " . $send->getMessage());
				// echo($send->getMessage()); 
			    }
                        }
		}
	}
	catch (Exception $mx) {
		echoIfVerbose("textMail() Exception: " . $mx->getMessage() . "\n");
	}
}

/////////////////////////////////////////////////////////
function sendAlert($to, $scanResult, $properties, $timeStamp) {
	// Send alert for this one URL
	$alertSubject = $properties['shortid'] . " Alert $timeStamp";
	textMail($to, $properties['from'], $properties['replyto'], $properties['abuseto'], $alertSubject, $scanResult, $properties['smtpserver'], $properties['smtpport'], $properties['user'], $properties['password']);
}


/////////////////////////////////////////////////////////
function sendMail($scanResults, &$properties) {
	global $identity;
	
	$sendMessage = false;						// initial condition
	if ($properties['verbose']) {
		$sendMessage = true;
	}
	$message = $properties['message'] . "\n";	// 'seed' the message
	// Get defaults
	$to = $properties['to'];
	$from = $properties['from'];
	$timeStamp = date("r");
	$subject = $properties['shortid'] . " Report $timeStamp";
	
	$message .= $timeStamp . "\n";
	$message .= "$identity \n\n"; 
	$message .= "CyberSpark.net monitoring and alerting software-- This open source software is provided free of charge under a Creative Commons by-nc-sa license. See http://cyberspark.net/license for more information.\n";
	$message .= $properties['ID'] . "\n";
//	$message .= "Timestamp " . $timeStamp . "\n\n";

	if (isNotifyHour($properties['notify'])) {
		if (!$properties['notifiedtoday']) {
			// Send alert during administrative alerting time ("notify" time)
			$message .= "This is a daily administrative message sent even if everything is OK.\n\n";		
			$subject = $properties['shortid'] . " OK $timeStamp";
			$sendMessage = true;
			echoIfVerbose("Sending administrative check-in message to: " . $to . ": " . $scanResult . "\n");
			if ($properties['time'] <= 60) {
				// Avoid notifying more than once a day
				$properties['notifiedtoday'] = true;
			}
		}
	}
	else {
			// At all times other than the 'notify hour' just turn the switch off so that
			// next time the notify hour arrives a message will be sent.
			$properties['notifiedtoday'] = false;
	}

	$iu = 0;
	foreach ($scanResults as $scanResult) {
		if (strncmp("OK", $scanResult, 2) != 0) {
			// Result doesn't begin with "OK" so alerting is needed
			// Send alert
			$urls = $properties['urls'];
			echoIfVerbose("Sending alert(s) to: " . $urls[$iu]->emails . ": \n" . $scanResult . "\n");
			sendAlert($urls[$iu]->emails, $scanResult, $properties, $timeStamp);
		}	 
		// Add to big message in all cases
		if (strlen($scanResult) > 1) {
			$message .= $scanResult . "\n";
		}
		$iu++;
	}
	if ($sendMessage) {
		$subject .= " $dateComposed";
		textMail($to, $from, $properties['replyto'], $properties['abuseto'], $subject, $message, $properties['smtpserver'], $properties['smtpport'], $properties['user'], $properties['password']);
	}
}


?>
