<?php
$converting = (isset($_GET['f2c'])) ;
function convert($num) {
	global $converting;
	if ($converting) {
		return (floor(($num-32)*5./9.*100.+.5)/100.);
	} else {
		return($num);
	}
}


	if (isset($_GET['recid'])) $disp_recid = $_GET['recid']+0;
	if (isset($argv[1])) $disp_recid = $argv[1]+0;
	$range="minutes";
	if (isset($_GET['range'])) $range = $_GET['range'];
	if (isset($argv[2])) $range = $argv[2];

	$conn = mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
	mysql_select_db("metasys");
	// get from database
	if ($range=="minutes") {
		$query = "select recid,recdate as recdatex, value as avg_value, value as max_value, value as min_value from display_points_data_log where recid = {$disp_recid} order by recdate desc limit 90;";
		$format = "D H:i";
	} elseif ($range=="hours") {
		$query = "select recid,recdate as recdatex, value as avg_value, value as max_value, value as min_value from display_points_data_log where recid = {$disp_recid} order by recdate desc limit 240;";
		$format = "D H:i";
	} elseif ($range=="days") {
		$query = "select recid, concat(mid(recdate,1,13),':00') as recdatex, avg(value) as avg_value, max(value) as max_value, min(value) as min_value from display_points_data_log where recid = {$disp_recid} group by recdatex order by recdatex desc limit 32;";
		$format = "D jS M H:00";
	} elseif ($range=="months") {
		$query = "select recid, mid(recdate,1,10) as recdatex, avg(value) as avg_value, max(value) as max_value, min(value) as min_value from display_points_data_log where recid = {$disp_recid} group by recdatex order by recdatex desc limit 365;";
		$format = "D jS M";
	}
	file_put_contents("/tmp/gpd.tmp",sprintf("%s: query=%s\n", `date`, $query),FILE_APPEND);
	//echo $query."\n";
	$getresult = mysql_query($query);
	if (mysql_errno() != 0) die(mysql_error()."\n");
	//echo "getting...\n";
	// read historical data from database
	$histdata = array();
	$histmaxv = array();
	$histminv = array();
	$histtime = array();
	while ($detrow = mysql_fetch_array($getresult)){
		array_unshift($histdata,convert($detrow['avg_value']));
		array_unshift($histmaxv,convert($detrow['max_value']));
		array_unshift($histminv,convert($detrow['min_value']));
		array_unshift($histtime, date($format, strtotime($detrow['recdatex'])));
	}
	$jsondata = array();
	$jsondata['histdata']=$histdata;
	$jsondata['histtime']=$histtime;
	$jsondata['histmaxv']=$histmaxv;
	$jsondata['histminv']=$histminv;
	echo json_encode($jsondata);
?>
