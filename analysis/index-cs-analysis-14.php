<?php
include('cs-log-pw.php');
include('cs-log-config.php');

////////////////////////////////////////////////////////////////////////
// Start at http://cyberspark.net/
//   The code is at github. Find the link on the CyberSpark site.
// Using http://D3js.org for visualization
//	 Tutorials at http://bost.ocks.org/mike/bar/3/

////////////////////////////////////////////////////////////////////////
$WIDTH_CHART 	= CHART_NARROW;
$span 			= 'P1D';
$TITLE			= 'Blotto sites not working? &mdash;';
$URL_HASHES		= array(
					'd9a74ffcbe241ef00f8181ae555ee509', // hrw.org
					'61568e84058a9a3f4b1bf63f36d3deaa', // www.phayul.com
					
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>