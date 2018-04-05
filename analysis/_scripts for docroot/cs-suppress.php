<?php 
	/**** Suppress email alerts for a URL
	****/
include_once 'a/cs-log-config.php';

	if (isset($_GET['hash']) && isset($_GET['url']) ) {
		$hash = $_GET['hash'];
		$url  = $_GET['url'];
	}
	else {
		echo 'Invalid request.';
		exit;
	}

?><html>
<head>
	<meta charset="utf-8" />
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
 	<script>
		<?php /**** from 
			https://stackoverflow.com/questions/1218245/jquery-submit-form-and-then-show-results-in-an-existing-div
 		****/ ?>
 		
		$(document).ready(function() {
			$('#SUPPRESS_EMAIL_ALERTS').submit(function() {
    			$.ajax({
        			data: $(this).serialize(),
        			type: $(this).attr('method'),
        			url: $(this).attr('action'),
        			success: function(response) {
        	   			$('#SUPPRESS_EMAIL_ALERTS_RESPONSE').html(response);
        			}
    			});
    			return false;
			});
		});
	</script>
</head>
<body style="font-family:'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', 'DejaVu Sans', Verdana, sans-serif;">
<div style="margin-left:40px; width:80%;">
<a href="http://cyberspark.net/"><img src="https://viz.cyberspark.net/a/images/CyberSpark-banner-320x55.png" width="300" height="50" alt="CyberSpark web site"/></a>&nbsp;</p><p style="margin-left:40px; ">
For the URL <?php echo $url; ?> <br/>
	<form id='SUPPRESS_EMAIL_ALERTS' action='<?php echo CS_SUPPRESS_POST_URL; ?>' method='post'>
		<div style='margin-left:40px;'>
			Suppress email alerts for<br/>
			<ul style='list-style-type:none; margin-left:20px;'>
				<li>
					<input type='radio' id='24hours' name='hours' value='24'>
					<label for='24hours'>One day</label>
					</input>
				</li>
				<li>
					<input type='radio' id='48hours' name='hours' value='48'>
						<label for='48hours'>Two days</label>
					</input>
				</li>
				<li>
					<input type='radio' id='72hours' name='hours' value='72'>
						<label for='72hours'>Three days</label>
					</input>
				</li>
				<li>
					<input type='radio' id='reset' name='hours' value='-1'>
						<label for='reset'>Enable alerts again immediately</label>
					</input>
				</li>
				<li>
					<input type='radio' id='reset' name='hours' value='0'>
						<label for='reset'>Just tell me the current status</label>
					</input>
				</li>
			</ul>
		</div>
		<div style='margin-left:40px; width:80%;'>
			<button type='submit' value='Go' />Go</button>
		</div>
		<input type='hidden' id='CS_API_KEY' name='CS_API_KEY' value='none'>
		<input type='hidden' id='md5_url' name='md5_url' value='<?php echo $hash; ?>'>
		<input type='hidden' id='url' name='url' value='<?php echo $url; ?>'>
	</form>
	</div>
	<div id="SUPPRESS_EMAIL_ALERTS_RESPONSE" style="margin-left:80px; width:60%; color:#888; padding:10px; border:thin; border-style:solid; border-width:1px; border-color:#888;">
	</div>
</body>
</html>