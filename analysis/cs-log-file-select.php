<?php
include('cs-log-config.php');
include('cs-log-functions.php');
?>
<html>
<body>
Full path to file or directory: <form action='<?php echo CS_URL_FILE_PROCESS; ?>' method='POST'><input type='text' size='50' id='FILE_NAME' name='FILE_NAME' /><input name='submit' type='submit' /></form>
</body>
</html>