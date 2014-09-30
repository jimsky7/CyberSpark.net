<?php
include('cs-log-pw.php');
include('cs-log-config.php');

////////////////////////////////////////////////////////////////////////
// Start at http://cyberspark.net/
//   The code is at github. Find the link on the CyberSpark site.
// Using http://D3js.org for visualization
//	 Tutorials at http://bost.ocks.org/mike/bar/3/

$WIDTH_TT    	= 300;
$HEIGHT_TT		= 20;
$WIDTH_CHART 	= CHART_NARROW;
$HEIGHT_CHART	= 30;

////////////////////////////////////////////////////////////////////////
$span 			= 'P1D';
$TITLE			= 'Sample page &mdash;';
$URL_HASHES		= array(
					'b3eaf4bce2b85708a6c930a5d527340d', // cyberspark.net
					'383f65f95363c204221bd6b4cc4d6701', // rsf.org/en
					'a585d97bc0f09e282bd9973002db6c50'  // rsf.org/fr

			);

include('index-cs-analysis-common.php');
?>