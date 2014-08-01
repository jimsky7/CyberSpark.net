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
$TITLE			= 'Advocates and activists &mdash;';
$URL_HASHES		= array(
					'8e7e3b9ec73001a8035360fbd966ad91', // www.accessnow.org
					'a7402209f7371b62cb3888e6870349db', // advocacy.globalvoicesonline.org
					'e059bf58e6df39d6cfcfb71695f36a0a', // www.amnesty.org
					'a88f27ca8bf9348cce493fd4aafef0e8', // avaaz.org/en
					'b3eaf4bce2b85708a6c930a5d527340d', // cyberspark.net
					'6ab5eaca112b4186de8b0102d5e7332e', // chokepointproject.net
					'1de150c5e9b2b970a7c8011dda3f3b12', // crypto.cat
					'a4ecd3013c8306027453b05779051210', // www.civilrightsdefenders.org
					'3ff6ba15d726e06f75307b8fa4450f8f', // cyberlaw.stanford.edu
					'1848b573e61ec8f69a32b0ac3a8c590f', // digitalrightsfoundation.pk
					'8dd9360ab6cfe963fd05360ac82e379e', // www.fidh.org
					'0916ea889a723c2f691b5a12c175fd09', // humanityunited.org
					'5b80d1d78b1337cfa9ed4594b79912b6', // humanrightshouse.org
					'ff4a3a62334eeda2ed3de3c44b3e2259', // invisiblechildren.com
					'828201ba89773be07f372e83385e3a99', // movements.org
					'ab0d9abedb77357a563dd1339d51a516', // msf.org
					'a6d1d2e23bfa3fdcc65ad829b917fed9', // natalia.civilrightsdefenders.org
					'308c12cb5a9b3d1be9578444080361ca', // en.necessaryandproportionate.org/text
					'9048cfcacb30d9c2781959b94313cdcd', // nobelprize.org
					'a3a7ae2532261b5abd02a32faef75f03', // www.nonviolent-conflict.org
					'18f534fe420b24dede2935a76da9f052', // www.openrightsgroup.org
					'3ad794711d6d9c08249ceb3f62a4fa4a', // www.peacemagazine.org
					'61568e84058a9a3f4b1bf63f36d3deaa', // www.phayul.com
					'd65023b92259538d2369b2c876e31394', // privatemanning.org
					'7cccc4f514f29db8c9723cd0104497cd', // restorethe4th.com
					'a86ffb2bbd6f47c5232508b607bcc86b', // www.stoptorture.org.il
					'70fc1509399d3a5e0122ee85c9b2a66a', // www.theelders.org
					'0fc7b4b43ae0a5cdcacf72619da5d4ad', // www.theengineroom.org
					'449d8c8723ddb04fb55526f608e5806f', // tibet.net
					'f5b62f0b027f6ab25e85a8675b000562', // transparency.globalvoicesonline.org
					'898bffdd0bbcca5964c0361375c151d9', // www.transparency.org
					'b241d9c18866d71a7969079868e37d47', // witness.org

					
			);

////////////////////////////////////////////////////////////////////////
include('index-cs-analysis-common.php');
?>