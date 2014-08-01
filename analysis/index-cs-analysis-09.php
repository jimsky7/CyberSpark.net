<?php
include('cs-log-pw.php');
include('cs-log-config.php');

////////////////////////////////////////////////////////////////////////
// Start at http://cyberspark.net/
//   The code is at github. Find the link on the CyberSpark site.
// Using http://D3js.org for visualization
//	 Tutorials at http://bost.ocks.org/mike/bar/3/
	
////////////////////////////////////////////////////////////////////////
$WIDTH_CHART 	= CHART_WIDE;
$span 			= 'P4D';
$TITLE			= 'CS4-2 Skeptic sites &mdash;';
$URL_HASHES		= array(
					'a6d30fd8c0b96f3ad629aecfe945958d',	// virtualskeptics.com
					'6d2fea84826badf6e3975b7c89dc360c', // iigatlanta.org
					'c4737840eae7f419294699f066edbc02', // skepticality.com
					'deba73ca2ac868cb69279109d60b7d0e', // www.amazingmeeting.com
					'a8454c442d48577835c96241ea6954c3', // skeptools.com
					'94870149312fa58bedc4a15a5c49dd14'  // whatstheharm.net
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>