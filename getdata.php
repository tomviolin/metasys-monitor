<?php
	$devinfo = explode(':',$_GET['info']);
	$elementId = $_GET['id'];
	$devdet = explode(' ',$devinfo[1]);

	$ip = explode('.',$devinfo[0]);
	$ipaddr = "";
	foreach ($ip as $octet) {
		$ipaddr .= ($octet + 0).'.';
	}
	$ipaddr=substr($ipaddr,0,strlen($ipaddr)-1);
	$command = 'cd /home/tomh/projects/bacnet; BACNET_BBMD_ADDRESS='.$devinfo[0].' ./readprop.sh';
	foreach ($devdet as $n) {
		$command .= ' ' . ($n+0);
	}
	file_put_contents("/tmp/ajaxcmd.txt", $command."\n", FILE_APPEND);
	$countent = trim(`$command 2>/dev/null`);
	$countent = preg_replace('/\r/','',$countent);
	file_put_contents("/tmp/ajaxcmd.txt", $countent."\n", FILE_APPEND);
	$clines=explode("\n",$countent);
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
	$cerr="";
	$j = array('id' => $elementId, 'value'=>$countent, 'cerr'=>$cerr);
	echo json_encode($j);
	file_put_contents("/tmp/ajaxcmd.txt",json_encode($j)."\n",FILE_APPEND);
?>
