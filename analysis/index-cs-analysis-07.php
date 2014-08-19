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
$TITLE			= 'CS9 (dev system) sites &mdash;';
$URL_HASHES		= array(
					'ab0d9abedb77357a563dd1339d51a516',	// msf.org
					'9048cfcacb30d9c2781959b94313cdcd', // nobelprize.org
					'79346372039750b5aafc783a1cf8702f', // globalforceforhealing.org
					'0916ea889a723c2f691b5a12c175fd09', // humanityunited.org
					'a4a03119d94266896e70fb9d4fb5f3c4', // alkasir.com
//					'fdbe6d429307d63108491564cd5ce0cb', // red7.com
					'383f65f95363c204221bd6b4cc4d6701', // en.rsf.org
					'a88f27ca8bf9348cce493fd4aafef0e8', // avaaz.org
					'8e7e3b9ec73001a8035360fbd966ad91', // accessnow.org
					'2a49903efca953e340a5a31748f342eb', // theforgotten.org
					'ba5e509c82ebbab6be69a00fb47be270', // chinese-leaders.org
					'37a66f4e8898391c3c2210f1097f0b7a', // www.codethechange.org
//					'6ab5eaca112b4186de8b0102d5e7332e', //chokepointproject.net
					'd18b5c4405eab654f212216a8650782f', // publeaks.nl
					'a4ecd3013c8306027453b05779051210', // http://www.civilrightsdefenders.org/ { 156 samples }
					'8dd9360ab6cfe963fd05360ac82e379e', // http://www.fidh.org/ { 157 samples }
//					'b3eaf4bce2b85708a6c930a5d527340d', // cyberspark.net
					'7cccc4f514f29db8c9723cd0104497cd', // restorethe4th.com
					'befe55de3f99cae5945b03f87fd54767', // apc.org
					'b622717f93236090dc6f117f866904b6'  // chinachange.org
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>