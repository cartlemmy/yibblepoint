<?php

if (setAndTrue($_SERVER["argv"],1)) $_GET["type"] = $_SERVER["argv"][1];

$report = array("Totals"=>array(
	"visits"=>0,
	"request"=>0,
	"answered"=>0,
));

ob_start();

?><html><head></head><body><?php

switch (isset($_GET["type"]) ? $_GET["type"] : "daily") {
	case "daily":
		$ts = time() - 86400;
		$reportName = date("F j, Y",$ts);
		$historyFile = realpath(dirname(__FILE__))."/history/".date("Y-m-d",$ts);
		parseReportFile($historyFile,$report,true);
		$to = "josh@randomapp.com,kwheeler@randomapp.com";
		break;
	
	case "monthly":
		$ts = strtotime("-1 month");
		$reportName = date("F Y",$ts);
	
		list($year,$month,$lastDay) = explode("-",date("Y-m-t",$ts));
		for ($day = 1; $day <= $lastDay; $day++) {
			$historyFile = realpath(dirname(__FILE__))."/history/".date("Y-m-d",strtotime($year."".$month."".$day));
			parseReportFile($historyFile,$report);
		}
		$to = "josh@randomapp.com,kwheeler@randomapp.com";
		break;
	
}

$reportName = "Chat Report - ".$reportName;

echo "<h1>$reportName</h1>";

$actionMap = array(
	"init"=>"Initialized",
	"request"=>"Chat Requested",
	"answered"=>"Chat Answered"
);

echo "<pre>";
foreach ($report as $name=>$item) {
	echo "<h2>".$name."</h2>";
	echo "<b>Chat Requests: </b> ".$item["request"]."\n";
	echo "<b>Answered Chat Requests: </b> ".$item["answered"]."\n";
	echo "<b>Visitors: </b> ".$item["visits"]."\n";
		
	if (isset($item["history"]) && count($item["history"])) {
		echo "<h3>Details</h3>";
		echo "<table border=\"1\">";
		foreach ($item["history"] as $ip=>$chatSession) {
			if ($chatSession["request"]) {
				echo "<tr><th colspan=\"2\">".$chatSession["requestedBy"].(isset($chatSession["requestedByEmail"])?"<br />".$chatSession["requestedByEmail"]:"")."</th></tr>";
				echo "<tr><th>Time</th><th>Action</th></tr>";
				foreach ($chatSession["history"] as $o) {
					echo "<tr><td>".date("g:i:sa",$o["ts"])."</td><td>".(isset($actionMap[$o["action"]]) ? $actionMap[$o["action"]] : $o["action"])."</td></tr>";
				}
			}
		}
		echo "</table>";
	}
	echo "<br /><hr /><br />";
}
echo "</pre>";

?></body></html><?php

$html = ob_get_flush();

if (!isset($_SERVER["REMOTE_ADDR"])) {
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
	$headers .= 'From: system@bnichatadmin.com' . "\r\n";
	$headers .= 'Reply-To: system@bnichatadmin.com' . "\r\n";
	$headers .= 'X-Mailer: PHP/' . phpversion();
	
	mail($to,$reportName,$html,$headers);
}

function parseReportFile($file,&$reportAll,$details = false) {
	if (is_file($file)) {
		$transferVars = array("requestedBy","requestedByEmail","answeredBy");
		if ($fp = fopen($file,"r")) {
			while (!feof($fp)) {
				$data = json_decode(fgets($fp),true);

				if ($data && $data["user"]) {
					if (!isset($reportAll[$data["user"]])) {
						$reportAll[$data["user"]] = array(
							"visits"=>0,
							"request"=>0,
							"answered"=>0,
							"history"=>array()
						);
					}
					$report = &$reportAll[$data["user"]];
				
					if (!isset($report["history"][$data["ip"]])) {
						if ($details) {
								$report["history"][$data["ip"]] = array(
								"ts"=>$data["ts"],
								"requestedBy"=>"(".$data["ip"].")",
								"request"=>0,
								"answered"=>0,
								"history"=>array()
							);
						}
						$report["visits"] ++;
						$reportAll["Totals"]["visits"]++;
					}

					if (isset($report[$data["action"]])) $report[$data["action"]]++;
					if (isset($reportAll["Totals"][$data["action"]])) $reportAll["Totals"][$data["action"]]++;
					if ($details && isset($report["history"][$data["ip"]][$data["action"]])) $report["history"][$data["ip"]][$data["action"]]++;
					
					if ($details) {
						$report["history"][$data["ip"]]["history"][] = $data;
						foreach ($transferVars as $n) {
							if (isset($data[$n])) $report["history"][$data["ip"]][$n] = $data[$n];
						}
					}
				}
			}
			fclose($fp);
		}
	}
}
