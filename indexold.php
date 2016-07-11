<?php
$mode="selected";
if (!isset($_GET["query"]) || $_GET["query"] == "selected") {
	$mode="selected";
} elseif ($_GET["query"]=="ALL") {
	$mode="ALL";
}
?>
<!doctype html>
<html>
<head>
<?php if ($mode == "selected") { ?>
	<meta http-equiv="Refresh" content="5">
<?php } ?>
	<meta content="width=device-width, minimum-scale=0.9, maximum-scale=0.9" name="viewport">

	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>

<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquerymobile/1.4.5/jquery.mobile.min.js"></script>
<style type="text/css">
.ui-li-count {
	font-size: 100%;
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
	background-color: #99ff99;
	color: black;
	text-shadow: none;
}

.ui-listview.floaty {
	float: left;
	max-width: 20em;
	margin-right: 0.5%;
	xmargin-left: 0.5%;
	margin-top: 3px;
	min-width: 20em;
}
span.x-list-item {
	font-weight: bold;
	font-size: 95%;
}
.ui-listview>.ui-li-static {
	padding: 0.5em 0.5em;
}
</style>
<script>
$(function() {
	//document.documentElement.requestFullscreen();
});
</script>


</head>

<body>
<div data-role='page'>
<div data-role="header" xclass="ui-bar" data-theme="b" data-position="fixed">
<?php if ($mode=="ALL") { ?>
	<a href="?query=selected" class="ui-btn-left" data-role="button" data-theme="<?=$mode=="selected"?"a":"b"?>" data-icon="back" data-iconpos="notext">back</a>
	<h1>Metasys Search</h1>
<?php } ?>
<?php if ($mode=="selected") { ?>
	<h1>Metasys Monitor</h1>
	<a href="?query=ALL" class='ui-btn-right' data-role="button" data-icon="search" xdata-ajax="false" data-iconpos="notext" data-theme="<?=$mode=="ALL"?"a":"b"?>">Search</a>
<?php } ?>
</div>
<div class="content">

<?php

$conn = mysql_connect("waterdata.glwi.uwm.edu","tomh","wd34faer");
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

	echo '<li data-icon="false"><span class="x-list-item" href="'.$link.'">'.$row['dis_description']." <span style='font-size: $fsize%'>(".$row['dis_functional_name'].")</span> ";
	echo "<span title='".$row['dis_functional_name']." - ".$row['description']."' class='ui-li-count ".$cerr."'>$countent";
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
		<a data-role="button" title="next 100 rows" data-icon="arrow-r" data-iconpos="notext" href="?query=ALL&start=<?= $start+100 ?>&search=<?= htmlentities(urlencode($search))?>">+100</a>
<?php } ?>
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
console.log('here..');
window.setTimeout(function() {
//	$('#recs').html("(<?= $start+1 ?>-<?= $start + $recount  ?>)");
}, 3000);
</script>

<?php } } ?>
</div> <!-- /ui-content -->
</div> <!-- /ui-page -->
</body>
</html>
