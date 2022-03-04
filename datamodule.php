<?php
require('evalmath.class.php');

// determine which dataset we are going after
if (isset($argv[1])) {
	$DATA_SUFFIX = "_".sprintf("%02d",$argv[1]+0);
	$dataset = $argv[1];
	array_shift($argv);
} else if (isset($_GET['dataset']) && is_numeric($_GET['dataset'])) {
	$DATA_SUFFIX = "_".sprintf("%02d",$_GET['dataset']);
	$dataset = $_GET['dataset'];
} else {
	$DATA_SUFFIX = "_00";
	$dataset = "";
}
//fprintf(STDERR,"DATA_SUFFIX=$DATA_SUFFIX\n");
// determine mode of operation:
//	record=y:
//	cron-based background operation which
//		- reads data from BACnet
//		- saves it to database
//		- determines if any error conditions exist
//		- performs appropriate actions (sending alerts, etc.)
//	record not 'y'
//		- reads data from database
//		- determines if any error conditions exist
//		- prepares and sends JSON representation of data to web client
//		  including any error condition determinations.
//
if ((isset($_GET['record']) && $_GET['record'] == "y") || (isset($argv[1]) && $argv[1]=="record=y")) {
	$RECORD=TRUE;
	//fprintf(STDERR,"recording!\n");
} else {
	$RECORD=FALSE;
	//fprintf(STDERR, "NOT recording!\n");
}

// data cleanup functions

function numequiv($valp) {
	$val=trim($valp);
	// numeric equivalent to database values
	if (is_numeric($val)) return trim($val);
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
flush();
	// create expression evaluator
	//echo "new EvalMath...\n"; flush();
	$m = new EvalMath;
	//echo "after new EvalMath.\n";

	$mv = array();
	$conn = mysql_connect("waterdata.glwi.uwm.edu","metasys","Meta56sys$$");
	mysql_select_db("metasys");


	$result = mysql_query("select d.heading,d.functional_name as dis_functional_name, d.description as dis_description, d.soft_min_value, d.soft_max_value, d.hard_min_value, d.hard_max_value, d.priority, d.alarm_name, d.alarm_type, d.allpoints_recid, a.*, i.ip_address, object_types.object_id as object_type_id from display_points$DATA_SUFFIX d LEFT JOIN allpoints a ON d.functional_name = a.functional_name LEFT JOIN devices i ON a.device_id = i.device_id LEFT JOIN object_types ON a.object_type = object_types.object_name order by heading, d.recid");
	if (mysql_errno() != 0) die(mysql_error()."\n");
	$j = array();
	while ($row = mysql_fetch_array($result)) {

		$jsondata = array();
		//echo "record: ".$row['disp_recid']." ".$row['dis_description']."\n";
		flush();
		if ($RECORD) {
			// READ DATA FROM BACnet and parse into useful data format
			usleep(mt_rand(300000,600000));
			$command = 'cd /home/tomh/projects/bacnet; BACNET_BBMD_ADDRESS='.$row['ip_address'].' ./readprop.sh '.$row['device_id'].' '.$row['object_type_id'].' '.$row['object_id'].' 85 '.$row['object_type_id'].' '.$row['object_id'].' 117';
			$countent = trim(`$command 2>/dev/null`);
			echo ">>> ".$row['dis_functional_name']." <<<\n";
			echo "===\n$command\n===\n";
			echo "=== Result: ===\n";
			echo $countent."\n";
			echo "\n";
			flush();
			$countent = preg_replace('/\r/','',$countent);
			$clines=explode("\n",$countent);
			for ($i = 0; $i < count($clines); ++$i) {
				echo "$i: ".$clines[$i]."\n";
			}
			if (count($clines) < 4) continue;
			$c1 = explode(":",$clines[2]);
			//print_r($c1);
			$value = numequiv(trim($c1[1]));
			$c2 = explode(":",$clines[3]);
			$c2 = trim($c2[1]);
			printf("===units:'%s'\n", $c2);
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
			printf("===units:'%s'\n", $c2);
			$units = $c2;
			if (is_numeric($c1)) {
				$value = sprintf("%.02f",$c1);
			} else {
				$value = trim($c1[1]);
			}
			$units = $c2;
			$dtime = date("D H:i");
			if ($value === "active") $value="1";
			if ($value === "inactive") $value = "0";
			//echo "inserting...\n";
			
			// insert into database
			$query = "insert into display_points_data_log (recid, recdate, value, units) values ("
					. $row['allpoints_recid'].","
					. "now(),"
					. "'$value',"
					. "'$units');";
			printf("%s\n", $query);
			$insresult = mysql_query($query);
			if (mysql_errno() != 0) die(mysql_error()."\n");
		} else {
			// get from database
			$query = "select * from display_points_data_log where recid = {$row['allpoints_recid']} order by recdate desc limit 60;";
			$getresult = mysql_query($query);
			if (mysql_errno() != 0) die(mysql_error()."\n");
			//echo "getting...\n";
			flush();
			$vrow = mysql_fetch_array($getresult);
			$value = sprintf('%.02f',$vrow['value']);
			$units = $vrow['units'];
			$dtime = date('D H:i',strtotime($vrow['recdate']));
			$c1 = $value;
			//print_r($vrow);
		}

		
		if (is_numeric($value)) {
			// determine alarm condition based on hard/soft min/max values
			$softerr = 0;
			$softlo = 0;
			$softhi = 0;
			$harderr = 0;
			$hardlo = 0;
			$hardhi = 0;
			$cerr = "";
			if (
				$c1 == numequiv($row['soft_min_value']) || 
				$c1 == numequiv($row['soft_max_value']) || 
				($c1 >= numequiv($row['soft_min_value']) && $row['soft_max_value'] == '') ||
				($c1 <= numequiv($row['soft_max_value']) && $row['soft_min_value'] == '') ||
				($c1 >= numequiv($row['soft_min_value']) && $c1 <= numequiv($row['soft_max_value']))
			) {
				$cerr = "ok";
			} else if (
				$c1 == numequiv($row['hard_min_value']) ||
				($row['hard_min_value'] != '' && $c1 < numequiv($row['hard_min_value']))
			) {
				$cerr = "error";
				$harderr = 1;
				$hardlo = 1;
			} else if (
				$c1 == numequiv($row['hard_max_value']) ||
				($row['hard_max_value'] != '' && $c1 > numequiv($row['hard_max_value']))
			) {
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
		}
		// read remainder of historical data from database
		$histdata = array();
		$histtime = array();
		$histdata[] = $value;
		$histtime[] = $dtime;
		$c2 = $units;
		if ($c2 != "" && !$RECORD) {
			// only read historical data if units are valid
			while ($detrow = mysql_fetch_array($getresult)){
				array_unshift($histdata,$detrow['value']);
				array_unshift($histtime, date("D H:i", strtotime($detrow['recdate'])));
			}
		}
		//$histdata = array(1,2,3,4,5);
		$jsondata['desc']=$row['priority'].":".$row['dis_description'];
		$jsondata['soft_min']=$row['soft_min_value'];
		$jsondata['soft_max']=$row['soft_max_value'];
		$jsondata['hard_min']=$row['hard_min_value'];
		$jsondata['hard_max']=$row['hard_max_value'];
		$jsondata['value']=$value;
		$jsondata['units']=$units;
		$jsondata['history'] = $histdata;
		$jsondata['histtime'] = $histtime;
		if ($row['alarm_type'] == 0) {
			$jsondata['cerr']=$cerr;
		} else {
			$jsondata['cerr']="noalarm";
		}
		$jsondata['alarm_name'] = $row['alarm_name'];
		$jsondata['alarm_type'] = $row['alarm_type'];
		$j[$row['allpoints_recid']-0] = $jsondata;

		//echo "json done\n";
		flush();
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
				//$mv[$alarm_name."_kalman_value"] = $drow['kalman_value'];
			}
		}
	}

	// *** APPLY GLOBAL ALARUMS ***
	// only when dataset < 5

	if ($dataset < 5) {
		// establish variables in expression evaluator
		$m->setvars($mv);

		// dump variable values
		//print_r($m->vars());
		// go through alarum database
		$query = "select * from alarms";
		$result = mysql_query($query);
		if (mysql_errno() != 0) die(mysql_error()."\n");
				if (mysql_errno() != 0) die(mysql_error()."\n");
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
	}
	if ($RECORD) exit(0);
	$json_output = "[";
	foreach ($j as $jindex => $jvalue) {
		$jvalue['data_index'] = $jindex;
		$json_output .= json_encode($jvalue) . ",";
	}
	if (strlen($json_output) > 1) {
		$json_output = substr($json_output,strlen($json_output)-1) . ']';
	} else {
		$json_output = "[]";
	}
	echo $json_output;
?>
