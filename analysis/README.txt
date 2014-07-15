README.txt

// **** http://cyberspark.net/
// **** https://github.com/jimsky7/CyberSpark.net
// **** See also http://d3js.org/


'Index' files in this directory contain samples in which we use D3js to graph CyberSpark 
data.

'PHP' files in this directory may be used to:
	1) upload new log files to MySQL
	2) upload (using HTTP POST) new log entries to MySQL
	3) fetch log entries for a time period as CSV or TSV files

Important note: These PHP scripts should be on their own server, running a web server, 
and not on any 'sniffer' system(s). They've been tested on NGINX but should run with
no modification on an Apache server.

You will need to put "D3JS" in a directory /d3/ for the javascript to work.


See documentation in "CyberSpark Analysis Architecture and tech dox.doc"