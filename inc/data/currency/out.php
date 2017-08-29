<?php

$bt = debug_backtrace();
$returnValue = $bt[0]["file"] !== SL_INCLUDE_PATH."/class.slInstance.php";

require_once(SL_INCLUDE_PATH."/class.slCache.php");

$formatFile = realpath(dirname(__FILE__))."/format.csv";
$symbolFile = realpath(dirname(__FILE__))."/symbol.csv";

if (is_file($formatFile) && is_file($symbolFile)) {
	$exchangeRateFile = SL_DATA_PATH."/exchange-rate.json";
	if (is_file($exchangeRateFile)) {
		$rate = json_decode(file_get_contents($exchangeRateFile),true);
		
		$cache = $returnValue ? null : new slCache($exchangeRateFile);

		if (!$returnValue && $cache->isCached()) {
			$cache->out();
		} else {
			if ($fp = openAndLock($formatFile,"r")) {
				if ($cache) $cache->start();
				
				$out = array();
				
				while (!feof($fp)) {
					$row = fgetcsv($fp);
					if ($row[1]) $out[$row[1]] = array($row[3],$row[5],$row[6],"",isset($rate["rates"][$row[1]])?$rate["rates"][$row[1]]:null);
				}
				
				closeAndUnlock($fp);
				
				if ($fp = openAndLock($symbolFile,"r")) {
					while (!feof($fp)) {
						$row = fgetcsv($fp);
						if (isset($out[$row[0]])) $out[$row[0]][3] = $row[2];
					}
					closeAndUnlock($fp);
				}
				
				$out["_YC"] = array("credit",2,"#,###.##","Cr",1000);
				
				if (!$returnValue) {
					echo json_encode($out);
				
					$cache->complete();
				}
			}
		}
	} else echo "null";
}

if ($returnValue) return $out;
