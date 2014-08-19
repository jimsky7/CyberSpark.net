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
$span 			= 'P2D';
$TITLE			= 'Conflict &mdash;';
$URL_HASHES		= array(
					'd5bd6a0f754a0e8532b390faeab6a410', // adenalghad.net
					'a4a03119d94266896e70fb9d4fb5f3c4', // alkasir.com
					'6e94854a8b1c2624eda80a64a8109f31', // alsahwa-yemen.net
					'36c793208e2c818a1030fabeb2d4c913', // bidaran.net
					'10b21ef24fde03c3260577743b3dde26', // bolobhi.org
					'cae04c5b4f6e861fb0e91b391c982ba1', // khodnevis.org
					'a2a60a826d680341adc7469686979e56', // mideastgate.org
					'd358e22f63c891151f0044506916a313', // msader-ye.net
					'423a4e069cce7db77965ebaa72369b00', // observacuba.org
					'6bb4ff2cd56777df63e28ef531248e9f', // sahafah.net
					'8804030c513463b76d5b9742b6e93279', // sahamnews.org
					'b7e09eb4517898026431962baccbe56f', // voice-yemen.com
					'f149606b357fbe69117df95aa0766d52', // vxheavens.com
					'03ccc4c0113a9e34d229a0b8c3190b1f', // woeser.middle-way
					'1d3c29174fc6b04ebcefcbe65f5a8821', // www.adalah.org
					'ae08e071d3854605ede5d3f0c017c271', // www.alainet.org
					'63fbcaca071ef02615953053fdb073d4', // www.alhaq.org
					'9ce545da61c61c0caa1abdbf435b153c', // www.americainarabic.net
					'ced95cf71fec7af2d842bb6a0b60863d', // www.americainarabic.org
					'17b3a0a5a239c0d81357683502098071', // www.bidaran.net
					'cd7c86af52f22e308c9091fad20ac26d', // www.colectivodeabogados.org
//					'ccd748fb1310d8e7772f1d8b7096cc23', // www.crd-net.org
					'18e3bbc8f632366c924f69c1be437fa5', // www.dailystar.com.lb
					'c301ebb9cc23b4222b59661ec302aa1b', // www.dalailama.com
					'78f0b5ac73ef1c630e9107efdc392745', // www.humanrights-ir.org/english/
					'b41be5921a722c2b051ffabc2eab6388', // www.humanrights.asia
					'95c550055edb38025d6f2efa07f68ef3', // www.ihearttibet.org
					'de4d89f9e11e7502d84bbc8407e0fa19', // www.irrawaddy.org
					'64a23acd8f7271a68a2ff8a2dbad8143', // www.libyanleague.org
					'b8162bee67b8532414b0b09ae1a514b9', // www.palestinaya.org
					'61568e84058a9a3f4b1bf63f36d3deaa', // www.phayul.com
					'4a1be1011ba7581761ff6f1dff81154c', // www.pchrgaza.org/portal/en/
					'c5ab7ef4385440fc4b97aa932d5fef7c', // www.savetibet.org
					'6c81e95d09f41577c6f7adc83f30da02', // www.shaheedoniran.org/english/
					'c8770b2a84c721d31bc0b83da2030949', // www.shaheedoniran.org/farsi/
					'853b0316c8a7e0b914b1d7431413fb3e', // yemen-press.com
					'd47433d3dc4cfb7fdb2f49f7eadcbd7f', // yemen.net.ye
					'3070ace797812b42f9957d67da30b297', // yemenat.net
					'8ccec7810e417b6e6d64c45199ed87a4', // yemenportal.net
					'a3a7ae2532261b5abd02a32faef75f03', // www.nonviolent-conflict.org
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>