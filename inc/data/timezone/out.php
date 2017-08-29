<?php

require_once(SL_INCLUDE_PATH."/class.slCache.php");

$zoneFile = realpath(dirname(__FILE__))."/zone.csv";
$tzFile = realpath(dirname(__FILE__))."/timezone.csv";


if (is_file($zoneFile) && is_file($tzFile)) {
	$cache = new slCache(array($zoneFile,$tzFile));
	if ($cache->isCached()) {
		$cache->out();
	} else {
		$cache->start();
		
		$out = array();
		$zones = getLabeledCSV($zoneFile,true,"zone_id");
		$tz = getLabeledCSV($tzFile,true,"zone_id");
		
		foreach ($tz as $zone_id=>$z) {
			$max = -2147483648;
			$useZone = null;
			foreach ($z as $zone) {
				if (time() > $zone["time_start"] && (int)$zone["time_start"] >= $max) {
					$max = (int)$zone["time_start"];
					$useZone = $zone;
				}
			}
			if ($useZone["zone_id"]) $out[] = array($zones[$useZone["zone_id"]][0]["zone_name"],$useZone["abbreviation"],(int)$useZone["gmt_offset"]/3600);
		}
		echo json_encode($out);
		$cache->complete();
	}
}
