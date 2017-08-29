<?php

require_once(dirname(__FILE__).'/class.ConfigCache.php');

if (defined("SL_INITIALIZED")) {
	file_put_contents(SL_DATA_PATH."/debug.txt",print_r(debug_backtrace(),true));
	return;
}

define('SL_SID',isset($GLOBALS["slConfig"]["slSID"])?$GLOBALS["slConfig"]["slSID"]:'slSID');

if (!isset($slSetupMode)) $slSetupMode = false;

if (is_file("inc/data/version")) {
	list($version,$versionCode) = explode("\n",file_get_contents("inc/data/version"));
	$GLOBALS["slConfig"]["version"] = $version;
	$GLOBALS["slConfig"]["versionCode"] = $versionCode;
}

require("inc/license.php");

if (!isset($GLOBALS["slConfig"]["core"]["name"])) $GLOBALS["slConfig"]["core"]["name"] = "admin";
define('SL_CORE_NAME',$GLOBALS["slConfig"]["core"]["name"]);

if (!isset($GLOBALS["slNoSession"])) $GLOBALS["slNoSession"] = false;
if (!isset($GLOBALS["slCronSession"])) $GLOBALS["slCronSession"] = false;
$GLOBALS["slBypassPerimssions"] = false;

define("SL_BASE_PATH",realpath(dirname(__FILE__)."/.."));
define('USING_SSL',isset($GLOBALS["_SERVER"]['HTTPS']));

if (isset($_SERVER["REQUEST_URI"])) {
	
	$redirFile = SL_BASE_PATH."/web/inc/redirects";
	
	if (is_file($redirFile) && ($fp = fopen($redirFile,"r"))) {
		
		$p = explode("/",array_shift(explode("?",$_SERVER["REQUEST_URI"])));
		if ($p[count($p)-1] == "") array_pop($p);
		$p = array_pop($p);
		
		$ruri = array_shift(explode("?",$_SERVER["REQUEST_URI"]));
		
		while (!feof($fp)) {
			$re = explode(">",trim(fgets($fp)),2);
			if (count($re) != 2) continue;
			
			if ($re[0] != $p && $re[0] != substr($ruri,-strlen($re[0]))) continue;
			
			if (strpos($re[1],":") !== false && strpos($re[1],"://") === false) {
				$r = explode(":",$re[1],2);
				$data = json_decode($r[1],true);
				$re[1] = $r[0];
			} else $data = array("type"=>301);
			
			if (substr($re[1],0,1) == "/") $re[1] = $GLOBALS["slConfig"]["web"]["canonicalRoot"].$re[1];
			
			if ($data["type"] == 302) {
				header("HTTP/1.1 302 Moved Temporarily"); 
			} else {
				header("HTTP/1.1 301 Moved Permanently"); 
			}
			
			header("Location: ".$re[1]); 
			fclose($fp);
			exit();			
		}
		fclose($fp);
		if (isset($GLOBALS["slConfig"]["web"]['forceHTTPS']) && $GLOBALS["slConfig"]["web"]['forceHTTPS']) {
			$GLOBALS["slConfig"]["web"]["canonicalRoot"] = str_replace('http://','https://',$GLOBALS["slConfig"]["web"]["canonicalRoot"]);
			if (!USING_SSL) {
				header("HTTP/1.1 301 Moved Permanently"); 
				$uri = explode("?",$_SERVER["REQUEST_URI"]);
				if (substr($uri[0],-5) == "home/") $uri[0] = substr($uri[0],0,-5);
				if (substr($uri[0],-1) != '/') $uri[0] .= '/';
				header("Location: ".$GLOBALS["slConfig"]["web"]["canonicalRoot"].implode("?",$uri));
				exit();
			}
		}
	}
	
	if (($pos = strpos($_SERVER["REQUEST_URI"],SL_SID."=")) != false) {
		$pos += strlen(SL_SID) + 1;
		$end = strpos($_SERVER["REQUEST_URI"],"&",$pos);
		if ($end === false) $end = strpos($_SERVER["REQUEST_URI"],"?",$pos);
		if ($end === false) $end = strlen($_SERVER["REQUEST_URI"]);
		session_id(substr($_SERVER["REQUEST_URI"],$pos,$end-$pos));
		session_start();
	} elseif (isset($_SERVER["HTTP_USER_AGENT"]) && strpos($_SERVER["HTTP_USER_AGENT"],"YibblePoint") !== false) {
		//Nada
	} elseif (!isset($_SERVER["HTTP_COOKIE"])) {
		if (array_shift(explode("?",array_pop(explode("/",$_SERVER["REQUEST_URI"])))) !== "") {
			//TODO: This is causing issue in new sessions
			//$GLOBALS["slNoSession"] = true;
			//$GLOBALS["slNoCSCookies"] = true;
		}
	} elseif (strpos($_SERVER["REQUEST_URI"],$GLOBALS["slConfig"]["core"]["name"]."/js/") !== false) {
		$GLOBALS["slNoSession"] = true;
	}
}

if ($slSetupMode) $GLOBALS["slNoSession"] = false;


$GLOBALS["slScriptStartTS"] = microtime(true);
$GLOBALS["slScriptLoad"] = 0;

$_SESSION["scriptRunTime"] = array();

define("SL_LIB_PATH",realpath(dirname(__FILE__)."/../lib"));
define("SL_INCLUDE_PATH",realpath(dirname(__FILE__)));
define("SL_DATA_PATH",realpath(dirname(__FILE__)."/../data"));

$dev = false;
if (isset($_SERVER["REQUEST_URI"])) {
	$REQUEST_URI = $_SERVER["REQUEST_URI"];
	if (strpos($REQUEST_URI,"dev/")) {
		$REQUEST_URI = str_replace("dev/","",$REQUEST_URI);
		$dev = true;
	}
}

define("DEV_MODE",$dev);
if ($dev) $slConfig["dev"]["debug"] = true;

define("SL_WEB_PATH",realpath(dirname(__FILE__).($dev?"/../web/dev":"/../web")));
define("SL_WEB_PATH_NON_DEV",realpath(dirname(__FILE__)."/../web"));

if (!defined("SL_INITIALIZED")) {
	function isOldIE() {
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$match = array();
			if (preg_match('/MSIE\s*([\d\.]+)/',$_SERVER['HTTP_USER_AGENT'],$match)) {
				if ($match[1] < 9) return true;
			}
		}
		return false;
	}

	define("IS_IE",isOldIE());
}

require(SL_INCLUDE_PATH."/base.php");

if (!$slSetupMode && isset($_SERVER["REMOTE_ADDR"]) && !isset($_GET["_SKEY"]) && setAndTrue($GLOBALS["slConfig"]["web"],"trafficTracking")) {
	session_name(SL_SID); session_start();

	require(SL_INCLUDE_PATH."/visit.php");
	if (!isset($_SESSION["VISIT_RECORDED"])) {
		require(SL_INCLUDE_PATH."/recordVisit.php");
	}
}


$h = function_exists('apache_request_headers') ? apache_request_headers() : false;

if (!($h && isset($h['User-Agent']) && strpos($h['User-Agent'], 'YibblePointCrawler') !== false) && !$GLOBALS["slConfig"]["dev"]["debug"] && !isset($_GET["fromEditor"]) && isset($_SERVER["REQUEST_URI"]) && !setAndTrue($_GET,"nocache") && !setAndTrue($_SESSION,"NO_PAGE_CACHE") && setAndTrue($GLOBALS["slConfig"]["web"], "enableCaching")) { // Don't use cache when debugging
	session_name(SL_SID);
	session_start();
	
	$sf = $_SERVER["REQUEST_URI"]."-".(setAndTrue($_SESSION,"userID")?session_id()."-":"").(setAndTrue($_SESSION,"cacheID")?$_SESSION["cacheID"]."-":"").$GLOBALS["slConfig"]["international"]["language"].(USING_SSL?'-ssl':'');
		
	$cacheFile = SL_DATA_PATH."/cache/".substr(md5($sf),0,2)."/".safeFile($sf,true);

	if (is_file($cacheFile)) {
		if (is_file($cacheFile.'.qoc')) {
			//TODO: Queue on cache
		}
		if (file_get_contents($cacheFile,false,NULL,0,15) == "!lg-cache-head:") {
			require_once(SL_INCLUDE_PATH."/class.slCache.php");
			$cache = new slCache($cacheFile,true);
			if (!$cache->isExpired()) {
				header("Content-Type: text/html; charset=utf-8"); //TODO this should be set from the cache file
				$cache->out(true);
				echo "\n<!--Cached: ".pathToRelative($cacheFile)." @ ".date("Y-m-d H:i:s T", filemtime($cache->cacheFile))." -->";
				if (function_exists("recordVisitData")) recordVisitData("page",array("uri"=>$_SERVER["REQUEST_URI"]));
				exit();
			}
		} else {
			if (filemtime($cacheFile) > time() - 60) {
				header("Content-Type: text/html; charset=utf-8"); //TODO this should be set from the cache file
				readfile($cacheFile);
				echo "\n<!-- Cached: ".pathToRelative($cacheFile)." @ ".date("Y-m-d H:i:s T", filemtime($cache->cacheFile))." -->";
				if (function_exists("recordVisitData")) recordVisitData("page",array("uri"=>$_SERVER["REQUEST_URI"]));
				exit();
			}
		}	
	}
}

$configDefault = require(SL_INCLUDE_PATH."/config.default.php");

setDefaults($GLOBALS["slConfig"],$configDefault);

if (isset($_SESSION["isMobile"])) {
	$GLOBALS["slConfig"]["isMobile"] = $_SESSION["isMobile"];
} else {
	if (isset($_SERVER['HTTP_USER_AGENT'])) {
		if (requireThirdParty("Mobile-Detect",true)) {
			$detect = new Mobile_Detect;
			$_SESSION["isMobile"] = $GLOBALS["slConfig"]["isMobile"] = $detect->isMobile();
		} else $GLOBALS["slConfig"]["isMobile"] = false;
	} else $GLOBALS["slConfig"]["isMobile"] = false;
}

if ($GLOBALS["slConfig"]["isMobile"]) $GLOBALS["slConfig"]["noEfx"] = true;

if (!defined("SL_INITIALIZED")) {
	function preOut($text) {
		if (setAndTrue($_SERVER,"REMOTE_ADDR")) {
			echo "<pre>".$text."</pre>";
		} else {
			echo htmlspecialchars_decode(strip_tags($text))."\n";
		}
	}

	function defaultErrHandler(){ return false; }

	function showErrors() {
		$GLOBALS["_OLD_ERROR"] = array(ini_get('display_errors'),ini_get('log_errors'),set_error_handler("defaultErrHandler"));
		ini_set('display_errors',1);
		ini_set('log_errors',0);
	}

	function restoreErrors() {
		ini_set('display_errors',$GLOBALS["_OLD_ERROR"][0]);
		ini_set('log_errors',$GLOBALS["_OLD_ERROR"][1]);
		if ($GLOBALS["_OLD_ERROR"][2]) set_error_handler($GLOBALS["_OLD_ERROR"][2]);
	}
	
	function surpressErrors($v) {
		$GLOBALS["slConfig"]["bypassError"] = $v;
	}	
}

if ($GLOBALS["slCronSession"] && !setAndTrue($GLOBALS["slConfig"]["dev"],"logCronErrors")) {
	ini_set('display_errors',1);
	ini_set('log_errors',0);
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	
} elseif ($GLOBALS["slConfig"]["dev"]["debug"] || DEV_MODE) {
	$GLOBALS["slErrNum"] = 0;
	$GLOBALS["slErrBuffLen"] = 0;
	
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	
	ini_set('display_errors',1);
	ini_set('log_errors',0);
	
} elseif (($GLOBALS["slCronSession"] && setAndTrue($GLOBALS["slConfig"]["dev"],"logCronErrors")) || setAndTrue($GLOBALS["slConfig"]["dev"],"logErrors")) {
	ini_set('display_errors',0);
	ini_set('log_errors',1);
} else {
	ini_set('display_errors',0);
	ini_set('log_errors',1);
}


if (!isset($GLOBALS["slConfig"]["security"]["keyPath"])) {
	$check = array();
	$pwuid = posix_getpwuid(posix_geteuid());
	$user = array_shift($pwuid);
	if (
		($home = getenv('HOME')) || 
		(is_dir($home = '/home/'.$user))
	) {
		$check[] = $home;
	}
	$path = explode("/",dirname(__FILE__));
	while (array_pop($path)) {
		$c = implode("/",$path);
		if (is_dir($c) && is_readable($c)) $check[] = $c;
	}
	
	foreach ($check as $path) {
		$file = $path.'/'.$GLOBALS["slConfig"]["security"]["keyFile"];
		if (is_file($file) && is_readable($file)) {
			ConfigCache::set('security','keyPath', $path);
			break;
		}
	}
	
	ConfigCache::enforce('security','keyPath',null);
}


if (isset($_SERVER["SERVER_NAME"]) && isset($_SERVER["DOCUMENT_ROOT"])) {
	file_put_contents(SL_DATA_PATH."/_SERVER",json_encode(array("SERVER_NAME"=>$_SERVER["SERVER_NAME"],"DOCUMENT_ROOT"=>$_SERVER["DOCUMENT_ROOT"])));
} else {
	$_SERVER = array_merge($_SERVER,json_decode(file_get_contents(SL_DATA_PATH."/_SERVER"),true));
}

$dp = substr($GLOBALS["slConfig"]["root"],strlen($_SERVER["DOCUMENT_ROOT"]));
if (substr($dp,0,1) != "/" && $dp != "") $dp = "/".$dp;

if (isset($_SERVER["REDIRECT_URL"]) && $_SERVER["REDIRECT_URL"] != substr($_SERVER["REQUEST_URI"],0,strlen($_SERVER["REDIRECT_URL"]))) {
	$dpNew = explode("/",array_shift(explode("?",$_SERVER["REQUEST_URI"])));
	while (count($dpNew) > count(explode("/",$dp))) {
		array_pop($dpNew);
	}
	$dp = implode("/",$dpNew);
	define("SL_REWRITE",$dp);
} else {
	define("SL_REWRITE",false);
}

$GLOBALS["slConfig"]["docParent"] = $dp;

define("WWW_ROOT","http://".$_SERVER["SERVER_NAME"].$GLOBALS["slConfig"]["docParent"]);
//if (isset($_GET["test"])) { echo WWW_ROOT; exit(); }

if (!isset($GLOBALS["slConfig"]["net"]["httpTimeout"])) $GLOBALS["slConfig"]["net"]["httpTimeout"] = (int)ini_get("max_execution_time") + 1;

define("SL_KEY_FILE",$GLOBALS["slConfig"]["security"]["keyPath"].$GLOBALS["slConfig"]["security"]["keyFile"]);

if (!defined("SL_INITIALIZED")) {
	function decryptConfig(&$a) {
		foreach ($a as $n=>$o) {
			if (is_array($o)) {
				if (isset($o[0]) && $o[0] == "!ENCRYPTED") {
					$a[$n] = doEncOrDec($o[1],false);
				} else {
					decryptConfig($a[$n]);
				}
			}
		}
	}
}
decryptConfig($GLOBALS["slConfig"]);


$GLOBALS["slCore"] = new slCore($GLOBALS["slConfig"]);

if (!$slSetupMode) {
	$GLOBALS["slCore"]->setDB(new slDB($GLOBALS["slConfig"]["db"]));

	$GLOBALS["slCore"]->db->connect(array("type"=>"global"));
}

if (!$GLOBALS["slNoSession"]) {
	$GLOBALS["slSession"] = new slSession($GLOBALS["slConfig"]);
	
	$GLOBALS["slSession"]->getUserStatus();

	define("SL_USER_DATA_PATH",$GLOBALS["slSession"]->getUserDir());
	if (!$slSetupMode) $GLOBALS["slCore"]->db->connect(array("type"=>"user","session"=>$GLOBALS["slSession"]));
}

if ($GLOBALS["slCore"]->db) {
	$GLOBALS["slCore"]->db->connect(array(
		"type"=>"xml",
		"dir"=>SL_WEB_PATH
	));
}

define("LOCAL_SERVER",$_SERVER["SERVER_NAME"] == "localhost" || $_SERVER["SERVER_NAME"] == "127.0.0.1");

define("SL_THEME_PATH",SL_INCLUDE_PATH."/themes/".$GLOBALS["slConfig"]["core"]["theme"]);
define("SL_THEME_PATH_WWW","themes/".$GLOBALS["slConfig"]["core"]["theme"]);
define("SL_ROOT",$GLOBALS["slConfig"]["root"]);

if (isset($_SERVER["HTTP_HOST"])) {
	define("WWW_BASE_NON_DEV",(USING_SSL?"https":"http")."://".$_SERVER["HTTP_HOST"].$GLOBALS["slConfig"]["docParent"]."/");
	define("WWW_BASE",WWW_BASE_NON_DEV.(DEV_MODE?"dev/":""));
} else {
	define("WWW_BASE_NON_DEV",$GLOBALS["slConfig"]["web"]["canonicalRoot"]."/");
	define("WWW_BASE",WWW_BASE_NON_DEV);
}

define("WWW_RELATIVE_BASE",isset($REQUEST_URI) ? str_repeat("../",max(0,count(explode("/",$REQUEST_URI)) - count(explode("/",$GLOBALS["slConfig"]["docParent"])) - 1)) : "");

define("USE_REQUEST_URI", isset($REQUEST_URI) ? $REQUEST_URI : "");

$ururi = explode('?',USE_REQUEST_URI);
define("USE_REQUEST_URI_BASE", rtrim(array_shift($ururi),'/'));

define("CORE_NAME",$GLOBALS["slConfig"]["core"]["name"]);
define("CORE_NAME_LEN",strlen($GLOBALS["slConfig"]["core"]["name"]));

define("SL_INITIALIZED",1);

define("SEP",$GLOBALS["slConfig"]["sep"]);

if (setAndTrue($GLOBALS,"_IGNORE_PROTECTED_FILE_CHECK")) return;

$ruri = explode("?",isset($_SERVER["REQUEST_URI"])?$_SERVER["REQUEST_URI"]:'');
$file = $_SERVER["DOCUMENT_ROOT"].array_shift($ruri);

if (is_file($file)) {
	$ext = strtolower(array_pop(explode(".",$file)));
	if ($ext == "php" && !(array_pop(explode("/",$file)) == "index.php" && strpos($_SERVER["REQUEST_URI"],"?") === false)) {	
		if (strpos(file_get_contents($file, false, NULL, 0, 200), '// DO NOT PROTECT') === false) {
			file_put_contents(SL_DATA_PATH."/security-log","Requested protected file '$file'\n".
			"\tREMOTE_ADDR: ".$_SERVER["REMOTE_ADDR"].":".$_SERVER["REMOTE_PORT"]."\n".
			"\tREQUEST_URI: ".$_SERVER["REQUEST_URI"]."\n"
			,FILE_APPEND);
			header("HTTP/1.0 404 Not Found");
			exit();
		}
	}
}

