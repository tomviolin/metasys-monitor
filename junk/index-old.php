<!doctype html>
<?php
$mode="selected";
if (!isset($_GET["query"]) || $_GET["query"] == "selected") {
	$mode="selected";
} elseif ($_GET["query"]=="ALL") {
	$mode="ALL";
}
?>
<html>
<head>
<meta charset="utf-8">
<?php if ($mode == "selected") { ?>
	<!-- meta http-equiv="Refresh" content="5" -->
<?php } ?>
	<meta content="width=device-width, minimum-scale=0.9, maximum-scale=0.9" name="viewport">

	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">

<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<!-- (Start) Add jQuery UI Touch Punch -->
  <script src="jquery.ui.touch-punch.min.js"></script>
<!-- (End) Add jQuery UI Touch Punch -->

<script src="jquery.color-animation.js"></script>

<script src="https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.js"></script>
<script src="jquery.sprintf.js"></script>

<script src="howler.js"></script>
<script src="jquery.sparkline.js"></script>
<style type="text/css">
body { font-family: Helvetica,Arial Narrow,sans-serif; font-size: 10pt; }
.ui-li-count {
	font-size: 100%;
	line-height: 1.8em;
	xright: 0.1em;
	padding-right: .2em;
	padding-left: 0.2em;
	padding-top: 0;
	padding-bottom: 0;
	text-shadow: none;
}
.ui-listview > .ui-li-static.ui-li-has-count {
	padding-right: 0.2em !important;
}


.ui-listview>.ui-li-has-count>.ui-btn, .ui-listview>.ui-li-static.ui-li-has-count, .ui-listview>.ui-li-divider.ui-li-has-count {
	padding-right: 0.2em !important;
}
.ui-li-count, .ui-li-count.ok {
	background-color: #99ff99;
	color: black;
}
.ui-li-count.error_0 {
	background-color: #ffdddd;
	color: #664444;
	text-shadow: none;
}
.ui-li-count.error {
	background-color: #ff7777;
	color: black;
	text-shadow: none;
}
.ui-li-count.warning {
	background-color: #ffff88;
	color: black;
}

.ui-li-count {
	right: 0;
}
.points-datagrouup {
}
.points-placeholder {
	outline: 1px solid black;
	height: 1.8em;
	xwidth: 100%;
	background-color: #ff0;
}
.points-group-placeholder {
	width: 12.65em;
	height: 12.65em;
	float:left;
	background-color: #ff0;

}
.ui-sortable-handle, span.points-heading-text {
	cursor: move !important;
}
.ui-listview.floaty {
	float: left;
	width: 15.0em;
	max-width: 15.0em;
	margin-right: 2px;
	xmargin-left: 0.5%;
	margin-top: 2px;
	min-width: 15.0em;
}
span.x-list-item {
	font-weight: bold;
	font-size: 95%;
}
.ui-listview>.ui-li-static {
	padding: 0.25em 0.1em 0.25em 0.3em;
	background-color: #eee;
}
.ui-header .ui-title {
	font-size: 1.2em;
	padding-top: 0.4em;
	padding-bottom: 0.4em;
}
.ui-header {
	background-image: url('circuitboardblue.png');
	background-repeat: repeat;
}
.sparkline > canvas { background-color: #ffc; 
	xheight: 30px !important;
	xwidth: 30px !important;
	margin: auto;
	bottom: 0px;
	vertical-align: middle !important;
	xmargin-left: 0.4em;
	height: 1.4em !important;
	padding: 00px;
	margin:0;
}
#popspark { text-align: center; }
#popspark > :first-child {
	background-color: #fff;
}
</style>
<script>

var itHandle = null;
var points={};
var pointsindex=0;
var changeSound;
var alarmSound;
var openSound;
var closeSound;
var normalSound;
function isNumeric(x){
	var xx=$.trim(x);
	if (xx != "" && isFinite(xx)) return true;
	return false;
}

function init_values() {
	itHandle = window.setInterval(update_values, 5000);
/* -- soundManager --
soundManager.setup({
  preferFlash: false,
  onready: function() {
    // Ready to use; soundManager.createSound() etc. can now be called
    changeSound = soundManager.createSound({
      url: 'sounds/softding.mp3',
	id: 'softding',
	pan: -100
    });
    changeSound.load();
  }
});
*/
	changeSound = new Howl({
		urls: ['change.mp3']
	});
	normalSound = new Howl({
		urls: ['star_trek_warble.mp3']
	});
	alarmSound = new Howl({
		urls: ['star_trek_red_alert.mp3']
	});
	openSound = new Howl({
		urls: ['tos_swoosh.mp3']
	});
	//closeSound = new Howl({
	//	urls: ['tos_close.mp3']
	//});

	$(".sparkline").sparkline([null],{defaultPixelsPerValue: 1});
	$(".sparkline").bind('sparklineClick', function(e) {
		openSound.play();
		var pindex = $(e.target).parents('.x-dval').attr('id');
		var desc = $('#'+pindex).siblings('.desc').html();
		var value = $('#'+pindex).children('#b').html();
		$('#poptext').html(desc);
		$('#popGraph').popup('open').on("popupafterclose",function(){closeSound.play();});
		var data=points[pindex];
		var sparkopts={fillColor: false, normalRangeColor: '#ccffcc', height:'100px', width:'200px'};
		if (	isNumeric(data.soft_min) && isNumeric(data.soft_max) &&
			isNumeric(data.hard_min) && isNumeric(data.hard_max)) {
			sparkopts.chartRangeMin = data.hard_min;
			sparkopts.chartRangeMax = data.hard_max;
			sparkopts.normalRangeMin = data.soft_min;
			sparkopts.normalRangeMax = data.soft_max;
		}
		$('#popspark').sparkline(points[pindex].values, sparkopts).css({marginLeft: 'auto', marginRight: 'auto'});
		$('#popstats').html('Soft: '+data.soft_min+'-'+data.soft_max+'; Hard: '+data.hard_min+'-'+data.hard_max+" NOW:"+value);
		//$('#popstatsleft').html('soft<br>&nbsp;<br>'+data.soft_min+'<br>'+data.soft_max);
		$('#popstatsright').html('<span class="hardstats">'+data.hard_max+'</span><br><span class="softstats">'+data.soft_max+'</span><br>'+value+'<br>'+data.soft_min+'<br>'+data.hard_min);
	});
//	$("#popGraph").popup();
	window.setTimeout(update_values,0);
}

var nsc=2;
function update_values(){

	$.getJSON("newgetdata.php", 
		function(datx) {
				nsc++; if (nsc>2) {
					normalSound.play();
					nsc=0;
				}
				$.each(datx, function(data_index,data) {
					data_id = 'dval' + data_index;
					console.log("data_id=",data_id,"data=",data);
					if (data.value != ' ') {
						var cell=$('#'+data_id+" #b");
						var cella=$('#'+data_id+" #a");
						var oldvalue = cell.html();
						cell.html(data.value);
						cell.parent()
							.removeClass("error error_0 error_1 error_2 error_3 error_4 error_5 warning ok")
							.addClass(data.cerr);
						cell.parent().parent().parent().css({backgroundColor: '', color: ''});
						var newvalue = cell.html();
						if (newvalue != oldvalue) {
							timeoutValue=2;
							var lv = cell.parents('ul');
							//console.log('left=',$(lv).offset().left);
							//console.log('width=',$(lv).width());
							//console.log('doc width=',$(document).width());
							var pan=(($(lv).offset().left + $(lv).width()/2)/$(document).width()) * 2 - 1;
							pan*= 2;
							changeSound.stop();
							changeSound.pos3d(pan,0,0);
							changeSound.play();
							if ((newvalue == "active") || (newvalue > oldvalue && newvalue != "inactive")) {
								cella.html('&#9650;');
								cella.css({color: '#00AA00'});
							} else {
								cella.html('&#9660;');
								cella.css({color: '#ff3333'});
							}
							cell=cell.parent().parent().parent();
							backcolor = cell.css('backgroundColor');
							cell.css({
								backgroundColor: '#ff0'
								});
							cell.animate({backgroundColor: backcolor}, 1000);
							cell.css('color','#990000');
							/*window.setTimeout(function(){
								cell.css({
									backgroundColor: backcolor
									});
							}, 2000);*/
						} else {
							cella.html('');
						}
						console.log("cerr=",data.cerr);
						if (data.cerr == "error") {
							console.log("ERROR!");
							normalSound.stop();
							alarmSound.stop();
							alarmSound.volume(0.3);
							alarmSound.play();
						}
						var val;
						val=$.trim(data.value).split(' ')[0]-0;
						console.log("val=",val);
						console.log(points[data_id]===undefined);
						if ((points[data_id])===undefined || (points[data_id].values)===undefined) {
							
							points[data_id] = {values:[null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,val]};
						} else {
							points[data_id].values.push(val);
							while (points[data_id].values.length > 20) {
								points[data_id].values.shift();
							}
						}
						points[data_id].soft_min = data.soft_min;
						points[data_id].soft_max = data.soft_max;
						points[data_id].hard_min = data.hard_min;
						points[data_id].hard_max = data.hard_max;

						sparkopts = {fillColor:false,defaultPixelsPerValue:1,normalRangeColor:'#cfc'};
						if (	isNumeric(data.soft_min) && isNumeric(data.soft_max) &&
							isNumeric(data.hard_min) && isNumeric(data.hard_max)) {
							sparkopts.chartRangeMin = data.hard_min;
							sparkopts.chartRangeMax = data.hard_max;
							sparkopts.normalRangeMin = data.soft_min;
							sparkopts.normalRangeMax = data.soft_max;
						}
							
						$('#'+data_id+" .sparkline").sparkline(points[data_id].values,sparkopts);
					}
				});

		}


	);

}


function group_sort_handler(e,ui) {
	//console.log("*** update:",e,ui);
	var newitems = $(e.target).children().children('[data-role=list-divider]').children('.points-heading-text');
	var querystring="";
	for (i=0; i<newitems.length; ++i) {
		querystring += "&item"+i+"="+escape($(newitems[i]).html());
	}
	console.log(querystring);
	$.getJSON("updateheadings.php?token=x" + querystring, function() {
		console.log('command completed.');
		for (i=0;i<newitems.length;++i) {
			$(newitems[i]).html($.sprintf("%02d ",i+1)+$(newitems[i]).html().substr(3));
		}
	}).error(function() {
		console.log('command FAILED');
		alert("server error. unable to update positions.");
	});
}

function item_sort_handler(e,ui) {
	console.log(e,ui);
	console.log("receiver: ",e.target);
	var items = $(e.target).children('.point-item');
	console.log(items);
	var itemList = "";
	for (i=0; i < items.length; ++i) {
		itemList += ","+$(items[i]).attr("id").substr(2);
	}
	itemList = itemList.substr(1);
	console.log(itemList);
	var heading = $(items[0]).siblings('.ui-li-divider').children('.points-heading-text').html();
	console.log('heading=',heading);
	$.getJSON("updateitems.php?token=x&heading="+escape(heading)+"&items="+itemList, function() {
		console.log('command completed.');
	}).error(function () {
		console.log('database error.');
		alert('Database error!');
	});
}

$(function() {
	//document.documentElement.requestFullscreen();
	window.setTimeout(init_values,1);
<?php if ($mode != "ALL") { ?>

	$('.points-listview').sortable({handle: '.points-heading-text',
		placeholder: 'points-group-placeholder',
		update: group_sort_handler});
	$('.points-list').sortable({handle: '.desc', 
		connectWith: '.points-list',
		placeholder: 'points-placeholder',
		update: item_sort_handler});
<?php } ?>
});
</script>


</head>

<body>
<div data-role='page'>
<div data-role="header" xclass="ui-bar" data-theme="b" data-position="fixed">
<?php if ($mode=="ALL") { ?>
	<a href="?query=selected" class="ui-btn-left" data-ajax="false" data-role="button" data-theme="<?=$mode=="selected"?"a":"b"?>" data-icon="back" data-iconpos="notext">back</a>
	<h1>Metasys Search</h1>
<?php } ?>
<?php if ($mode=="selected") { ?>
	<h1>Metasys Monitor</h1>
	<a href="?query=ALL" class='ui-btn-right' data-role="button" data-icon="search" data-ajax="false" data-iconpos="notext">data-theme="<?=$mode=="ALL"?"a":"b"?>">Search</a>
<?php } ?>
</div>
<div class="content">
<div class="points-listview">
<?php

$conn = mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
mysql_select_db("metasys");
if ($mode=="selected") {
	$result = mysql_query("select d.recid as disp_recid, d.heading,d.functional_name as dis_functional_name, d.description as dis_description,d.priority, d.soft_min_value,d.soft_max_value, d.hard_min_value, d.hard_max_value, a.*, i.ip_address, object_types.object_id as object_type_id from display_points d LEFT JOIN allpoints a ON d.functional_name = a.functional_name LEFT JOIN devices i ON a.device_id = i.device_id LEFT JOIN object_types ON a.object_type = object_types.object_name order by d.heading, d.sortkey");
} elseif ($mode == "ALL") {
	$squ = "";
	if (isset($_GET['start'])) {
		$start=$_GET['start'] + 0;
	} else {
		$start=0;
	}
	if (isset($_GET['search'])) {
		$search = $_GET['search'];
		$squ = "where a.description like '%$search%' or a.functional_name like '%$search%'";
	}
	if ($search== "") {
		$title="Search";
		$query= "select '' as heading";
	} else {
		$title="Search Results for ".htmlentities($search);
		$query = ("select a.recid as disp_recid, '$title' as heading,a.functional_name as dis_functional_name, a.description as dis_description, a.device_id, a.object_id, a.object_type, i.ip_address, object_types.object_id as object_type_id from allpoints a LEFT JOIN devices i ON a.device_id = i.device_id LEFT JOIN object_types ON a.object_type = object_types.object_name $squ  order by heading, disp_recid limit $start,100");
	}
	$result = mysql_query($query);
	$count = mysql_num_rows($result);
	if ($search != "") {
		$title .= " (showing ".($start+1)."-".($start+$count).")";
	}
}

if ($mode=="selected" || $search != "") {
$heading = "";

//echo '<ul data-role="listview" data-count-theme="b" data-inset="true">';
$openlist = '<ul class="'.($mode=="selected"?"floaty points-list":"").'" data-role="listview" data-inset="true" data-count-theme="a" data-divider-theme="b">';
$closelist = "</ul>\n";
//echo $openlist;
$recount=0;
while ($row = mysql_fetch_array($result)) {
	$recount++;
	if ($mode=="selected") {
		$newheading = $row['heading'];
	} else {
		$newheading = $title;
	}
	if ($newheading != $heading) {
		$oldheading=$heading;
		$heading = $newheading;
		if ($oldheading != "") echo $closelist;
		echo $openlist;
		echo "<li style='background-color: #666699;' data-role='list-divider'><span class='points-heading-text'>$heading</span></li>\n";
	}
	$fsize=0;
	if ($search != "") $fsize = 80;
	if ($row['description']!="") $row['dis_description'];
	$cerr = "";
	if ($row['ip_address'] == "") {
		if ($row['dis_functional_name']!=""){
			$link = "?query=ALL&search=".urlencode($row['dis_functional_name']);
		} else {
			$link = "?query=ALL&search=".urlencode($row['dis_description']);
		}
		$countent = "".$row['dis_functional_name']." n/f";
		$cerr = "error";
	} else {
		$link="";
		$command = 'cd /home/tomh/projects/bacnet; BACNET_BBMD_ADDRESS='.$row['ip_address'].' ./bin/bacrpm '.$row['device_id'].' '.$row['object_type_id'].' '.$row['object_id'].' 85 '.$row['object_type_id'].' '.$row['object_id'].' 117';
		$devinfo = $row['ip_address'].':'.$row['device_id'].' '.$row['object_type_id'].' '.$row['object_id'].' 85 '.$row['object_type_id'].' '.$row['object_id'].' 117';
		$countent = trim(`$command 2>/dev/null`);
		$clines=explode("\r\n",$countent);
		$c1 = explode(":",$clines[2]);
		$c2 = explode(":",$clines[3]);
		$c2=trim($c2[1]);
		switch ($c2) {
		case "BACnet Error": $c2=""; break;
		case "degrees-fahrenheit": $c2="&deg;F"; break;
		case "degrees-celsius": $c2="&deg;C"; break;
		case "inches-of-water": $c2="\"H<sub>2</sub>O"; break;
		case "cubic-feet-per-minute": $c2="CFM"; break;
		case "percent-relative-humidity": $c2 = "%RH"; break;
		case "percent": $c2="%"; break;
		case "us-gallons-per-minute": $c2="gpm"; break;
		case "pounds-force-per-square-inch": $c2="psi"; break;
		case "revolutions-per-minute": $c2="rpm"; break;
		case "amperes": $c2="Amps"; break;
		case "megawatt-hours": $c2="MWh"; break;
		case "kilowatt-hours": $c2="KWh"; break;
		}
		if (is_numeric($c1[1])) {
			$c1 = sprintf("%.02f",$c1[1]);
		} else {
			$c1 = $c1[1];
		}
		$countent = $c1 ." ".$c2;
	}

	echo '<li id="li'.$row['disp_recid'].'" xxdata-icon="false" class="point-item"><span style="xpadding:0" class="x-list-item" href="'.$link.'"><span class="desc">'.$row['priority'].":".$row['dis_description']."</span><span style='font-size: $fsize%'>(".$row['dis_functional_name'].")</span> ";
	echo "<span class='x-dval ui-li-count $cerr' id=\"dval".$row['recid']."\" xdata-info=\"".$devinfo."\" title='dval".$row['recid'].": ".$row['dis_functional_name']." - ".$row['description']." soft:".$row['soft_min_value']."-".$row['soft_max_value']."; hard:".$row['hard_min_value']."-".$row['hard_max_value']."'><span id='a'></span><span id='b'>$countent</span>";
	echo "<!-- ".$row['object_type']."-->\n";
	if (preg_match('/(binary)|(multi)/',$row['object_type']) != 1) echo "<span class='sparkline'></span>";
	echo "</span></span>";
	echo "</li>\n";
}
if ($recount==0){
	echo "<li>The search <b>$search</b> returned no results.</li>";
}
echo "</ul>\n";

} 
?>
</div>

<?php
	if ($mode == "ALL") {
?>
<div data-role="footer" class="ui-bar" data-theme="b" data-position="fixed">
<form method="get" class="ui-grid-a" x-ajax="false">
	<input type="hidden" name="query" value="ALL">
	<input type="hidden" name="start" value="0">
	<div class="ui-block-a" style="width: 30%;">
<?php if ($start > 0) { ?>
		<a data-role="button" title="Previous 100 rows" data-icon="arrow-l" data-iconpos="notext" href="?query=ALL&start=<?= $start-100 ?>&search=<?= htmlentities(urlencode($search))?>">-100</a>
<?php } ?>
<?php if ($recount >= 100) { ?>
<?php if ($start == 0) { ?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?php } ?>
		<a data-role="button" title="next 100 rows" data-icon="arrow-r" data-iconpos="notext" href="?query=ALL&start=<?= $start+100 ?>&search=<?= htmlentities(urlencode($search))?>">+100</a> <?php } ?>
	</div>
	<div class="ui-block-b" style="width: 50%;">
		<input data-theme="a"type="text" name="search" value="<?= htmlentities($_GET['search'])?>">
	</div>
	<div class="ui-block-c">
		<button type="submit" data-icon="search" data-iconpos="notext">&nbsp;</button>
	</div> <!-- /ui-block-c -->
</form>
<?php if ($search=="") { ?>
<script> 
//window.setTimeout(function(){ document.forms[0]['search'].focus(); }, 200);
//window.setTimeout(function(){ document.forms[0]['search'].focus(); }, 500);
window.setTimeout(function(){ document.forms[0]['search'].focus(); }, 900);

</script>
<?php } else { 
?>

<script>
window.setTimeout(function() {
//	$('#recs').html("(<?= $start+1 ?>-<?= $start + $recount  ?>)");
}, 3000);
</script>

<?php } } ?>
</div> <!-- /points-listview -->
<div data-role="popup" id="popGraph" style="min-width: 24em" data-transition="pop">
<div data-role="header" data-theme="b" style="text-align:center;"><h1 style="margin: 0em;" id='poptext'>hi. this is test!! Please respond</h1></div>
<div data-role="content" style="background-color: #eeeeee">
<div style='float:left' id='popstatsleft'></div><div style='float:left'><span id='popspark' style='background-color: #eee' style='float:left;marginleft:0px;margin-right:0px;'></span></div><div id='popstatsright' style="whitespace: nowrap"></div><br clear=all>
</div>
<div data-role="footer" style='text-align: center;'><span id='popstats'></span></div>
</div>

</div> <!-- /ui-content -->
</div> <!-- /ui-page -->
</body>
</html>
