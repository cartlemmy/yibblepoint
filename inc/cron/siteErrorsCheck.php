<?php

//Run:every 2 minutes

$errors = array();
$dir = SL_DATA_PATH."/tmp/web-render";
if ($dp = opendir($dir)) {
	while (($file = readdir($dp)) !== false) {
		$path = $dir."/".$file;
		if (is_file($path)) {
			if (time() - filemtime($path) > 60) {
				$info = json_decode(file_get_contents($path),true);

				appendSECError($errors,$info["_SERVER"]["REQUEST_URI"]." took longer than 60 seconds to render.",false,$info["_SERVER"]["REQUEST_TIME"]);
				unlink($path);
			}
		}
	}
	closedir($dp);
}

if ($dp = opendir(SL_DATA_PATH.'/rendering')) {
	while (($file = readdir($dp)) !== false) {
		$path = SL_DATA_PATH.'/rendering/'.$file;
		if (is_file($path)) {
			if (filemtime($path) < time() - 120) {
				$info = json_decode(file_get_contents($path), true);
				if (!$info) $info = array("name"=>$file);
				urgentAlert($info["name"]." failed to render");	
				unlink($path); 
			}			
		}
	}
	closedir($dp);
}

if (is_file(SL_BASE_PATH."/error_log")) {
	if ($fp = fopen(SL_BASE_PATH."/error_log","r")) {
		while (!feof($fp)) {
			$line = fgets($fp);
			if (preg_match('/^\[([^\]]+)\]\sPHP\s(.*?)\:(.*)$/',$line,$match)) {
				switch ($match[2]) {
					case "Fatal error":
						appendSECError($errors,$match[2].":".$match[3],trim($match[3]), strtotime($match[1]));
						break;
				}
			}
		}
		fclose($fp);
		unlink(SL_BASE_PATH."/error_log");
	}	
}

if ($errors) {
	$body = array();
	foreach ($errors as $d) {
		$body[] = "[".($d[2] == $d[3] ? date('Y-m-d H:m:s',$d[3]) : date('Y-m-d H:m:s',$d[2])." - ".date('Y-m-d H:m:s',$d[3]))."] ".$d[1].($d[0] > 1 ? " (x".$d[0].")" : "");
	}

	require_once(SL_INCLUDE_PATH."/class.slCommunicator.php");
	$com = new slCommunicator();

	if (isset($GLOBALS["slConfig"]["support"]["email"]["address"])) {
		$com->addRecipient("email/".$GLOBALS["slConfig"]["support"]["email"]["address"],isset($GLOBALS["slConfig"]["support"]["name"]) ? $GLOBALS["slConfig"]["support"]["name"] : "Admin");
	} elseif (isset($GLOBALS["slConfig"]["communication"]["admin"])) {
		$com->addRecipient("email/".$GLOBALS["slConfig"]["communication"]["admin"],"Admin");
	} else return;
	
	$com->setSubject("Site Errors - ".WWW_BASE);
	
	$com->setMessage(implode("\n",$body));
		
	$com->send();
}
			

function appendSECError(&$errors,$error,$unique = false, $ts = false) {
	if (!$unique) $unique = $error;
	$unique = md5($unique);

	if (!isset($errors[$unique])) $errors[$unique] = array(0,$error,0x7FFFFFFF,0);
	$errors[$unique][0] ++;
	if ($ts) {
		$errors[$unique][2] = min($errors[$unique][2], $ts);
		$errors[$unique][3] = max($errors[$unique][3], $ts);
	}
}
