<?php
require('evalmath.class.php');

if (isset($_GET['dataset']) && is_numeric($_GET['dataset'])) {
	$DATA_SUFFIX = "_".sprintf("%02d",$_GET['dataset']);
	$dataset = $_GET['dataset'];
} else {
	$DATA_SUFFIX = "";
	$dataset = "";
}
if (isset($_GET['record']) && $_GET['record'] == "y") {
	$RECORD=TRUE;
	file_put_contents("php://stderr", "recording!\n", FILE_APPEND);
} else {
	$RECORD=FALSE;
	file_put_contents("php://stderr", "NOT recording!\n", FILE_APPEND);
}

// data cleanup functions

	function numequiv($val) {
		// numeric equivalent to database values
		if (is_numeric($val)) return $val;
		if ($val==="active") return 1;
		if ($val==="inactive") return 0;
		if ($val === TRUE) return 1;
		if ($val === FALSE) return 0;
		return 0;
	}

	function dbdate($datein) {
		// makes sure empty dates are stored as NULL
		if (!isset($datein) || $datein === "" || $datein == "0000-00-00 00:00:00" || $datein == "1969-12-00 18:00:00") {
			return "NULL";
		}
		if (is_numeric($datein)) {
			if ($datein == 0) {
				return "NULL";//".date("Y-m-s H:i:s",$datein)."'";
			} else {
				$dstr = strtotime($datein);
				if (is_numeric($dstr) && $dstr > 0) {
					$m = "'".date("Y-m-s H:i:s", $dstr).".";
				}
				$m = "NULL";
			}
		} else {
			$m = "'".date("Y-m-s H:i:s", strtotime($datein))."'";
		}
		if ($m == "'0000-00-00 00:00:00'" || $m == "'1969-12-00 18:00:00'") $m = "NULL";
		return $m;

	}

function logmsg($msg) {
	global $RECORD;
	file_put_contents("/tmp/metasys.log",date("Y-m-d H:i:s: ").$msg."\n",FILE_APPEND);
}

	if (FALSE && $RECORD == FALSE) {
		// clean up connection to make sure we can close it cleanly
		ob_end_clean();
		// make sure browser does not wait past connection close
		header("Connection: close");
		// if user hits stop button, keep running
		ignore_user_abort();
		// start output buffer
		ob_start();
		$TMPFILE = "/dev/shm/metasys_cache.tmp";
		$CUTOFF1  = 1;
		$CUTOFF2  = 6; 
		$age = FALSE;
		if (file_exists($TMPFILE)) $age = filemtime($TMPFILE);
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
		$nf = fopen($TMPFILE.".part","w");
		$wouldlock=false;
		if ( (! flock($nf,LOCK_EX | LOCK_NB, $wouldlock) ) || $wouldlock == 1){
			exit(0);
		}

	} // ! $RECORD

	// create expression evaluator
	//echo "new EvalMath...\n"; flush();
	$m = new EvalMath;
	//echo "after new EvalMath.\n";

	$mv = array();
	$conn = mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
	mysql_select_db("metasys");


	$result = mysql_query("select d.recid as disp_recid, d.heading,d.functional_name as dis_functional_name, d.description as dis_description, d.soft_min_value, d.soft_max_value, d.hard_min_value, d.hard_max_value, d.priority, d.alarm_name, d.alarm_type, a.*, i.ip_address, object_types.object_id as object_type_id from display_points$DATA_SUFFIX d LEFT JOIN allpoints a ON d.functional_name = a.functional_name LEFT JOIN devices i ON a.device_id = i.device_id LEFT JOIN object_types ON a.object_type = object_types.object_name order by heading, d.recid");
	if (mysql_errno() != 0) die(mysql_error()."\n");
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
		
		$value = $c1;
		if ($value == "active") $value="1";
		if ($value == "inactive") $value = "0";
		if (!is_numeric($value)) continue;
		$softerr = 0;
		$softlo = 0;
		$softhi = 0;
		$harderr = 0;
		$hardlo = 0;
		$hardhi = 0;
		$cerr = "";
		if (
			$c1 == $row['soft_min_value'] || 
			$c1 == $row['soft_max_value'] || 
			($c1 >= $row['soft_min_value'] && $row['soft_max_value'] == '') ||
			($c1 <= $row['soft_max_value'] && $row['soft_min_value'] == '') ||
			($c1 >= $row['soft_min_value'] && $c1 <= $row['soft_max_value'])) {
			$cerr = "ok";
		} else if (
			$c1 == $row['hard_min_value'] ||
			($row['hard_min_value'] != '' && $c1 < $row['hard_min_value'])) {
			$cerr = "error";
			$harderr = 1;
			$hardlo = 1;
		} else if (
			$c1 == $row['hard_max_value'] ||
			($row['hard_max_value'] != '' && $c1 > $row['hard_max_value'])) {
			$cerr = "error";
			$harderr = 1;
			$hardhi = 1;
		}
		if ($cerr == "") {
			if ($c1 < $row['soft_min_value']) {
				$softlo = 1;
				$softerr = 1;
				$cerr = "warning";
			}
			if ($c1 > $row['soft_max_value']) {
				$softhi = 1;
				$softerr = 1;
				$cerr = "warning";
			}
		}
			
		// read historical data from database
		$histdata = array();
		$histtime = array();
		if ($c2 != "") {
		$mquery = "select * from display_points_data_record where recid=".(0+$row['disp_recid'])." order by recdate desc limit 20";
		$multiresult = mysql_query($mquery);
		file_put_contents("php://stderr", 'DEBUG:'. $mquery."\nStatus:".mysql_error().",".mysql_num_rows($multiresult)."\n");
		while ($detrow = mysql_fetch_array($multiresult)){
			array_unshift($histdata,$detrow['value']);
			array_unshift($histtime, date("H:i", strtotime($detrow['recdate'])));
		}
		}
		//$histdata = array(1,2,3,4,5);
		$jsondata = array();
		$jsondata['desc']=$row['priority'].":".$row['dis_description'];
		$jsondata['soft_min']=$row['soft_min_value'];
		$jsondata['soft_max']=$row['soft_max_value'];
		$jsondata['hard_min']=$row['hard_min_value'];
		$jsondata['hard_max']=$row['hard_max_value'];
		//$jsondata['value']=trim($countent);
		$jsondata['value']=$c1;
		$jsondata['units']=$c2;
		$jsondata['history'] = $histdata;
		$jsondata['histtime'] = $histtime;
		if ($row['alarm_type'] == 0) {
			$jsondata['cerr']=$cerr;
		} else {
			$jsondata['cerr']="noalarm";
		}
		$jsondata['alarm_name'] = $row['alarm_name'];
		$jsondata['alarm_type'] = $row['alarm_type'];
		$j[$row['disp_recid']-0] = $jsondata;

		// ** log data to historical record **
		if ($RECORD) {
			$drowrec = array("value"=>$value, "kalman_value" => $value, "last_exceeded_soft"=>NULL, "last_exceeded_hard"=>NULL
					);
			$insqueryrec = ("insert into display_points_data_record (recid,recdate,value, kalman_value) values (" .
				"'".$row['disp_recid']."',".
				"now(),".
				"'".$drowrec['value']."',".
				"'".$drowrec['kalman_value']."');");
			mysql_query($insqueryrec);
			logmsg("status = ".mysql_errno().":".$insqueryrec);
		} else {
			// *** update data status record ***
			$dataquery = "select * from display_points_data where recid=".$row['disp_recid'];
			$dataresult = mysql_query($dataquery);
			if (mysql_num_rows($dataresult) === 0) {
				// creating new data record
				$drow = array("value"=>$value, "kalman_value" => $value, "last_exceeded_soft"=>NULL, "last_exceeded_hard"=>NULL
						);
				$insquery = ("insert into display_points_data (recid,value, kalman_value) values (" .
					"'".$row['disp_recid']."',".
					"'".$drow['value']."',".
					"'".$drow['kalman_value']."');");
				mysql_query($insquery);
				logmsg($insquery);
				
				if (mysql_errno() != 0) die(mysql_error()."\n");
			} else {
				$drow = mysql_fetch_array($dataresult);
			}

			$drow["value"] = $value;
			$drow["kalman_value"] = ($drow["kalman_value"] * 0.99) + ($value * 0.01);
			if ($softerr!=0) $drow['last_exceeded_soft'] = date("Y-m-d H:i:s", time());
			if ($harderr!=0) $drow['last_exceeded_hard'] = date("Y-m-d H:i:s", time());
			$dataupdatequery = "update display_points_data set value='{$drow['value']}'," .
						"kalman_value = '{$drow['kalman_value']}', ".
						"last_exceeded_soft = ".dbdate(strtotime($drow['last_exceeded_soft'])).", ".
						"last_exceeded_hard = ".dbdate(strtotime($drow['last_exceeded_hard'])) .
						" where recid={$drow['recid']};";
			//echo $dataupdatequery."\n";
			logmsg($dataupdatequery);
			$resultupdate = mysql_query($dataupdatequery);

			$alarm_name = $row['alarm_name'];
			if ($alarm_name == "") $alarm_name="default";

			if ($alarm_name !== "") {
				if (isset($mv[$alarm_name."_count"])) {
					// name is an aggregate
					// only update averages, delete individual values
					unset($mv[$alarm_name."_value"]);
					unset($mv[$alarm_name."_soft_min_value"]);
					unset($mv[$alarm_name."_soft_max_value"]);
					unset($mv[$alarm_name."_hard_min_value"]);
					unset($mv[$alarm_name."_hard_max_value"]);
					unset($mv[$alarm_name."_soft_min_exceeded"]);
					unset($mv[$alarm_name."_soft_max_exceeded"]);
					unset($mv[$alarm_name."_hard_min_exceeded"]);
					unset($mv[$alarm_name."_hard_max_exceeded"]);
					unset($mv[$alarm_name."_soft_exceeded"]);
					unset($mv[$alarm_name."_hard_exceeded"]);
					unset($mv[$alarm_name."_kalman_value"]);
					// update totals
					$mv[$alarm_name."_total"] = $mv[$alarm_name."_total"] + $value;
					$mv[$alarm_name."_count"] = $mv[$alarm_name."_count"] + 1;
				} else {
					// first time seeing this name, store everything
					$mv[$alarm_name."_value"] = $value;
					$mv[$alarm_name."_soft_min_value"] = $row['soft_min_value'];
					$mv[$alarm_name."_soft_max_value"] = $row['soft_max_value'];
					$mv[$alarm_name."_hard_min_value"] = $row['hard_min_value'];
					$mv[$alarm_name."_hard_max_value"] = $row['hard_max_value'];
					$mv[$alarm_name."_soft_min_exceeded"] = $softlo;
					$mv[$alarm_name."_soft_max_exceeded"] = $softhi;
					$mv[$alarm_name."_hard_min_exceeded"] = $hardlo;
					$mv[$alarm_name."_hard_max_exceeded"] = $hardhi;
					$mv[$alarm_name."_soft_exceeded"] = $softerr;
					$mv[$alarm_name."_hard_exceeded"] = $harderr;
					$mv[$alarm_name."_total"] = $value;
					$mv[$alarm_name."_count"] = 1;
					$mv[$alarm_name."_kalman_value"] = $drow['kalman_value'];
				}
			}
		} // $!RECORD
	}

	// *** APPLY ALARUMS ***

	// establish variables in expression evaluator
	$m->setvars($mv);

	// dump variable values
	//print_r($m->vars());
	// go through alarum database
	$query = "select * from alarms";
	$result = mysql_query($query);
	while ($arow = mysql_fetch_array($result)) {
		if ($arow['context']==1) {
			// evaluate expression
			$expval = $m->e("(".$arow['expression'].")");
			//echo "expval: ".$expval."\n";
			if (is_numeric($expval) && $expval != 0) {
				// *** we have an alarum! ***
				// visualized by using matching on the alarm_name_match
				foreach ($j as $recid => $values) {
					//echo "visiting $recid...\n"; flush();
					if (preg_match('/'.$arow['alarm_name_match'].'/',$values['alarm_name'])>0) {
						$j[$recid]['cerr'] = "alarm";
						//echo "--> flagging error at ".$recid."\n"; flush();
					}
				}
			}
		}
	}
	if ($RECORD) exit(0);
	if (FALSE) {
	fwrite($nf, json_encode($j));
	fclose($nf);
	rename($TMPFILE.".part",$TMPFILE);
	// remember we closed HTTP connection only when $age < $CUTOFF2
	if ($age >= $CUTOFF2) echo json_encode($j);
	} else {
		echo json_encode($j);
	}
?>
