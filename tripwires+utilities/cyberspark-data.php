<?php
echo '<html>
 <head>
  <title>PHP-MySQL tripwire</title>
 </head>
 <body>
  <div align="justify"><blockquote><blockquote><font face="Arial" size=2><br /><br /><br />
';
	// THANKS to Chris for refining this script!
    // On va afficher les tables de la base
    // Pour le watchdog de Jim Schuyler
    // Si la taille de cette page change, c'est que le nombre de tables a changé
    // ou que la taille de login.php a changé
	   
	// This file must be installed at the web server's docroot for the monitored site
	
	/** Version 4.03 on 2011-09-15 **/
	
//////////////////////////////////////////////////////
//  INSTRUCTIONS
///////////////////////////////////////////////////
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
	$fichiers = array('wp-config.php','.htaccess');

	// When installed, test by viewing http://YOURDOMAIN/cyberspark-data.php from a web browser
//////////////////////////////////////////////////////
//  INSTRUCTIONS
///////////////////////////////////////////////////







	try {
		
		/** With the help of -> http://www.w3schools.com/PHP/php_ref_mysql.asp **/
        $connection = mysql_connect($dbhost,$dbuser,$dbpassword);
        // We do not need this any longer, unset for safety purposes
        unset($dbpassword);

        if ($connection) {
            echo "<b>CyberSpark.net </b> database scanner. Designed for WordPress sites.";
            echo "<b>Database ".$dbname." :</b>";
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
            mysql_close($connection);
            echo "<br /><b>There are $i tables in database $dbname. (Is this what you expected?)</b><br /><br />";
        }
        else
        {
            echo "Couldn't connect to the database. Error:".mysql_error()."<br /><br />";
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
	echo "The size of this page will change if a table is removed or added or if an 'important file' changes.";
	echo '    </font></blockquote></blockquote>
 </body>
</html>
';
?>