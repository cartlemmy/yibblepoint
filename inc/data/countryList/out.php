<?php

require_once(SL_INCLUDE_PATH."/class.slCache.php");

list($lang,$country) = explode("-",$GLOBALS["slConfig"]["international"]["language"]);

$icuDir = SL_INCLUDE_PATH."/thirdparty/country-list-master/country/icu/";
$dir = $icuDir.$lang."_".strtoupper($country);
if (!is_dir($dir)) {
	$dir = $icuDir.$lang;
}

$file = $dir."/country.csv";

if (is_file($file)) {
	
	$cache = new slCache($file);

	if ($cache->isCached()) {
		$cache->out();
	} else {
		if ($fp = openAndLock($file,"r")) {
			$cache->start();
			
			$labels = fgetcsv($fp);
			
			$iso = array();
			$name = array();
			
			while (!feof($fp)) {
				$row = fgetcsv($fp);
				if ($row[0]) {
					$iso[] = $row[0];
					$name[] = rawurlencode($row[1]);
				}
			}
			
			echo 'sl.unescapeObject({';
			echo '"iso":'.json_encode(implode(",",$iso)).'.split(","),'."\n";
			echo '"name":'.json_encode(implode(",",$name)).'.split(",")'."\n";
			echo '})';
			
			closeAndUnlock($fp);
			exit();
			$cache->complete();
		}
	}
}
