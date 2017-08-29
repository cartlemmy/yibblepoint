<?php

//Run:every second

ob_start();
$issueLevel = 0;
$issueLevels = array("No Issue","Notice","Warning","Alert");

// Check for malicious scripts
$allowedRootFiles = is_file(SL_DATA_PATH."/root-files") ? explode("\n",trim(file_get_contents(SL_DATA_PATH."/root-files"))) : false;
$fileUpdated = is_file(SL_DATA_PATH."/root-files-updated") ? json_decode(file_get_contents(SL_DATA_PATH."/root-files-updated"),true) : array();
$currentRootFiles = array();

if ($dp = opendir(SL_BASE_PATH)) {
	$malDir = SL_DATA_PATH."/potential-mal";
	
	if (!is_dir($malDir)) mkdir($malDir);
	
	while (($file = readdir($dp)) !== false) {
		if ($file != "." && $file != ".." && $file != "error_log") {
			$path = SL_BASE_PATH."/".$file;
			
			$isFile = is_file($path);
			$mtime = filemtime($path);
			
			$updFile = SL_DATA_PATH."/root-file-updated/".$file;
			
			$currentRootFiles[] = $file;
			
			if ($allowedRootFiles && !in_array($file,$allowedRootFiles)) {
				echo ($isFile?"File":"Dir")." '$path' not in ".SL_DATA_PATH."/root-files\n";
				if ($isFile) {
					rename($path,$malDir."/".$file);
					echo "\tMoved to: ".$malDir."/".$file.".tar.gz\n";
				} else {
					system("tar -zcf ".escapeshellcmd($malDir."/".$file.".tar.gz")." ".escapeshellcmd($path));
					if (is_file($malDir."/".$file.".tar.gz")) {
						system("rm -rf ".escapeshellcmd($path));
						echo "\tMoved to: ".$malDir."/".$file.".tar.gz\n";
					}
				}
				
				$issueLevel = max($issueLevel,3);
			}
			
			if (isset($fileUpdated[$file]) && $isFile && $mtime > $fileUpdated[$file]) {
				echo "File '$path' modified\n";
				$issueLevel = max($issueLevel,2);
			}
			
			$fileUpdated[$file] = $mtime;
		}
	}
	closedir($dp);
}

if (is_file(SL_DATA_PATH."/security-log")) {
	$issueLevel = max($issueLevel,1);
	readfile(SL_DATA_PATH."/security-log");
	unlink(SL_DATA_PATH."/security-log");
}

if (!$allowedRootFiles) file_put_contents(SL_DATA_PATH."/root-files",implode("\n",$currentRootFiles));
file_put_contents(SL_DATA_PATH."/root-files-updated",json_encode($fileUpdated));

if ($issueLevel) mail(
	isset($GLOBALS["slConfig"]["communication"]["admin"]) ? $GLOBALS["slConfig"]["communication"]["admin"] : $GLOBALS["slConfig"]["defaultFrom"]["email"],
	"Potential Security Issue '".SL_BASE_PATH."'",
	ob_get_flush()
);

