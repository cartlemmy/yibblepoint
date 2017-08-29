<?php

error_reporting(0);

$m = array(
	"Cron run at ".date("Y-m-d H:i:s T")." for ".getcwd(),
	"Linux User ".array_shift(posix_getpwuid(posix_geteuid()))
);
$len = 0;
foreach ($m as $line) {
	$len = max($len,strlen($line));
}

$b = str_repeat("#",$len + 6);

$cronHeader = "\n".$b."\n";
foreach ($m as $line) {
	$cronHeader .= "## ".$line.str_repeat(" ",$len - strlen($line))." ##\n";
}
$cronHeader .= $b."\n";

ob_start();

$GLOBALS["slConfig"] = require("inc/config.php");
$GLOBALS["slCronSession"] = true;
$GLOBALS["slSetupMode"] = false;

$indivCron = isset($_GET["n"]) ? $_GET["n"] : false;

require_once("inc/initialize.php");

require("inc/notRunnableFromWeb.php");

$cronRunningFile = SL_DATA_PATH."/cron-running".($indivCron ? "-".$_GET["n"] : "");

if (!is_writable(SL_DATA_PATH)) {
	echo SL_DATA_PATH." not writable"; exit();
}

if (is_file($cronRunningFile) && !is_writable($cronRunningFile)) {
	echo $cronRunningFile." not writable"; exit();
}


$maxRun = max(ini_get('max_execution_time'),180);

if (is_file($cronRunningFile) && filemtime($cronRunningFile) + $maxRun > time()) {
	echo "= ".date('Y-m-d H:i:s')."= \n\tcron.php already running. Started at ".date('Y-m-d H:i:s', filemtime($cronRunningFile))."\n";
	exit();
}

touch($cronRunningFile);

$dirs = array("inc"=>SL_INCLUDE_PATH."/cron","lib"=>SL_LIB_PATH."/cron");

// Get app crons
getAppCrons($dirs);

$jobs = array();

foreach ($dirs as $n=>$dir) {
	if ($dp = opendir($dir)) {
		while ($file = readdir($dp)) {
			$name = "def-".$n."-".preg_replace("/[^\w\d]+/","-",$file);
			if (!$indivCron || $name === $indivCron) {
				$path = $dir."/".$file;
				if (is_file($path) && substr($file,-4) == ".php") {
					$c = file_get_contents($path,NULL,NULL,0,1024);
					if (($pos = strpos($c,"//Run:")) !== false) {
						$pos += 6;
						if (($end = strpos($c,"\n",$pos)) !== false) {
							
							$desc = trim(substr($c,$pos,$end-$pos));
							
							//Options?
							$options = array();
							if (($pos = strpos($c,"//Options:")) !== false) {
								$pos += 10;
								if (($end = strpos($c,"\n",$pos)) !== false) {
									if ($d = json_decode(trim(substr($c,$pos,$end-$pos)), true)) $options = $d;
								}
							}
							
						
							$def = $GLOBALS["slSession"]->getUserData($name);
							if (!$def) $def = array("type"=>"","value"=>"","file"=>pathToRelative($path),"lastrun"=>0);
							
							$def["options"] = $options;
							
							if (substr($desc,0,5) == "every") {
								$def["type"] = "every";
								$v = trim(substr($desc,5));
								if (preg_replace("/[^\d]/","",$v) == "") $v = "1 ".$v;
								$def["value"] = strtotime($v,0);
							} else {
								$def["type"] = "standard";
								$def["value"] = trim($desc);
							}
							$jobs[$name] = $def;
						}
					}
				}
			}
		}
		closedir($dp);
	}
}

$GLOBALS["slCronTs"] = time();
$GLOBALS["slCronDay"] = floor($GLOBALS["slCronTs"] / 86400);
$GLOBALS["slCronLastTs"] = $GLOBALS["slSession"]->getUserData("cron-last-run");
$GLOBALS["slSession"]->setUserData("cron-last-run",$GLOBALS["slCronTs"]);

foreach ($jobs as $name=>$def) {
	$GLOBALS["slCronName"] = $name;
	if (cronShouldRun($def)) {
		if (setAndTrue($def["options"],"separateProcess") && $indivCron !== $name) {
			echo runScript("cron.php", array("n"=>$name));
		} else {
			ob_start();
			include($def["file"]);
			$c = ob_get_clean();
			if ($c) echo "== ".$def["file"]." ==\n".$c."\n\n";
			
			$def["lastrun"] = time();
			$GLOBALS["slSession"]->setUserData($name,$def);
		}
	}	
}

$c = ob_get_clean();
if (strlen(trim($c))) {
	echo $cronHeader.$c."\n";
	if (ob_get_contents() !== false) ob_flush();
} else {
	echo ".";
}

if (isset($GLOBALS["slSession"])) $GLOBALS["slSession"]->__destruct(); // Required to properly end session

unlink($cronRunningFile);

function getCronData($n,$def = null) {
	return $GLOBALS["slSession"]->fileDataGet($GLOBALS["slCronName"],$n,$def);
}

function setCronData($n,$v) {
	return $GLOBALS["slSession"]->fileDataSet($GLOBALS["slCronName"],$n,$v);
}

function cronShouldRun($def) {
	switch ($def["type"]) {
		case "every":
			return time() >= $def["lastrun"] + $def["value"] - 1;
		
		case "standard":
			$match = explode(" ",$def["value"]);
			$start = floor($GLOBALS["slCronTs"]/60)*60;
			$end = floor($GLOBALS["slCronLastTs"]/60)*60;
			if ($start == $end) return false;
			for ($ts = $start; $ts < $end; $ts += 60) {
				$now = explode("-",date("i-G-j-n-w",$ts));
				for ($i = 0; $i < count($match); $i++) {					
					$every = false;
					if ($match[$i] == "*" || "".$match[$i] === "".$now[$i]) continue;
					
					if (strpos($match[$i],"/") !== false) {
						$every = explode("/",$match[$i],2);
						$r = $every[0];
						$every = $every[1];
					} else $r = $match[$i];
					
					$range = array();
					if (strpos($r,"-") !== false) {
						$r = explode("/",$r,2);
						for ($j = $r[0]; $j <= $r[1]; $i++) {
							$range[] = "".$j;
						}
					} elseif (strpos($r,",") !== false) {
						$r = explode(",",$r);
						for ($j = 0; $j < count($r); $j ++) {
							$range[] = "".trim($r[$j]);
						}
					} else {
						$range = "*";
					}
					
					if (
						($range == "*" || in_array($now[$i],$range)) &&
						($every === false || ($now[$i] % $every) == 0)
					) continue;
					
					return false;
				}
			}
			return true;
	}
	return false;
}

function getAppCrons(&$dirs, $dir = "lib/app") {
	if ($dp = opendir($dir)) {
		while ($file = readdir($dp)) {
			$path = $dir."/".$file;
			if (!($file == "." || $file == "..") && is_dir($path)) {
				if (is_file($path."/manifest.json")) {
					//$manifest = json_decode(file_get_contents($path."/manifest.json"), true);
					//if (setAndTrue($manifest,"usesCron")) 
					if (is_dir($path."/cron")) $dirs[safeFile(str_replace("lib/","",$path))] = $path."/cron";
					getAppCrons($dirs,$path);
				}
			}
		}
		closedir($dp);
	}
}
