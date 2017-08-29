<?php

//Run:every 30 minutes

require_once(SL_INCLUDE_PATH."/class.slCache.php");

$dirs = array(SL_DATA_PATH."/cache");

foreach ($dirs as $dir) {
	cleanCache($dir);
}

function cleanCache($dir) {
	if ($dp = opendir($dir)) {
		while (($file = readdir($dp)) !== false) {
			$path = $dir."/".$file;
		
			if ($file == "." || $file == "..") {
				// NADA				
			} elseif (is_file($path)) {
				$cache = new slCache($path,true);
				
				if ($cache->isExpired()) {
					if (@unlink($path)) {
						echo "REMOVING: ".array_pop(explode("/",$path))."\n";
					}
				}
			} elseif (is_dir($path)) {
				cleanCache($path);				
			}
		}
		closedir($dp);
	}
}

cleanOld(SL_DATA_PATH."/tmp");

function cleanOld($dir) {
	if ($dp = opendir($dir)) {
		while (($file = readdir($dp)) !== false) {
			$path = $dir."/".$file;
		
			if ($file == "." || $file == "..") {
				// NADA				
			} elseif (is_file($path)) {
				if (time() > filemtime($path) + 3600) {
					if (@unlink($path)) {
						echo "REMOVING: ".array_pop(explode("/",$path))."\n";
					}
				}
			} elseif (is_dir($path)) {
				cleanOld($path);				
			}
		}
		closedir($dp);
	}
} 
