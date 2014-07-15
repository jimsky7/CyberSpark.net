<html>
<head>
	<!-- D3js version 3.4.8 -->
	<script src="/d3/d3.min.js" charset="utf-8">
    </script>
    <style>
		#chart div {
  			font: 10px sans-serif;
  			background-color: steelblue;
  			text-align: right;
  			padding: 3px;
  			margin: 1px;
  			color: white;
		}
		#chart2 div {
  			font: 10px sans-serif;
  			background-color: #CD5355;
  			text-align: right;
  			padding: 3px;
  			margin: 1px;
  			color: white;
		}

.chart1 rect {
  fill: #EED13A;
}

.chart1 text {
  fill: #C57B03;
  font: 10px sans-serif;
  text-anchor: end;
}

        </style>
</head>
<body style="padding-left:40px;">
	<p>
&laquo; <a href='/analysis/'>Back</a> 
	</p>
    <p>
Note:    http://slice.red7.com/get-log-entries.php?URL_HASH=b3eaf4bce2b85708a6c930a5d527340d&span=P1W&limit=17
	</p>
    <p id="section">
    </p>
	<div id="chart">
    </div>
<svg class="chart1"></svg>
	<div id="chart2">
    	<table cellspacing='0' cellpadding='0' border='0'>
        	<tr id="chart2row">
            	<td>
                </td>
            </tr>
         </table>
    </div>
    
    <script type="text/javascript">
	
	
	var data = [4, 8, 15, 16, 23, 42];
	
	/* test DIV */
	var section = d3.selectAll("body");
	var div = section.append("div");
	div.html("Hello there from D3!");
	div.style("color","magenta");
	div.style("font-weight","bold");
	
	/* test chart */
	var chart = d3.selectAll("#chart");
	var bar = chart.selectAll("div");
	var barUpdate = bar.data(data);
	
	var x = d3.scale.linear()
    	.domain([0, d3.max(data)])
    	.range([0, 320]);
	
	var barEnter = barUpdate.enter().append("div");
		barEnter.style("width", function(d) { return x(d) + "px"; });
		barEnter.text(function(d) { return d; });
		
	/* test chart 1 */

var width = 420,
    barHeight = 20;
var x1 = d3.scale.linear()
    .domain([0, d3.max(data)])
    .range([0, width]);
var chart1 = d3.select(".chart1")
    .attr("width", width)
    .attr("height", barHeight * data.length);
var bar1 = chart1.selectAll("g")
    .data(data)
  .enter().append("g")
    .attr("transform", function(d, i) { return "translate(0," + i * barHeight + ")"; });
bar1.append("rect")
    .attr("width", x1)
    .attr("height", barHeight - 1);	
bar1.append("text")
    .attr("x", function(d) { return x1(d) - 3; })
    .attr("y", barHeight / 2)
    .attr("dy", ".35em")
    .text(function(d) { return d; });	
	
	/* test chart 2 */
	var chart2 = d3.selectAll("#chart2");
	var bar2 = chart2.selectAll("row");
	var barUpdate2 = bar2.data(data);
	
	var barEnter2 = barUpdate2.enter().append("td");
		barEnter2.style("height", function(d) { return x(d) + "px"; });
		barEnter2.style("width", "10px");
		barEnter2.style("background-color", "#CD5355");
		barEnter2.text(function(d) { return d; });
		
		
		
		
	</script>

</body>
</html>