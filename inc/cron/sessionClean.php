<?php

//Run:every 30 minutes

/*
 * Cleans old session data and temporary user files
 */

$setup = $GLOBALS["slConfig"]["cleanup"];

// Session clean-up
$dir = SL_DATA_PATH."/sessions";

surpressErrors(true);
if ($dp = opendir($dir)) {
	while ($file = readdir($dp)) {
		if ($file != "." && $file != "..") {
			$path = $dir."/".$file;
			$filemtime = filemtime($path);
			if (time() > $filemtime + $setup["mininumTmpLife"]) {
				if (!is_dir($path."/user") && !is_dir($path."/tmp") && !is_dir($path."/file")) {
					//Junk session, delete it
					delTree($path);
				} elseif (time() > $filemtime + $setup["sessionMaxLife"]) {
					//Dead session, delete it
					delTree($path);
				}
			}
		}
	}
	closedir($dp);
}

//User temp file cleanup
$dir = SL_DATA_PATH."/users";

if ($dp = opendir($dir)) {
	while ($file = readdir($dp)) {
		if ($file != "." && $file != "..") {
			$path = $dir."/".$file."/tmp";
			if (is_dir($path)) {
				cleanTmpDir($path);
			}
		}
	}
}

surpressErrors(false);

function cleanTmpDir($path,$depth = 0) {
	$filesLeft = 0;
	if ($dp = opendir($path)) {
		while ($file = readdir($dp)) {
			if ($file != "." && $file != "..") {
				$path2 = $path."/".$file;
				if (is_dir($path2)) {
					if (cleanTmpDir($path2, $depth + 1) || $depth == 0) {
						$filesLeft ++;
					} else {
						if (!rmdir($path2)) {
							echo "Failed to delete ".$path2."\n";
							$filesLeft ++;
						}
					}
				} else {
					if (time() > filemtime($path2) + $GLOBALS["slConfig"]["cleanup"]["userTmpDataLife"]) {
						if (!unlink($path2)) {
							echo "Failed to delete ".$path2."\n";
							$filesLeft ++;
						}
					} else $filesLeft ++;
				}
			}
		}
		return $filesLeft;
	}
}
