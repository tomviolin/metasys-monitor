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
<script src="//cdn.jsdelivr.net/jquery.color-animation/1/mainfile"></script>

<script src="https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.js"></script>
<script src="howler.js"></script>
<script src="jquery.sparkline.js"></script>
<style type="text/css">
body { font-family: elvetica,Arial Narrow,sans-serif; }
.ui-li-count {
	font-size: 100%;
	xright: 0.1em;
	padding-right: .2em;
	padding-left: 0.2em;
	padding-top: 0;
	padding-bottom: 0;
	background-color: #99ff99;
	color: black;
	text-shadow: none;
}
.ui-li-count.error {
	background-color: #ff9999;
	color: black;
	text-shadow: none;
}
.ui-li-count.active {
	background-color: #ffff99;
	color: black;
}
.ui-li-count.inactive {
	background-color: #cccccc;
	color: #333333;
	text-shadow: none;
}
.ui-li-count {
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
.sparkline > :first-child { background-color: #fff; 
	xheight: 30px !important;
	xwidth: 30px !important;
	margin: auto;
	bottom: 0px;
	vertical-align: bottom !important;
	xmargin-left: 0.4em;
	height: 1.2em !important;
}
#popspark { text-align: center; }
#popspark > :first-child {
	background-color: #fff;
}
</style>
<script>

var points=[];
var pointsindex=0;
var changeSound;
var alarmSound;

function init_values() {
	window.setInterval(update_values, 2000);
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
		urls: ['sounds/softding.mp3','sounds/softding.ogg']
	});

	$(".sparkline").sparkline([null],{defaultPixelsPerValue: 1});
	$(".sparkline").bind('sparklineClick', function(e) {
		console.log('clicked!');
		console.log(e);
		var pindex = $(e.target).parents('.x-dval').attr('id');
		console.log("pindex=",pindex);
		var desc = $('#'+pindex).siblings('.desc').html();
		console.log('desc=',desc);
		$('#poptext').html(desc);
		$('#popGraph').popup('open');
		$('#popspark').sparkline(points[pindex], {height:'100px', width:'200px'}).css({marginLeft: 'auto', marginRight: 'auto'});
	});
//	$("#popGraph").popup();
}


function update_values(){

	$.getJSON("newgetdata.php", 
		function(datx) {
			$.each(datx, function(data_index,data) {
				data_id = 'dval' + data_index;
				if (data.value != ' ') {
					var cell=$('#'+data_id+" #b");
					var cella=$('#'+data_id+" #a");
					var oldvalue = cell.html();
					cell.html(data.value);
					cell.parent()
						.removeClass("error active inactive")
						.addClass(data.cerr);
					cell.parent().parent().parent().css({backgroundColor: '', color: ''});
					var newvalue = cell.html();
					if (newvalue != oldvalue) {
						timeoutValue=2;
						var lv = cell.parents('ul');
						console.log('lv',lv);
						console.log('left=',$(lv).offset().left);
						console.log('width=',$(lv).width());
						console.log('doc width=',$(document).width());
						var pan=(($(lv).offset().left + $(lv).width()/2)/$(document).width()) * 2 - 1;
						pan*= 2;
						console.log("pan:",pan);
						changeSound.pos3d(pan,0,0);
						//changeSound.play();
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
					var val;
					if (data.value == " active ") val=1;
					else if (data.value==" inactive ") val=2;
					else {
						val=$.trim(data.value).split(' ')[0]-0;
					}
					if (!(points[data_id])) {
						points[data_id]=[null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,val];
					} else {
						points[data_id].push(val);
						while (points[data_id].length > 20) {
							points[data_id].shift();
						}
					}
					
					$('#'+data_id+" .sparkline").sparkline(points[data_id],{defaultPixelsPerValue:1});
				}
			});

		}


	);

}

$(function() {
	//document.documentElement.requestFullscreen();
	window.setTimeout(init_values,1000);
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

<?php

$conn = mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
mysql_select_db("metasys");
if ($mode=="selected") {
	$result = mysql_query("select d.recid as disp_recid, d.heading,d.functional_name as dis_functional_name, d.description as dis_description, a.*, i.ip_address, object_types.object_id as object_type_id from display_points d LEFT JOIN allpoints a ON d.functional_name = a.functional_name LEFT JOIN devices i ON a.device_id = i.device_id LEFT JOIN object_types ON a.object_type = object_types.object_name order by heading, d.recid");
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
$openlist = '<ul class="'.($mode=="selected"?"floaty":"").'" data-role="listview" data-inset="true" data-count-theme="a" data-divider-theme="b">';
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
		echo "<li style='background-color: #666699;' data-role='list-divider'>$heading <span id='recs'></span></li>\n";
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
		switch (trim($countent)) {
		case "active": $cerr="active"; break;
		case "inactive": $cerr="inactive"; break;
		}
	}

	echo '<li data-icon="false"><span style="padding:0" class="x-list-item" href="'.$link.'"><span class="desc">'.$row['dis_description']."</span> <span style='font-size: $fsize%'>(".$row['dis_functional_name'].")</span> ";
	echo "<span class='x-dval ui-li-count $cerr' id=\"dval".$row['recid']."\" xdata-info=\"".$devinfo."\" title='dval".$row['recid'].": ".$row['dis_functional_name']." - ".$row['description']."'><span id='a'></span><span id='b'>$countent</span>";
	echo "<!-- ".$row['object_type']."-->\n";
	if (preg_match('/binary/',$row['object_type']) != 1) echo "<span class='sparkline'></span>";
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
<div data-role="popup" id="popGraph" style="min-width: 20em">
<div data-role="header" data-theme="b" style="text-align:center;"><h1 style="margin: 0em;" id='poptext'>hi. this is test!! Please respond</h1></div>
<div data-role="content" style="background-color: #eeeeee">
<div id='popspark' style='background-color: #eee'></div>
</div>
</div>
</div> <!-- /ui-content -->
</div> <!-- /ui-page -->
</body>
</html>
