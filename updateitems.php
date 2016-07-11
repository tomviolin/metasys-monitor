<?php
header('application/javascript');
mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
mysql_select_db("metasys");
$j = array();
if (isset($_GET['items']) && isset($_GET['heading'])) {
	$items = explode(",", $_GET['items']);
	$heading = $_GET['heading'];
	for ($i = 0; $i < count($items); ++$i) {
		$query = "update display_points set heading='$heading', sortkey=$i where recid=".$items[$i].";";
		$j[] = $query;
		mysql_query($query);
		$j[] = mysql_error();
	}
	echo json_encode($j);
	//echo json_encode($items);
}
?>
