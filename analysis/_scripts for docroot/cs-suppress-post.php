<?php
/**** 
	Receives <FORM> POST from cs-suppress.php and sets email suppression OR just reports
	current status (depending on 'hours' parameter in POST). Uses the core code for
	lock handler as described below...

	Handler to manage all aspects of email alert suppression
	(It's actually usable as a generic lock mechanism.)
	via HTTP POST ... parameters are
		CS_API_KEY		=	a minimal secret key required to post anything
		md5_url	=		=	md5() hash of URL you're inquiring about or setting
		hours			=	the number of hours to suppress (only used when setting)
							> 0 indicates number of hours to suppress/lock
							0   indicates this is only an inquiry, no setting
							< 0 unsets the lock immediately
	
	This code uses an incoming MD5 hash (of a URL) to determine a lock file name.
	On the agent side (remote) mechanisms for POSTing to this server are similar to
	the way log entries are posted. But this email suppression doesn't rely on 
	MySQL - instead it creates and manages lock files.

	Files in the directory are named using md5 hash of their URL. Each files's content
	is the unix timestamp when the lock expires. "Lock" means email will be suppressed
	by agents. If a particular URL is locked, all agents should suppress email
	notifications for that URL. This is a centralized facility where agents can
	lock URLs and inquire about their status.

	Expired lock files are only removed when an inquiry reveals they've expired.

****/

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net

// Note: We want to execute this script from whatever directory it's placed in, but
// want to use common core code for the lock/unlock functionality, which may be 
// elsewhere. Include the core code now...

include('a/cs-lock-handler-core.php');	// Note the common code is down one dir in 'a/'

?>