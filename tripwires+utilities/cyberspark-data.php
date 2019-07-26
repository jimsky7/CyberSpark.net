<?php
echo '<html>
 <head>
  <title>CyberSpark.net PHP-MySQL tripwire</title>
 </head>
 <body>
  <div align="justify"><blockquote><blockquote><font face="Arial" size=2><br />';

//////////////////////////////////////////////////////
// MYSQLI 'true' if you want to use PHP mysqli
//////////////////////////////////////////////////////
define ('MYSQLI', true);					// to use newer mysqli() class
define ('WATCH_SOME_ROW_COUNTS', true);	// to track change in SOME row counts of specific table
										// BEST for WordPress sites
define ('WATCH_ALL_ROW_COUNTS', false);	// if you want to watch ALL table sizes
										// NOT advisable for WordPress sites

//////////////////////////////////////////////////////
//  INSTRUCTIONS AND CONFIGURATION
///////////////////////////////////////////////////

	// THANKS to Chris (in Paris) for refining this script!
    // On va afficher les tables de la base de données WordPress
    // Pour le watchdog de Jim Schuyler (maintenant "CyberSpark")
    // Si la taille de cette page change, c'est que le nombre de tables a changé
    // ou que la taille de wp-login.php (ou d'autres fichiers) a changé
	   
	// This file must be installed at the web server's docroot for the monitored site
	
	// It access the WordPress database and reports the number of tables present, plus the number
	// of rows in each. It can be configured to report back based on the size of each table, or
	// only specific table sizes. The monitoring system is responsible for detecting any change
	// in the length of this report and signalling or alerting a responsible person.
	
	/** Version 4.03 on 2011-09-15 **/
	/** Revised 2014-08-24 by sky@cyberspark.net **/
	
	// Change the file extension to PHP from TXT and copy it to your docroot for the desired site
	// Be sure PHP is installed and configured for your web server
	
	// Use this configuration ONLY if a WordPress wp-config.php file is present
	// Otherwise you need to put in strings with the correct values
	// Modify the path in the INCLUDE so it references your WordPress config file
	$configPath = substr(__FILE__,0,strrpos(__FILE__,"/"));
	include($configPath."/wp-config.php");
	$dbhost = constant('DB_HOST');
	$dbuser = constant('DB_USER');
	$dbpassword = constant('DB_PASSWORD');
	$dbname = constant('DB_NAME');

	// Names of files to check - must be path from docroot
	$fichiers = array('wp-config.php', '.htaccess', 'wp-login.php');
	// Names of tables to check lengths
	// Include 'wp_users' only if you do not permit comments, otherwise every new commenter
	// (including spam commenters) will cause this table to change size, and an alert may
	// be sent. (bummer)
	$tables = array('wp_posts', 'wp_links', ' wp_options', ' wp_postmeta', 'wp_terms'); // not 'wp_users'
	// Color to emphasize words on the page
	$emphasisColor = '#FF2222';
	$emphasisStart = "<span style='color:$emphasisColor;'>";
	$emphasisEnd   = "</span>";
	
	// When installed, test by viewing http://YOURDOMAIN/cyberspark-data.php from a web browser

//////////////////////////////////////////////////////
//  THE ACTIVE CODE
///////////////////////////////////////////////////

	try {

		if (MYSQLI) {

			$mysqli = mysqli_init();
			mysqli_real_connect ($mysqli, $dbhost, $dbuser, $dbpassword, $dbname);
			echo 'Using mysqli version '.mysqli_get_server_version($mysqli)."<br/><br/>\r\n";

			if ($mysqli == null) {
				die("Error: mysqli couldn't connect to MySQL on ".MYSQL_HOST." with user name and password specified.");
			}
        	echo "<b>CyberSpark.net </b> database scanner. Designed for WordPress sites.<br/>";
        	echo "<b>Database ".$dbname." :</b><br/>";
			echo "Connected to the database using mysqli() class.<br/><br/>\r\n";

			$stmt =  $mysqli->stmt_init();
			$query = "SHOW TABLES";
			$result = $stmt->prepare($query);

			$result = $stmt->execute();
			if ($stmt->errno) {
				echo "Error: [alert] number ".$stmt->errno." <br/>\r\n";
				echo "Error: [alert] message ".$stmt->error." <br/>\r\n";
				die ("Program ended.");
			}
			$result = $stmt->bind_result($tableName) ;
			$result = $stmt->store_result();
			if ($result) {
				if (($nt=$stmt->num_rows) > 0) {
					while($stmt->fetch()) {
        	     		echo "<!-- $tableName $tableName $tableName $tableName $tableName -->\r\n"; // Pour allonger la taille de la page, pour permettre la détection par l'outil de Jim
// get row count for display or to trigger a change in this page
						$stmtR  =  $mysqli->stmt_init();
						$queryR = "SELECT COUNT(*) FROM $tableName";
						$resultR = $stmtR->prepare($queryR);
						$resultR = $stmtR->execute();
						$resultR = $stmtR->bind_result($rowCount) ;
						$resultR = $stmtR->store_result();
						$resultR = $stmtR->fetch();
						if ($stmtR->errno) {
							echo "Error: [alert rows] number " .$stmtR->errno." <br/>\r\n";
							echo "Error: [alert rows] message ".$stmtR->error." <br/>\r\n";
						}
						$checked = '';
						if (WATCH_SOME_ROW_COUNTS) {
							if (WATCH_ALL_ROW_COUNTS || in_array($tableName, $tables)) {
	        	     			$j = $rowCount;
								echo "<!-- $tableName ";
								while ($j-- > 0) {
									echo '.........|';
								}
								$checked = " $emphasisStart(this table&rsquo;s row count can be monitored)$emphasisEnd";
								echo " -->\r\n"; // Pour allonger la taille de la page, pour permettre la détection par l'outil de Jim
							}
						}
						$stmtR->free_result();
						$stmtR->close();
						echo "$tableName [$rowCount rows] $checked<br/>\r\n";	
					}
		        	echo "<br />$emphasisStart There are $nt tables in database &ldquo;$dbname&rdquo; &mdash; is this what you expected?)$emphasisEnd<br /><br />";
				}
				$stmt->free_result();
				$stmt->close();
			}
			else {
		        echo "<br />$emphasisStart There are no tables in database &ldquo;$dbname&rdquo; &mdash; is this what you expected?)$emphasisEnd<br /><br />";
			}
			$mysqli->close();
		}
		else {
			/** With the help of -> http://www.w3schools.com/PHP/php_ref_mysql.asp **/
	        $connection = mysql_connect($dbhost, $dbuser, $dbpassword);
			if ($connection) {
        	    echo "<b>CyberSpark.net </b> database scanner. Designed for WordPress sites.<br/>";
        	    echo "<b>Database ".$dbname." :</b><br/>";
				echo "Connected to the database using (deprecated) mysql_connect().<br/>\r\n";
				mysql_select_db($dbname, $connection);

        	    $queryresult = mysql_query("SHOW TABLES");
        	    $i = 0;
        	    while($row = mysql_fetch_array($queryresult))
        	    {
        	     $tablename = $row["Tables_in_".$dbname];
        	     echo "<br />&nbsp;&nbsp;$tablename";
        	     echo " <!-- $tablename $tablename $tablename $tablename $tablename -->"; // Pour allonger la taille de la page, pour permettre la détection par l'outil de Jim
        	     $i++;
        	    }
        	    echo "$emphasisStart<br />There are $i tables in database &ldquo;$dbname&rdquo; &mdash; is this what you expected?)$emphasisEnd<br /><br />";
				mysql_close($connection);
			}
        	else
        	{
        	    echo "$emphasisStart<br/>Couldn't connect to the database &ldquo;$dbname&rdquo; &mdash; Error:".mysql_error()."$emphasisEnd<br /><br />";
        	}
		}
	}
	catch (Exception $nobase) {
	}

	echo "<b>Size of important files:</b> <br />";
	foreach ($fichiers as $key => $nomfichier) {
     	if (file_exists($nomfichier))
     	{
     	 $taille = filesize($nomfichier);
     	 echo $nomfichier . ': ' . $taille . ' bytes' . " <br />";
     	 echo "$nomfichier last modified : " . date ("d M Y H:i:s.", filemtime($nomfichier)). " <br />";
     	 // On met des points pour faire changer la taille de la page affichée au cas o˘ la taille du fichier changerait
     	 echo " <!-- ";
     	 for ($j = 0; $j < $taille; $j++)
     	  {
     	   echo ".....";
     	  }
     	 echo " --><br />";
     	}
	}
	echo "$emphasisStart<br/>The size of the raw HTML of this page will change if a database table is removed or added, if a monitored row count changes, or if an 'important file' changes.$emphasisEnd";
	echo '<br/><br/>Get CyberSpark.net source code from <a href="http://cyberspark.net/code">cyberspark.net/code</a>';	
	echo '    </font></blockquote></blockquote>
 </body>
</html>
';
?>