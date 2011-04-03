This directory contains the tripwires and scanners provided by CyberSpark.net.

Install the directory /cyberspark at the docroot of your web site. It must have permissions
set properly and be owned by your web server software (account 'httpd' or 'www-data' or
whatever that may be.  If you don't know that account name, you can set permissions to 777.  
There's an _htaccess file whose name you may change to .htaccess and it will prevent
your Apache server from revealing anything that's in this directory to a browser.
The CyberSpark tripwire (scanner) and utility may write data files into this directory - that's
why the directory has to be writeable.

Install one or more of cyberspark-scan.php or cyberspark-utility.php in your docroot. You should
CHANGE THE NAME of the script so it's not obvious to someone who might probe your server from
a web browser.  These scripts require PHP version 5.

Instructions on the use of cyberspark-scan and cyberspark-utility can be found in the project's
documentation.

More information for webmasters, users and security personnel is available online ->
http://cyberspark.net/webmaster