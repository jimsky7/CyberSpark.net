<?php
function readMoreProps($fileName, &$data) {
	$f = fopen($fileName, "r");
	if (!$f) {
		echo "<span class='RED_ALERT'>Couldn't open $fileName in readMoreProps()</span><br/>\r\n";
	}
	$line = 0;
// echo "Parsing: $fileName<br/>\r\n";
	$i = strrpos($fileName, ".");
	$j = strrpos($fileName, "/");
	if ($i && $j) {
		$ID = substr($fileName, $j+1, $i-$j-1);
	}
// echo "Sniffer ID: $ID<br/>\r\n";
	while (($s = fgets($f)) !== false) {
		$s = trim($s);
		if (strncmp($s, "#", 1) == 0) {
			// Ignore comment line (starts with "#")
			continue;
		}
		list($key, $value) = explode('=', $s, 2);
		
		if (strcasecmp($key, 'url') == 0) {
			// url=ooooooooooooo
			// Get URL
			list ($url, $s) = explode(';', $value);
			if (isset($url) && isset($s)) {
				list ($filters, $emails) = explode('=', $s);
				$data[$url] = array('ID'=>$ID, 'filters'=>$filters, 'emails'=>$emails);
			}
		}
		$line++;		
	}
// echo "Total of $line useful lines read<br/>\r\n";
}
?>