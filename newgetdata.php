<?php
	ob_end_clean();
	header("Connection: close");
	ignore_user_abort();
	ob_start();
	$TMPFILE = "/dev/shm/metasys_cache.tmp";
	$CUTOFF1  = 1;
	$CUTOFF2  = 6; 
	$age = filemtime($TMPFILE);
	if ($age !== FALSE) {
		$age = time()-$age;
		if ($age < $CUTOFF2) {
			readfile($TMPFILE);
		}
		if ($age < $CUTOFF1) exit(0);
	}
	if ($age < $CUTOFF2) {
		// age less than the 2nd cutoff, we get new
		// data in background
		// so this code closes the HTTP session.
		$size = ob_get_length();
		header("Content-Length: $size");
		ob_end_flush();
		flush();
		session_write_close();
	}	
	//$fpid = pcntl_fork();
	//if ($fpid != 0) exit(0);
	$nf = fopen($TMPFILE.".part","w");
	$wouldlock=false;
	if ( (! flock($nf,LOCK_EX | LOCK_NB, $wouldlock) ) || $wouldlock == 1){
		exit(0);
	}
	$conn = mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
	mysql_select_db("metasys");
	$result = mysql_query("select d.recid as disp_recid, d.heading,d.functional_name as dis_functional_name, d.description as dis_description, d.soft_min_value, d.soft_max_value, d.hard_min_value, d.hard_max_value, d.priority, a.*, i.ip_address, object_types.object_id as object_type_id from display_points d LEFT JOIN allpoints a ON d.functional_name = a.functional_name LEFT JOIN devices i ON a.device_id = i.device_id LEFT JOIN object_types ON a.object_type = object_types.object_name order by heading, d.recid");
	$j = array();
	while ($row = mysql_fetch_array($result)) {
		$command = 'cd /home/tomh/projects/bacnet; BACNET_BBMD_ADDRESS='.$row['ip_address'].' ./readprop.sh '.$row['device_id'].' '.$row['object_type_id'].' '.$row['object_id'].' 85 '.$row['object_type_id'].' '.$row['object_id'].' 117';
		$countent = trim(`$command 2>/dev/null`);
		$countent = preg_replace('/\r/','',$countent);
		$clines=explode("\n",$countent);
		if (count($clines) < 4) continue;
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
			$c1 = trim($c1[1]);
		}
		$countent = $c1 ." ".$c2;
		$cerr="";
		if (
			$c1 == $row['soft_min_value'] || 
			$c1 == $row['soft_max_value'] || 
			($c1 >= $row['soft_min_value'] && $row['soft_max_value'] == '') ||
			($c1 <= $row['soft_max_value'] && $row['soft_min_value'] == '') ||
			($c1 >= $row['soft_min_value'] && $c1 <= $row['soft_max_value'])) {
			$cerr = "ok";
		} else if (
			$c1 == $row['hard_min_value'] ||
			$c1 == $row['hard_max_value'] ||
			($row['hard_min_value'] != '' && $c1 < $row['hard_min_value']) ||
			($row['hard_max_value'] != '' && $c1 > $row['hard_max_value'])) {
			$cerr = "error";
		} else {
			$cerr = "warning";
		}
		if ($cerr == "error" && $row['priority'] <1) {
			$cerr="error_".$row['priority'];
		}
		$jsondata = array();
		$jsondata['soft_min']=$row['soft_min_value'];
		$jsondata['soft_max']=$row['soft_max_value'];
		$jsondata['hard_min']=$row['hard_min_value'];
		$jsondata['hard_max']=$row['hard_max_value'];
		$jsondata['value']=trim($countent);
		$jsondata['cerr']=$cerr;
		$j[$row['recid']-0] = $jsondata;

	}

	fwrite($nf, json_encode($j));
	fclose($nf);
	rename($TMPFILE.".part",$TMPFILE);
	// remember we closed HTTP connection only when $age < $CUTOFF2
	if ($age >= $CUTOFF2) echo json_encode($j);
?>
