<?php
	/**
		CyberSpark.net monitoring-alerting system
		send email message
	*/

// PEAR mail pagkage
require_once 'Mail.php';
require_once 'Mail/mime.php';

// CyberSpark stuff
require_once 'include/echolog.inc';
include_once "include/functions.inc";

/////////////////////////////////////////////////////////
function textMail($to='', $from='', $replyTo='', $abuseTo='', $subject='', $message='', $smtpServer, $smtpPort, $user, $password) {
	global $identity;
	
	// Preprocessing
	// To: and From: require a space between any terminating quote and the opening "<"
	//     This lowers a spam-assassin score if you have mistakenly done this.
	$to      = str_replace('"<', '" <',$to);
	$from    = str_replace('"<', '" <',$from);
	$replyTo = str_replace('"<', '" <',$replyTo);
	$abuseTo = str_replace('"<', '" <',$abuseTo);

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
		$headers['To'] = $to;				// this is everyone (altered later)
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
// If your SMTP doesn't create a Message-ID: header, then you may want to enable this
//		$headers['Message-ID'] = '<'.sha1(microtime()).'@'.str_replace('ssl://','',$smtpServer).'>';

		// Send the message.
		// Note that email addresses are in a comma-separated list, which is specified in RFCs.
		// See RFC 822, RFC 2822, RFC 5322 at least.
		// In general, you can have raw email addresses, if you wish...
		//    a@bbb.com, b@ccc.com, c@ddd.com
		// The spaces should be OK, but I haven't verified.
		// You can put actual email addresses in brackets to differentiate from real names
		//    Sally_Smith <a@bbb.com>,Bill <b@ccc.com>,Bart <c@ddd.com>
		// And if you have nonalpha characters or spaces in the real name, you can isolate with quotes:
		//    "Dr. Sally Smith" <a@bbb.com>,"Mr. Bill Jr." <b@ccc.com>,"Black Bart!" <c@ddd.com>
		// HOWEVER, because of the way we parse, you cannot have commas within real names, even when quoted.
		// If you want to skip sending altogether, put in s single word "none" like this:
		//    none
		// Remember that CyberSpark sniffers log everything, so even if you never send an email, 
		//   all issues are logged.
		// Note that it is the PEAR package that is doing all of this fancy stuff, not our code here.
		// The PEAR package actually does not need us to pre-parse into individual addresses, because it is
		//   capable of handling the comma-separated format. We do it here solely to preserve privacy
		//   by sending messages to one addressee at a time.
		if (isset($to) and ($to != null) and (strlen($to) > 0)) {
			$toList = explode(",", $to);
			// Each message is sent individually. This way a recipient doesn't know who
			// (else) is being notified.
			foreach ($toList as $toMail) {
				$toMail = trim($toMail);			// trim leading and trailing blanks
 				$headers['To'] = $toMail;			// sanitize: show only the current addressee
           		// Send alert unless destination is "none"
                // (Although nonsensical, "none" can be intermixed with other destinations)
                if (strcasecmp($toMail, 'none') != 0) {
                    // Regular destinatioms...
			    	$send = $PEARmailer->send($toMail, $headers, $message);  
			    	if(PEAR::isError($send)) { 
						$message .= "\nCould not send this alert (to '$toMail'): " . $send->getMessage() . "\n";
						writeLogAlert("textMail() failed. Exception: " . $send->getMessage());
			    	}
                }
			}
		}
		else {
			echoIfVerbose("textMail() cannot send email without a 'to'\n");
		}
	}
	catch (Exception $mx) {
		echoIfVerbose("textMail() Exception: " . $mx->getMessage() . "\n");
	}
}

/////////////////////////////////////////////////////////
//  attachmentMail()
//  Send a MIME message. If $fileName is non-null, attach a file.
function attachmentMail($to='', $from='', $replyTo='', $abuseTo='', $subject='', $message='', $smtpServer, $smtpPort, $user, $password, $fileName) {
	global $identity;
	
	// Preprocessing
	// To: and From: require a space between any terminating quote and the opening "<"
	//     This lowers a spam-assassin score if you have mistakenly done this.
	$to      = str_replace('"<', '" <',$to);
	$from    = str_replace('"<', '" <',$from);
	$replyTo = str_replace('"<', '" <',$replyTo);
	$abuseTo = str_replace('"<', '" <',$abuseTo);

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
	
		// Set up message's content type header
		$contentType = "text/plain; charset=\"US-ASCII\""; // Default is text content type
		if (isset($fileName) and ($fileName != null) and (is_string($fileName)) and (strlen($fileName) > 0)) {
			// If a file is being attached, go to mime
			$mime = new Mail_mime();
			$mime->setTXTBody($message);
			if ($fileName != null) {
				$mime->addAttachment($fileName);
			}
			$mimeMessage = $mime->get();
			// Get the MIME separator (created inside the Mail_mime object) because it has to go into a header
			$i = strpos($mimeMessage, "\r\n");
			if ($i !== false) {
				// Take the assigned separator and add the multipart header
				$separator = substr($mimeMessage, 0, $i);
				$separator = str_replace('--', '', $separator);
				$contentType = "multipart/related; boundary=$separator";
			}
		}
		else {
			// Note that if we get here the message is still text and is treated as plain text
			$contentType = "text/plain; charset=\"US-ASCII\"";
			$mimeMessage = $message;
		}

		$headers = array();
		$headers['To'] = $to;				// this is everyone (altered later)
		$headers['From'] = $from;
		if (isset($replyTo)) {
			$headers['Reply-To'] = $replyTo;
			$headers['Return-Path'] = $replyTo;
		}
		$headers['Subject'] = $subject;
		$headers['MIME-Version'] = "1.0";
		$headers['Content-type'] = $contentType;
		$headers['Date'] = date("r");
		$headers['X-Mailer'] = $identity;
		$headers['X-Report-abuse'] = "Please report abuse to " . $abuseTo;
// If your SMTP doesn't create a Message-ID: header, then you may want to enable this
//		$headers['Message-ID'] = '<'.sha1(microtime()).'@'.str_replace('ssl://','',$smtpServer).'>';
		
		// Send the message.
		// Note that email addresses are in a comma-separated list, which is specified in RFCs.
		// See RFC 822, RFC 2822, RFC 5322 at least.
		// In general, you can have raw email addresses, if you wish...
		//    a@bbb.com, b@ccc.com, c@ddd.com
		// The spaces should be OK, but I haven't verified.
		// You can put actual email addresses in brackets to differentiate from real names
		//    Sally_Smith <a@bbb.com>,Bill <b@ccc.com>,Bart <c@ddd.com>
		// And if you have nonalpha characters or spaces in the real name, you can isolate with quotes:
		//    "Dr. Sally Smith" <a@bbb.com>,"Mr. Bill Jr." <b@ccc.com>,"Black Bart!" <c@ddd.com>
		// HOWEVER, because of the way we parse, you cannot have commas within real names, even when quoted.
		// If you want to skip sending altogether, put in s single word "none" like this:
		//    none
		// Remember that CyberSpark sniffers log everything, so even if you never send an email, 
		//   all issues are logged.
		// Note that it is the PEAR package that is doing all of this fancy stuff, not our code here.
		// The PEAR package actually does not need us to pre-parse into individual addresses, because it is
		//   capable of handling the comma-separated format. We do it here solely to preserve privacy
		//   by sending messages to one addressee at a time.
		if (isset($to) and ($to != null) and (strlen($to) > 0)) {
			$toList = explode(",", $to);
			// Each message is sent individually. This way a recipient doesn't know who
			// (else) is being notified.
			foreach ($toList as $toMail) {
				$toMail = trim($toMail);			// trim leading and trailing blanks
 				$headers['To'] = $toMail;			// sanitize: show only the current addressee
           		// Send alert unless destination is "none"
                // (Although nonsensical, "none" can be intermixed with other destinations)
                if (strcasecmp($toMail, 'none') != 0) {
                    // Regular destinatioms...
			    	$send = $PEARmailer->send($toMail, $headers, $mimeMessage);  
			    	if(PEAR::isError($send)) { 
						$message .= "\nCould not send this alert with attached file (to '$toMail'): " . $send->getMessage() . "\n";
						writeLogAlert("attachmentMail() failed. Exception: " . $send->getMessage());
			    	}
                }
			}
		}
		else {
			echoIfVerbose("attachmentMail() cannot send email without a 'to'\n");
		}
	}
	catch (Exception $mx) {
		echoIfVerbose("attachmentMail() Exception: " . $mx->getMessage() . "\n");
	}
}

/////////////////////////////////////////////////////////
function sendAlert($to, $scanResult, &$properties, $timeStamp) {
	// Send alert for this one URL
	$alertSubject = $properties['shortid'] . " Alert $timeStamp";
	// 'seed' the message
	$message = '';
	if (isset($properties['messageseed'])) {
		$message .= $properties['messageseed'] . "\n\n";	
	}
	// send the message
	textMail($to, $properties['from'], $properties['replyto'], $properties['abuseto'], $alertSubject, $message.$scanResult, $properties['smtpserver'], $properties['smtpport'], $properties['user'], $properties['password']);
}


/////////////////////////////////////////////////////////
//  sendMail()
//  Send an alert under certain circumstances (dictated by $scanResults)
function sendMail($scanResults, &$properties) {
	global $identity;
	
	$sendMessage = false;						// initial condition
	if ($properties['verbose']) {
		$sendMessage = true;
	}
	// 'seed' the message
	$message = '';
	if (isset($properties['messageseed'])) {
		$message .= $properties['messageseed'] . "\n";	
	}
	echoIfVerbose("sendMail() message seed: $properties[messageseed] \n");
	// Get defaults
	$to = $properties['to'];
	$from = $properties['from'];
	$timeStamp = date("r");
	$subject = $properties['shortid'] . " Report $timeStamp";
	
	$message .= "$timeStamp \n";
	$message .= "$identity \n\n"; 
	$message .= "CyberSpark.net monitoring and alerting software-- This open source software is provided free of charge under a Creative Commons by-nc-sa license. See http://cyberspark.net/license for more information.\n";
	if (isset($properties['ID'])) {
		$message .= $properties['ID'] . "\n";
	}
//	$message .= "Timestamp " . $timeStamp . "\n\n";

	if (isNotifyHour($properties['notify'])) {
		if (!$properties['notifiedtoday']) {
			// Send alert during administrative alerting time ("notify" time)
			$message .= "This is a daily administrative message sent even if everything is OK.\n\n";		
			$subject = $properties['shortid'] . " OK $timeStamp";
			$sendMessage = true;
			echoIfVerbose("Sending administrative check-in message to: " . $to . ": " . $scanResults . "\n");
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
//		$subject .= " $dateComposed";
		textMail($to, $from, $properties['replyto'], $properties['abuseto'], $subject, $message, $properties['smtpserver'], $properties['smtpport'], $properties['user'], $properties['password']);
	}
}

/////////////////////////////////////////////////////////
//  sendMailAttachment()
//  Sends a MIME message with an optional attached file. 
function sendMailAttachment($shortSubject, $message, &$properties, $fileName) {
	// Get defaults
	$to = $properties['to'];
	$from = $properties['from'];
	$timeStamp = date("r");
	$subject = $properties['shortid'] . " $shortSubject $timeStamp";
	
	$message = $properties['shortid'] . "\n" . $message . "\n\n";

	attachmentMail($to, $from, $properties['replyto'], $properties['abuseto'], $subject, $message, $properties['smtpserver'], $properties['smtpport'], $properties['user'], $properties['password'], $fileName);
}

/////////////////////////////////////////////////////////
//  sendMimeMail()
//  Sends a MIME message with an optional attached file. 
function sendMimeMail($shortSubject, $message, &$properties, $fileName) {
	return sendMailAttachment($shortSubject, $message, $properties, $fileName);
}
?>
