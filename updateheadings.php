<?php
header('application/javascript');
mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
mysql_select_db("metasys");
$j = array();

$num = 0;
while (isset($_GET['item'.$num])) {
	$oldlabel = $_GET['item'.$num];
	$newlabel = sprintf("%02d ",$num+1).substr($oldlabel, 3, strlen($oldlabel)-3);
	if ($oldlabel != $newlabel) {
		mysql_query("update display_points set heading='$newlabel' where heading='$oldlabel'");
	}
	$num ++;
}

echo json_encode($j);
?>
