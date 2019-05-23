<?php
//if (isset($_GET['dataset'])) {
if (isset($_GET['dataset']) && is_numeric($_GET['dataset'])) {
	$DATA_SUFFIX = "_".sprintf("%02d",$_GET['dataset']);
	$dataset = $_GET['dataset'];
} else {
	$DATA_SUFFIX = "";
	$dataset = "";
}
function logmsg($msg) {
	file_put_contents("/tmp/metasys.log",date("Y-m-d H:i:s: ").$msg."\n",FILE_APPEND);
}
header('application/javascript');
mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
mysql_select_db("metasys");
$j = array();
if (isset($_GET['items']) && isset($_GET['heading'])) {
	$items = explode(",", $_GET['items']);
	$heading = $_GET['heading'];
	for ($i = 0; $i < count($items); ++$i) {
		$query = "update display_points$DATA_SUFFIX set heading='$heading', sortkey=$i where recid=".$items[$i].";";
		$j[] = $query;
		mysql_query($query);
		logmsg($query);
		$j[] = mysql_error();
	}
	echo json_encode($j);
	//echo json_encode($items);
}
?>
