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
$TITLE			= 'Journalists and press &mdash;';
$URL_HASHES		= array(
					'92a18a87260cc0b45ab4232226a690cc', // bdnews24.com
					'3eaf9a25677e8da2c57eec2a2c8a1feb', // carnegielibrary.org
					'5b5159d7b0a00d4aac67beae7929561f', // chinadigitaltimes.net
					'fc27060bda97ba82f408944afbf5d630', // chinadigitaltimes.net/chinese
					'ba5e509c82ebbab6be69a00fb47be270', // chinese-leaders.org
					'45ce635cb7900da0cfc1c3513cf8cd57', // chinesepen.org
					'3ff6ba15d726e06f75307b8fa4450f8f', // cyberlaw.stanford.edu
					'a585d97bc0f09e282bd9973002db6c50', // fr.rsf.org
					'4323fbfc85941328aeb620db6533830f', // icommons.org
					'15d3dbba8143f5ce63443687497bf3f5', // ijcentral.org
					'af23fdebc48331b905e070f84b92acf3', // journalistsecurity.net
					'790faa3385e8c5048598385a15d0e48f', // www.cpj.org
					'cd1d72816f79d6a09c7b2aadb18bd969', // www.derechos.org
					'c43726d74366c6809ddb43138d6a39bd', // www.englishpen.org
					'3a61adf15af592d4497f3e7652269099', // www.gipi.kg
					'437a464bf520eda2200f05e0b75ba500', // www.rsf.org
					'c2dc96744d290499549e9353d0d04a72', // www.vot.org
					'29947a4ad95e365778eaaacd731d6ff3', // onlinecensorship.org
					'd18b5c4405eab654f212216a8650782f', // publeaks.nl
					
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>