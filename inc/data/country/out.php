<?php

require_once(SL_INCLUDE_PATH."/class.slCache.php");

$file = realpath(dirname(__FILE__))."/".$requestInfo["rawParams"].".csv";

if (is_file_check_src($file)) {
	$stateProvinceFile = realpath(dirname(__FILE__))."/".$requestInfo["rawParams"]."-state-province.csv";
	
	$country = strtoupper($requestInfo["rawParams"]);

	$cache = new slCache($file);

	if (0 && $cache->isCached()) {
		$cache->out();
	} else {
		if ($fp = openAndLock($file,"r")) {
			$cache->start();
			
			$labels = fgetcsv($fp);
			
			$zip = array();
			$city = array();
			$state = array();
			$timezone = array();
			$geocode = array();
			$timezones = array();
			
			$cnt = 0;
			while (!feof($fp)) {
				$d = fgetcsv($fp);
				$row = array();
				foreach ($labels as $n=>$label) {
					$row[$label] = $d[$n];
				}		
				if (!isset($row["country"]) || $row["country"] == $country) {
					$zip[] = $row["zip"];
					$city[] = $row["primary_city"];
					$state[] = $row["state"];
					if ($row["timezone"] == "") {
						$timezone[] = "";
					} else {
						if (($tz = array_search($row["timezone"],$timezones)) === false) {
							$tz = count($timezones);
							$timezones[] = $row["timezone"];
						}
						$timezone[] = $tz;
					}
					$geocode[] = $row["latitude"].";".$row["longitude"];
				}
				$cnt ++;
			}
						
			closeAndUnlock($fp);
			
			echo 'sl.unescapeObject({';
			echo '"timezones":'.json_encode($timezones).",\n";
			echo '"stateAbbr":'.json_encode(is_file($stateProvinceFile) ? getLabeledCSV($stateProvinceFile) : null).",\n";
			echo '"zip":'.json_encode(implode(",",$zip)).'.split(","),'."\n";
			echo '"city":'.json_encode(implode(",",$city)).'.split(","),'."\n";
			echo '"state":'.json_encode(implode(",",$state)).'.split(","),'."\n";
			echo '"timezone":'.json_encode(implode(",",$timezone)).'.split(","),'."\n";
			echo '"geocode":'.json_encode(implode(",",$geocode)).'.split(",")'."\n";
			echo '})';
			
			$cache->complete();
		}
	}
}
