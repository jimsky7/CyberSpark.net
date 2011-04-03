%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
http://cyberspark.net/
http://cyberspark.net/webmaster

See the 'documents' directory for documentation.

Topmost directory contains cyberspark.php and cybersparkd.php which are respectively the monitoring daemon and the control daemon for CyberSpark monitoring.

To set up:

* You need a Debian or Ubuntu server - it can be relatively small because all it's going to run is a few PHP processes. It should not have any ports open inbound other than 22 (SSH). Generally you'll want to allow all outbound ports. You might also want to have ntpd running. No database or mail software is required, nor should it be running. And no web server! For the sake of security, no other processes that talk to the outside world should be running on this server.
* You must have PHP-cli (PHP command line) and PHP 5 installed.
* You need to have an email account and a corresponding SMTP server somewhere else - these should not be on the server that's running this monitoring software.
* You would benefit from having a Google Safe Browsing [GSB] server implemented and operating - CyberSpark has one of these if you can't set up one of your own.  (This isn't trivial, sorry.)  It's not necessary, however.
* Set various items within cyberspark.config.php ... these will be your email parameters and other specifics for this installation.

* Copy the '/etc/init.d/cyberspark' file into your real /etc/init.d/
* Create a subdirectory /cyberspark that is world-writeable in your docroot.
* Copy cyberspark.config.php, cyberspark.php and cybersparkd.php into your docroot.
* Copy the 'filters' 'include' and 'properties' directories to your docroot.
* Modify the CS8-0.properties file to contain one directive for testing purposes.
* Run the cyberspark super-daemon:
	nohup /etc/init.d/cyberspark start
* You should see some processes in 'top' (I like 'htop' better) at this point and monitoring should begin.

It's not quite that easy and there may be some stumbling blocks, but that's a good general outline of the installation process.


