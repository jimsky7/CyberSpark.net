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

    <script type="text/javascript">
		var timer = 0;
		var counter=0;
		var timeWhenVisible =  <?php echo TIME_WHEN_VISIBLE; ?>;	/* a minute is 60000 */
		var timeWhenHidden  =  <?php echo TIME_WHEN_HIDDEN; ?>;	/* a minute is 60000 */
		var chartHash       =  '';
		function chart_float() {
			var MARGIN = 20;
			var titles      = document.getElementById("CS_TITLES");
			var titlesRect  = titles.getBoundingClientRect();
			var chartFrame  = document.getElementById("CS_CHART_IFRAME");
			var chartRect   = chartFrame.getBoundingClientRect();
			if ((titlesRect.right - titlesRect.left) > 320) {
				chartFrame.style.left = titlesRect.right - (chartRect.right-chartRect.left) - MARGIN;
			}
			else {
				chartFrame.style.left = 0;
			}
		}
		function chart_onload() {
<?php
			$chartHash = $hash;		// from POST values
//			$MDY = '';				// what?

			if (($chartHash != null) && (strlen($chartHash) > 0)) {
				echo "var elt=document.getElementById(\"CS_CHART_IFRAME\"); \r\n";	
				echo "elt.src=\"/a/index-cs-analysis-frame.php?$MDY"."URL_HASH=$chartHash\"; \r\n";	
				echo "chartHash=\"$chartHash\";\r\n";
			}	
?>
			chart_float();
		}
		function cs_onload() {
			timer = setInterval(cs_reload, (document.hidden) ? timeWhenHidden : timeWhenVisible);
			if(document.addEventListener) document.addEventListener("visibilitychange", visibilityChanged);

/* <a target="CS_CHART_IFRAME" NS1:href="index-cs-analysis-frame.php?URL_HASH=9898fb1b1d715f653ada37099b8c00c0"><circle r="3" cx="0" cy="15" style="stroke: white; fill: rgb(224, 224, 224); opacity: 0.9;"></circle></a> 
*/
		}
		function visibilityChanged() {
			clearTimeout(timer);
			timer = setInterval(cs_reload, (document.hidden) ? timeWhenHidden : timeWhenVisible);
		}
		function cs_reload() { 
			var URI = "<?php echo $_SERVER['REQUEST_URI']; ?>";
			var i   = URI.indexOf("?");
			if (i >= 0) {
				URI = URI.substring(0,i);
			}
			if (chartHash !== '') {
				window.location.href = URI+"?CS_CHART_HASH="+chartHash;
			}
			else {
				window.location.href = URI;
			}
		}
	</script>

</head>
<body style="font-family:'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', 'DejaVu Sans', Verdana, sans-serif;" onload="chart_onload();" >
<div style="margin-left:40px; width:80%;">
<a href="http://cyberspark.net/"><img src="https://viz.cyberspark.net/a/images/CyberSpark-banner-320x55.png" width="300" height="50" alt="CyberSpark web site"/></a>&nbsp;</p><p style="margin-left:40px; ">
For <?php echo $url; ?> <br/><br/>
				<iframe src="/a/index-cs-analysis-bubbles-iframe.php" id="CS_CHART_IFRAME" name="CS_CHART_IFRAME" width="320px" height="110px" style="z-index:3; margin-left:10px; border:thin; border-style:solid; border-color:#d0d0d0; background-color:none; opacity:0.80;"  >
				</iframe>
<hr/>
	<form id='SUPPRESS_EMAIL_ALERTS' action='<?php echo CS_SUPPRESS_POST_URL; ?>' method='post'>
		<div style='margin-left:40px;'>
			<table>
			<tr>
			<td>
			Suppress email alerts for<br/>
			<ul style='list-style-type:none; margin-left:20px; line-spacing:1.2;'>
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
						<label for='reset'>Nope, Turn 'em back on now!</label>
					</input>
				</li>
				<li>
					<input type='radio' id='reset' name='hours' value='0'>
						<label for='reset'>Show the current status</label>
					</input>
				</li>
			</ul>
			</td>
			<td>
			</td>
			</tr>
			</table>
		</div>
		<hr/>
		<div style='margin-left:40px; width:80%;'>
			<button type='submit' value='Go' />Do it</button>
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