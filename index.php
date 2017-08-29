<?php

error_reporting(0);
ini_set("display_errors", 0);

$slSetupMode = !is_file("inc/config.php"); 

if ($slSetupMode) {
	if (is_dir("install-tmp/license")) {
		rename("install-tmp/license","data/license");
	}
}

$GLOBALS["slConfig"] = $slSetupMode ? array("dev"=>array("debug"=>true,"verbose"=>true)) : require("inc/config.php");

require("inc/2x-image.php");

$mime = array("css"=>"text/css","png"=>"image/png","jpg"=>"image/jpeg","jpeg"=>"image/jpeg","gif"=>"image/gif","svg"=>"image/svg","js"=>"text/js");
$ext = array_shift(explode("?",strtolower(array_pop(explode('.',array_pop(explode('/',$_SERVER["REQUEST_URI"])))))));
	
if (strpos($_SERVER["REQUEST_URI"],"?") === false && !(isset($GLOBALS["slConfig"]["dev"]["debug"]) && $GLOBALS["slConfig"]["dev"]["debug"]) && (isset($GLOBALS["slConfig"]["web"]["enableCaching"]) && $GLOBALS["slConfig"]["web"]["enableCaching"]) && isset($mime[$ext])) {
	$p = explode("/",$_SERVER["SCRIPT_NAME"]);
	array_pop($p);
	$p = substr($_SERVER["REQUEST_URI"],strlen(implode('/',$p))+1);

	if ($ext == "js" || $ext == "css") {
		$md5 = md5($p);
		$check = 'data/cache/'.substr($md5,0,2)."/".$md5;
	
		$skipHeaders = array("Date","Server","Set-Cookie","X-Powered-By","Expires");
		if (is_file($check)) {
			$c = explode("\n",file_get_contents($check),2);

			$headers = json_decode($c[0],true);

			foreach ($headers as $header) {
				if (!in_array(array_shift(explode(":",$header)),$skipHeaders)) header($header);
			}
			echo $c[1];
			exit();
			
		}
	} else {
		$check = 'web/'.$p;
		if (is_file($check)) {
			header('Content-type: '.$mime[$ext]);
			cacheHeaders(864000, $check);
			readfile($check);
			exit();
		}

		$check = 'web/templates/'.$GLOBALS["slConfig"]["web"]["template"].$p;
		if (is_file($check)) {
			header('Content-type: '.$mime[$ext]);
			cacheHeaders(864000, $check);
			readfile($check);
			exit();
		}
	}
}

require_once("inc/initialize.php");

if ($slSetupMode) {
	$createDirs = array(
		SL_DATA_PATH."/secuTokenData",
		SL_DATA_PATH."/web-visits"
	);
	foreach ($createDirs as $dir) {
		if (!is_dir($dir)) mkdir($dir);
	}
}

require("inc/class.slInstance.php");

$instance = $GLOBALS["slInstance"] = new slInstance($GLOBALS["slCore"]);

if (isset($GLOBALS["slSession"])) $GLOBALS["slSession"]->__destruct(); // Required to properly end session
