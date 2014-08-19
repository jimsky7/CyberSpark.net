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
$span 			= 'P3D';
$TITLE			= 'CS9 (dev system) sites &mdash;';
$URL_HASHES		= array(
					'a88f27ca8bf9348cce493fd4aafef0e8',	// avaaz.org
					'10b21ef24fde03c3260577743b3dde26', // bolobhi.org
					'3eaf9a25677e8da2c57eec2a2c8a1feb', // carnegielibrary.org
					'308c12cb5a9b3d1be9578444080361ca', // necessarandproportionate.org
					'8d4b1c075f2d6126908bbae47922b9a2', // ila-net.com
					'735e49bc60f5e45a1e6705a2451fa244', // mettacenter.org
					'd65023b92259538d2369b2c876e31394', // privatemanning.org
					'7cccc4f514f29db8c9723cd0104497cd', // restorethe4th.com
					'f0eb7c108adbbc0f113d666b0041d8b2', // http://wiki.15m.cc/wiki/Portada
					'befe55de3f99cae5945b03f87fd54767', // apc.org
					'37fa21533ea9de1e1b4565569538bff6', // freedomnotfear.org
					'1a45797d0341eaa06f475eba43261cd5', // irex.org
					'9d3ff4a865aac42f230c467200495546', // meta-activism.org
					'18f534fe420b24dede2935a76da9f052', // openrightsgroup.org
					'70fc1509399d3a5e0122ee85c9b2a66a', // theelders.org
					'29947a4ad95e365778eaaacd731d6ff3', // onlinecensorship.org
					'6031d39bab652bf1df49d849ae6be59b', // optin.stopwatching.us
					'2c8c34a61c2d8b1d932c11de8559eada', // secure.avaaz.org/en/
					'4fd9c9a2de4fbab8494b2f27d37f3118', // https://twitter.com
					'e203e98e4c606735cf56db84a002fd22', // https://facebook.com
					'd75277cdffef995a46ae59bdaef1db86', // https://google.com
					'4a1def3e9ff704e9fefbc0415f05237a' // thunderclap.it
//					'fdbe6d429307d63108491564cd5ce0cb' // red7.com
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>