<?php

////////////////////////////////////////////////////////////////////////
// Page footer

?>
<!-- common footer begins -->
<?php if (!isset($skipHR) || !$skipHR) { ?><hr/><?php } ?>
<div id="CS_FOOTER_NARROW">
<table id='LEGEND_NARROW' cellspacing='0' cellpadding='0' border='0' style='width:100%; max-width:<? echo CHART_NARROW; ?>px; font-size:11px;'>
		<tr>
			<td class="rect_green"   style="height:20px; width:55px; color:White;">&nbsp;&nbsp;seconds</td>
			<td class="rect_yellow"  style="height:20px; width:35px; color:black;">&nbsp;&nbsp;<?php echo CHART_YELLOW; ?> +</td>
			<td class="rect_orange"  style="height:20px; width:35px; color:White;">&nbsp;&nbsp;<?php echo CHART_ORANGE; ?> +</td>
			<td class="rect_red"     style="height:20px; width:35px; color:White;">&nbsp;&nbsp;<?php echo CHART_RED; ?> +</td>
			<td                      style="height:20px; width:10px;"></td>
			<td class="rect_blue"    style="height:20px; width:45px; color:White;">&nbsp;HTTP</td>
			<td                      style="height:20px; width:5px;"></td>
			<td class="rect_magenta" style="height:20px; width:50px; color:White;">&nbsp;Timeout</td>
			<td                      style="height:20px; width:5px;"></td>
			<td class="rect_cyan"    style="height:20px; width:40px; color:black;">&nbsp;Fail</td>
		</tr>
<?php if (!isset($skipTips) || !$skipTips) { ?>
        <tr>
        	<td colspan="12" style="padding:8px; border:thin; border-style:solid; border-width:1px; border-color:#d0d0d0;">            <b>Charts:</b><br/>
            &mdash;Mouseover (or tap) the colored bars to see details.<br/>
        	&mdash;Click (or tap) grey box at corner of a chart to view the associated URL.<br/>
       	    &mdash;Resize or maximize and the charts will float to fill the window.<br/>
         	<b>Bubbles:</b><br/>
            &mdash;Mouseover (or tap) a bubble's name to see detail.<br/>
            &mdash;Click (or tap) the center dot in a bubble to see its chart.<br/>
        	</td>
        </tr>
<?php } ?>
<?php if (!isset($skipCC) || !$skipCC) { ?>
       <tr>
        	<td colspan="12"><a href="http://creativecommons.org/licenses/by-nc-sa/3.0/" target="_blank"><img src="images/CC-by-nc-sa-88x31.png" width="88" height="31" alt="Creative Commons license" style="margin-top:10px;" /></a>
        	</td>
        </tr>
<?php } ?>
	</table>
    </div>
    <div id="CS_FOOTER_WIDE" style="float:left; width:100%;">
    <table id="LEGEND_WIDE" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:<? echo CHART_WIDE; ?>px; font-size:11px; margin-bottom:4px;">
		<tr>
			<td colspan="8" style="height:1px;">&nbsp;
			</td>
		</tr>
		<tr>
			<td class="rect_green"   style="height:20px; width:60px;"></td>
			<td class="rect_yellow"  style="height:20px; width:60px;"></td>
			<td class="rect_orange"  style="height:20px; width:60px;"></td>
			<td class="rect_red"     style="height:20px; width:60px;"></td>
			<td                      style="height:20px; width:5px;"></td>
			<td class="rect_blue"    style="height:20px; padding-left:5px;"></td>
			<td                      style="height:20px; width:5px;"></td>
			<td class="rect_magenta" style="height:20px;"></td>
			<td                      style="height:20px; width:5px;"></td>
			<td class="rect_cyan"    style="height:20px;"></td>
		</tr>
		<tr>
			<td style="height:4px;">&nbsp;seconds</td>
 			<td style="height:4px; border-left:thin; border-left-color:grey; border-left-style:solid;">&nbsp;
 			</td>
			<td style="height:4px; border-left:thin; border-left-color:grey; border-left-style:solid;">&nbsp;
			</td>
			<td style="height:4px; border-left:thin; border-left-color:grey; border-left-style:solid;">&nbsp;
			</td>
			<td>
			</td>
			<td style="">&nbsp;&nbsp;HTTP error
			</td>
			<td>
			</td>
			<td style="">&nbsp;&nbsp;Timeout
			</td>
			<td>
			</td>
			<td style="">&nbsp;&nbsp;Connection failed or refused
			</td>
		</tr>
		<tr>
			<td colspan="8" style="">
				<table cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td style="height:20px; width:58px; font-size:10px;">0</td>
						<td style="height:20px; width:60px; font-size:10px;"><?php echo CHART_YELLOW; ?></td>
						<td style="height:20px; width:60px; font-size:10px;"><?php echo CHART_ORANGE; ?></td>
                        <td style="height:20px; width:60px; font-size:10px;"><?php echo CHART_RED; ?></td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td colspan="8" style="height:4px;">&nbsp;
			</td>
		</tr>
<?php if (!isset($skipTips) || !$skipTips) { ?>
        <tr>
        	<td colspan="12" style="padding:8px; border:thin; border-style:solid; border-width:1px; border-color:#d0d0d0;">            <b>Charts:</b><br/>
            &mdash;Mouseover (or tap) the colored bars to see details.<br/>
        	&mdash;Click (or tap) grey box at corner of a chart to view the associated URL.<br/>
        	&mdash;Resize or maximize and the charts will float to fill the window.<br/>
        	<b>Bubbles:</b><br/>
            &mdash;Mouseover (or tap) a bubble's name to see detail.<br/>
            &mdash;Click (or tap) the center dot in a bubble to see its chart.<br/>
        	</td>
        </tr>
<?php } ?>
<?php if (!isset($skipCC) || !$skipCC) { ?>
       <tr>
        	<td colspan="12">
            <table cellspacing="0" cellpadding="5px" border="0">
            <tr><td>
            <a href="http://creativecommons.org/licenses/by-nc-sa/3.0/" target="_blank"><img src="images/CC-by-nc-sa-88x31.png" width="88" height="31" alt="Creative Commons license" style="margin-top:10px;" /></a></td>
            <td style="font-size:12px; padding-top:12px;">CyberSpark open source code is provided under a <a href="http://creativecommons.org/licenses/by-nc-sa/3.0/" target="_blank">Creative Commons by-nc-sa 3.0</a> license
            </td>
            </tr>
            </table>
        	</td>
        </tr>
<?php } ?>
	</table>
</div>
<!-- common footer ends -->
