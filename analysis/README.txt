%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
/analysis

This directory will contain files related to analysis of cyberspark monitoring data.

Initially (early 2013) the plan is:

°	Write a utility that will read CS logs and move entries into a MySQL database. This'll probably be command-line on a server.

°	Write a utility that will process data from MySQL into some format that can then be graphed. This will probably have a web interface.

°	Write a grapher to display these data. The display will be on a web site.

Beyond that, some things that might be nice are:

°	Highly interactive ways to select data
	-	Point-and-click to select URLs to analyze. 
	-	Drag-to-select date ranges.
°	Ways to select the "type" of analysis you want. For instance, length changes in pages on highly dynamic sites should be ignored most of the time. You might want to know about slowness only on the sites that are normally fast, and not on all sites. A site might be down for a while due to its developer, and you'd want to ignore the site, or ignore a particular time period.