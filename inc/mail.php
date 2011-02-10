<?php
	/**
		CyberSpark.net monitoring-alerting system
		send email message
	*/


function textMail() {
	global $smtpServer;
	global $smtpPort;

	try {
		$to  = $_POST['to'];
		$subject = 'CyberSpark alert';
		$message = 'Alert!\n';

		$params = array();
		$params['host'] = 'base.red7.com';
		$params['port'] = 587;
		$params['auth'] = 'PLAIN';
		$params['username'] = 'mail7@red7.com';
		$params['password'] = 'panda108';
		$params['localhost'] = 'localhost';
		$params['timeout'] = 30;
		$PEARmailer =& Mail::factory ('smtp', $params);
	
		$headers = array();
		$headers['To'] = $to;
		$headers['From'] = "\"CyberSpark.net\" <abuse@cyberspark.net>";
		$headers['Reply-To'] = "abuse@cyberspark.net";
		$headers['Subject'] = $subject;
		$headers['MIME-Version'] = "1.0";
		$headers['Content-type'] = "text/html; charset=iso-8859-1";
		$headers['Date'] = date("r");
		$headers['X-Originating-IP'] = $_SERVER['REMOTE_ADDR'];
		$headers['X-Report-abuse'] = "Please report abuse to hostmaster@red7.com";

		// Sent to requested address
		$send = $PEARmailer->send($to, $headers, $message);  
		if(PEAR::isError($send)) { 
			/** echo($send->getMessage()); **/ 
		}
		
		// Separate copy to hostmaster
		$headers['To'] = "hostmaster@red7.com";
		$send = $PEARmailer->send("hostmaster@red.com", $headers, $message);  
		if(PEAR::isError($send)) { 
			/** echo($send->getMessage()); **/ 
		}

	}
	catch (Exception $mx) {
	}



?>