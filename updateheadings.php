<?php
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

$num = 0;
while (isset($_GET['item'.$num])) {
	$oldlabel = $_GET['item'.$num];
	$newlabel = sprintf("%02d ",$num+1).substr($oldlabel, 3, strlen($oldlabel)-3);
	if ($oldlabel != $newlabel) {
		$query=("update display_points$DATA_SUFFIX set heading='$newlabel' where heading='$oldlabel'");
		mysql_query($query);
		logmsg($query);
	}
	$num ++;
}

echo json_encode($j);
?>
