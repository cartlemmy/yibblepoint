<?php

header("Content-type: text/cache-manifest");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$offlineFile = SL_DATA_PATH."/web-apps/".substr($requestInfo["path"],0,-9).".app";

if (is_file($offlineFile)) {
	$offlineData = json_decode(file_get_contents($offlineFile),true);

	echo "CACHE MANIFEST\n";
	
	$modified = 0;
	foreach ($offlineData["files"] as $file) {
		$modified = max($modified,fileModified($file));
	}
	
	if (isset($offlineData["dependencies"])) {
		foreach ($offlineData["dependencies"] as $file) {
			$modified = max($modified,filemtime($file));
		}
	}
	
	echo "# Modified ".date("Y-m-d H:i:s",$modified)."\n";
	echo implode("\n",$offlineData["files"]);
	
	echo "\n\nFALLBACK:\n";
	echo $offlineData["fallback"]."\n\n";
	echo "NETWORK:\n*";
}

function fileModified($file) {
	$roots = array(SL_WEB_PATH."/");
	if ($dp = opendir(SL_WEB_PATH."/templates")) {
		while (($f = readdir($dp)) !== false) {
			$roots[] = SL_WEB_PATH."/templates/".$f."/";
		}
		closedir($dp);
	}
	
	$ts = 0;
	foreach ($roots as $r) {
		$local = $r.$file;
		if (is_file($local)) $ts = max($ts,filemtime($local));
	}
	return $ts;
}
