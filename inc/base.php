<?php

require(SL_INCLUDE_PATH."/class.slCore.php");
require(SL_INCLUDE_PATH."/class.slClass.php");
require(SL_INCLUDE_PATH."/class.slRecord.php");
require(SL_INCLUDE_PATH."/class.slRequestInfo.php");
require(SL_INCLUDE_PATH."/security.php");
require(SL_INCLUDE_PATH."/class.slDB.php");
require(SL_INCLUDE_PATH."/class.slPermissions.php");
require(SL_INCLUDE_PATH."/class.slSession.php");
require(SL_INCLUDE_PATH."/class.slUser.php");
require(SL_INCLUDE_PATH."/class.slTranslator.php");

// PHP Compatibility
$v = explode(".",phpversion()); $vNum = 0; $p = 1;
while ($n = array_shift($v)) {$vNum += $n * $p;$p /= 1000;}
if ($vNum < 5.3) require(SL_INCLUDE_PATH.'/php-compatibility.php');

$d = explode("/",dirname(__FILE__));
array_pop($d);
define("LGPHP_ROOT_DIR",implode("/",$d));
define("LGPHP_ROOT_WWW",substr(LGPHP_ROOT_DIR,strlen($_SERVER["DOCUMENT_ROOT"])));

function safeFile($file,$noLoss = false,$sep = "_") {
	if (strlen($file) > 128) $file = substr($file,0,20)."-".md5($file);
	if ($noLoss) return str_replace(array("%20","%"),array($sep,"-"),rawurlencode($file));
	return preg_replace('/[^\w\d\-\_\.]+/',$sep,charNormalize($file));
}

function quick_md5_file($file, $raw = false) {
	return md5(filesize($file).file_get_contents($file,0,NULL,0,100000));
}

function pathToRelative($path) {
	return str_replace(getcwd()."/","",$path);
}

function webPathFromRelative($path, $relative = false) {
	$bt = debug_backtrace();
	return webPath(realpath(dirname($bt[0]["file"]))."/".$path, $relative);
}

function webPath($path,$relative = false) {
	
	$WWW = $relative ? WWW_RELATIVE_BASE : WWW_BASE;
	$from = array(SL_WEB_PATH."/",LGPHP_ROOT_DIR."/",$_SERVER['DOCUMENT_ROOT'].'/');
	$to = array($WWW, $WWW, '/');
	
	if (defined('SL_TEMPLATE_PATH')) {
		array_unshift($from, SL_TEMPLATE_PATH.'/');
		array_unshift($to, $WWW);
	}
	return str_replace($from,$to,$path);
}

function get_absolute_path($path) {
	$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
	$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
	$absolutes = array();
	foreach ($parts as $part) {
		if ('.' == $part) continue;
		if ('..' == $part) {
			array_pop($absolutes);
		} else {
			$absolutes[] = $part;
		}
	}
	return implode(DIRECTORY_SEPARATOR, $absolutes);
}


function loadMimes() {
	if (!isset($GLOBALS["MIME_TYPES"])) $GLOBALS["MIME_TYPES"] = json_decode(getStringBetween("/*MIME_LIST*/","/*END_MIME_LIST*/",file_get_contents(SL_INCLUDE_PATH."/js/core/mime.js")));
}

function dbgvar($var) {
	showJSON($var, "code");
}

function showJSON($data, $type = false) {		
	if ($type === false) $type = setAndTrue($GLOBALS["slConfig"]["dev"],"debug") ? 'comment' : 'hidden-dev';
	if (($json = json_encode($data, JSON_PRETTY_PRINT)) === false) $json = "(COULD NOT ENCODE JSON)";
	switch ($type) {
		case "hidden":
		case "hidden-dev":
			$showAnyway = DEV_TEST && $type == 'hidden-dev';
			echo "<pre class='showJSON'".($showAnyway ? "" : " style='display:none'").">\n".
				($showAnyway ? htmlspecialchars($json) : str_replace(array('<','>'),array('&lt;','&gt;'),$json)).
				"\n</pre>";
			return;
		
		case "pre":
			echo "\n<pre>\n".htmlspecialchars($json)."\n</pre>\n";
			return;
		
		case "comment":
			echo "\n<!--\n".str_replace('-->', '--(END COMMENT)', $json)."\n-->\n";
			return;
		
		
		case "code":
			echo "\n<code>\n".str_replace(array("\t"," ","\n"),array("&nbsp;&nbsp;&nbsp;&nbsp;","&nbsp;","<br>"),htmlspecialchars($json))."\n</code>\n";
			return;
			
		case "html":
		    echo str_replace(array("\t"," "),array("&nbsp;&nbsp;&nbsp;&nbsp;","&nbsp;"),htmlspecialchars($json));
		    return;	
		    
	}
	echo $json;
}

function fileMime($file,$fullInfo = false) {
	$ext = strtolower(array_pop(explode(".",$file)));
	
	loadMimes();
	
	if ($mime = mimeInfo($ext))	return $fullInfo ? $mime : $mime[0];
	
	return false;
}

function mimeInfo($v,$from = "extension") {
	$map = array("name","mime","extension");
	if (($from = array_search($from,$map)) === false) return false;
	
	if ($from == 0) $v = searchify($v);
	
	loadMimes();
	
	foreach ($GLOBALS["MIME_TYPES"] as $mime) {
		switch ($from) {
			case 0:
				if (searchify($mime[$from]) == $v) return $mime;
				break;
			
			case 1:
				if ($mime[$from] == $v) return $mime;
				break;
				
			case 2:
				$e = explode(",",$mime[2]);
				foreach ($e as $o) {
					if (trim($o) == $v) return $mime;
				}
				break;
		}
	}
	return false;
}

function mimeIcon($file) {
	$ext = strtolower(array_pop(explode(".",$file)));
	
	$mimes = json_decode(getStringBetween("/*MIME_LIST*/","/*END_MIME_LIST*/",file_get_contents(SL_INCLUDE_PATH."/js/core/mime.js")));
	
	foreach ($mimes as $mime) {
		$e = explode(",",$mime[2]);
		foreach ($e as $o) {
			if (trim($o) == $ext) {
				$file = SL_THEME_PATH."/mime/".safeFile($mime[1],false,"-").".png";
				if (is_file($file)) return webPath($file);
				
				$file = SL_THEME_PATH."/mime/".safeFile(array_shift(explode("/",$mime[1])),false,"-").".png";
				if (is_file($file)) return webPath($file);
			}
		}
	}
	
	return SL_THEME_PATH."/mime/file.png";
}

function safeName($string) {
	return trim(preg_replace('/[^A-Za-z\d]+/','-',strtolower(charNormalize($string))),'-');
}

function searchify($string, $delim = ' ',$replace = false) {
	if ($replace) {
		if (!isset($GLOBALS["SL_BROADSEARCH_REP"])) {
			$GLOBALS["SL_BROADSEARCH_REP"] = array();
			
			$file = SL_INCLUDE_PATH."/data/abbreviations/".$GLOBALS["slConfig"]["international"]["language"];
			if (is_file($file)) {
				$abbr = explode("\n",file_get_contents($file));
				$abbr[0] = explode(",",$abbr[0]);
				$abbr[1] = explode(",",$abbr[1]);
				$GLOBALS["SL_BROADSEARCH_REP"] = array($abbr[0],$abbr[1]);
			}
		}
		$string = strtolower($string);
		$string = str_replace($GLOBALS["SL_BROADSEARCH_REP"][0],$GLOBALS["SL_BROADSEARCH_REP"][1],$string);
	}
	$string = str_replace(array(",",".","'","\"","!","?","-"),"",$string);
	return preg_replace('/[^A-Za-z\d]+/',$delim,strtolower(charNormalize($string)));
}


function broadSearchify($string) {
	return searchify($string,'',true);
}
	 
function charNormalize($string) {
	$a = array('À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','¥','Þ','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ð','ñ','ò','ó','ô','õ','ö','ø','ù','ú','û','ý','ý','þ','ÿ','Ŕ','ŕ');
	$b = str_split('AAAAAAACEEEEIIIIDNOOOOOOUUUUYYbSaaaaaaaceeeeiiiidnoooooouuuyybyRr'); 	
	return str_replace($a, $b, $string); 
}

function getLabeledCSV($file,$notInline = false,$groupBy = false) {
	if ($fp = openAndLock($file,"r")) {
		$rv = array();
		
		$labels = fgetcsv($fp);
		
		$cols = count($labels);
		
		if (!$notInline) {
			foreach ($labels as $n) {
				$rv[$n] = array();
			}
		}
		
		while (!feof($fp)) {
			$d = fgetcsv($fp);
			if (!(count($d) == 1 && trim($d[0]) == "")) {
				$row = array();
				for ($i = 0; $i < $cols; $i++) {
					if ($notInline) {
						$row[$labels[$i]] = isset($d[$i]) ? $d[$i] : "";
					} else {
						$rv[$labels[$i]][] = isset($d[$i]) ? $d[$i] : "";
					}
				}
				if ($groupBy) {
					$g = $row[$groupBy];
					if (!isset($rv[$g])) $rv[$g] = array();
					$rv[$g][] = $row;
				} else {
					$rv[] = $row;
				}
			}
		}
		closeAndUnlock($fp);
		return $rv;
	}
	return null;
}

function is_file_check_src($file) {
	if (is_file($file)) return true;
	if (is_file($file.".src")) {
		$srcs = explode("\n",trim(file_get_contents($file.".src")));
		while ($src = array_pop($srcs)) {
			if ($data = file_get_contents($src)) {
				file_put_contents($file,$data);
				return true;
			}
		}
	}
	return false;
}

function decodeSafeFile($file) {
	return rawurldecode(str_replace(array("-","_"),array("%","%20"),$file));
}

function typeOf($o) {
	if ($o === NULL) return "null";
	if (is_string($o)) return "string";
	if (is_numeric($o)) return "number";
	if (is_bool($o)) return "bool";
	if (is_object($o)) return "object";
	if (is_array($o)) {
		$cnt = 0;
		foreach ($o as $n=>$v) {
			if ($n !== $cnt) return "object";
			$cnt++;
		}
		return "array";
	}
}

function setDefaults(&$a,$defaults) {
	if (is_array($a) && is_array($defaults)) {
		foreach ($defaults as $n=>$v) {
			if (!isset($a[$n])) {
				$a[$n] = $v;
			} elseif (is_array($v)) {
				setDefaults($a[$n],$v);
			}
		}
	}
}

$GLOBALS["slLockedFiles"] = array();
function openAndLock($file,$operation) {
	//$file = realpath($file);
	if (isset($GLOBALS["slLockedFiles"][$file])) {
		$GLOBALS["slLockedFiles"][$file][1] ++;
		fseek($GLOBALS["slLockedFiles"][$file][0],0);
		return $GLOBALS["slLockedFiles"][$file][0];
	}
	if ($fp = fopen($file,$operation)) {
		$endTs = time() + 2;
		while (!flock($fp,LOCK_EX | LOCK_NB)) {			
			//print_r(debug_backtrace()); exit();
			usleep(30000);
			if (time() > $endTs) {
				//echo "Couldn't get lock on $file\n";
				fclose($fp);
				return false;
			}
		}
		$GLOBALS["slLockedFiles"][$file] = array($fp,1);
		return $fp;
	}
	return false;
}

function closeAndUnlock($fp) {
	foreach ($GLOBALS["slLockedFiles"] as $file=>$info) {
		if ($info[0] == $fp) {
			$GLOBALS["slLockedFiles"][$file][1] --;
			if ($GLOBALS["slLockedFiles"][$file][1] > 0) return;
			unset($GLOBALS["slLockedFiles"][$file]);
			break;
		}
	}
	if (!is_resource($fp)) return;
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);	
}

function filePutLock($file,$data,$append = false) {
	if ($fp = openAndLock($file,$append ? "a" : "w")) {
		fputs($fp,$data);
		closeAndUnlock($fp);
		return true;
	}
	return false;
}

function fileGetLock($file,$truncateAfterGet = false, $maxlen = false) {
	if (!is_file($file)) return false;
	if ($fp = openAndLock($file,$truncateAfterGet ? "c+" : "r")) {
		if ($maxlen !== false) {
			$rv = fread($fp,$maxlen);
		} else {
			$rv = "";
			while (!feof($fp)) {
				$rv .= fread($fp,1024*1024);
			}
		}
		if ($truncateAfterGet) {
			ftruncate($fp,0);
		}
		closeAndUnlock($fp);
		return $rv;
	}
	return false;
}

function tmpFilePath($name) {
	$dir = SL_DATA_PATH."/tmp";
	if (!is_dir($dir)) mkdir($dir);
	return $dir."/".md5(date("Y-m-d-H-i-s")."-".session_id())."-".safeFile($name);
}

function getStringBetweenEnd($start, $end, $orig_string) {
	if (($pos = strpos(strtolower($orig_string), strtolower($start))) === false) return false;
	$startPos = $pos + strlen($start);
	$rv = substr($orig_string, $startPos);
	
	$pos = -1;
	
	if (($endPos = getStringBetweenEnd($start, $end, $rv)) !== false) {
		$pos = $endPos;
	}
	
	if (!is_array($end)) $end = array($end);
	
	foreach ($end as $e) {
		if (($p = strpos(strtolower($rv), strtolower($e))) !== false && $p < $pos == -1 ? 1000000 : $pos) {
			$bestEnd = $e;
			$pos = $p;
		}
	}
	
	return $pos == -1 ? false : $startPos + $pos + strlen($bestEnd);
}

function getStringBetween($start, $end, $orig_string, $ignoreEmbedded = false) {
	if (!$start || !$end) return false;
	if (($pos = strpos(strtolower($orig_string), strtolower($start))) === false) return false;
	
	$rv = substr($orig_string, $pos + strlen($start));
	
	if (!is_array($end)) $end = array($end);

	$pos = -1;
	$startPos = 0;
	
	if ($ignoreEmbedded && ($endPos = getStringBetweenEnd($start, $end, $rv)) !== false) {
		$startPos = $endPos;
	}

	foreach ($end as $e) {
		if (($p = strpos(strtolower($rv), strtolower($e), $startPos)) !== false && $p < $pos == -1 ? 1000000 : $pos) {
			$pos = $p;
		}
	}
	return $pos == -1 ? false : substr($rv, 0, $pos);
}

function toCamelCase($string,$firstUpper = false,$allowFirstCharNum = false) {
	$v = explode("-",preg_replace("/[^\w\d]+/","-",$string));
	$rv = array();
	for ($i = 0; $i < count($v); $i ++) {
		if (!$firstUpper && $i == 0) {
			$rv[] = strtolower($v[$i]);
		} else {
			$rv[] = ucfirst(strtolower($v[$i]));
		}
	}
	$rv = implode("",$rv);
	if (!$allowFirstCharNum && preg_replace("/[\d]/","",substr($rv,0,1)) == '') {
		$rv = "_"+$rv;
	}
	return $rv;
}

function delTree($dir) {
	if (!is_dir($dir)) return;
	$files = array_diff(scandir($dir), array('.','..')); 
	foreach ($files as $file) { 
		if (!((is_dir("$dir/$file")) ? delTree("$dir/$file") : @unlink("$dir/$file"))) return false;
	} 
	return @rmdir($dir); 
} 
  
function makePath($path, $createMissing = true) {
	$path = realpath($path);
	if (substr($path,0,strlen(LGPHP_ROOT_DIR)) != LGPHP_ROOT_DIR) return false;

	if ($createMissing) {
		exec('mkdir -p '.escapeshellarg($path), $out, $rv);
		if ($rv != 0) {
			//TODO: try native PHP function?
			return false;
		}
	}
	return $path;
}

function arrayFind($a,$find) {
	foreach ($find as $n=>$v) {
		if (!isset($a[$n]) || $find[$n] != $a[$n]) return false;
	}
	return true;
}

function requireThirdParty($module,$noError = false) {
	if (isset($GLOBALS["slConfig"]["thirdparty"][$module."-master"])) $module .= "-master";

	if (isset($GLOBALS["slConfig"]["thirdparty"][$module])) {
		$def = $GLOBALS["slConfig"]["thirdparty"][$module];
	
		if (isset($def["className"])) {
			if (class_exists($def["className"])) return;
			
			$paths = explode(":",get_include_path());
			foreach ($paths as $path) {
				$file = $path."/".$def["packageDir"]."/".$def["include"];
				if (!$path != "." && file_exists($file)) {
					require($file);
					return true;
				}
			}
		}
		
		$path = SL_INCLUDE_PATH."/thirdparty/".$module;
		$file = $path."/".$def["include"];
		
		if (is_file($file)) {
			if (isset($GLOBALS["slConfig"]["thirdparty"][$module]["setIncudePath"])) set_include_path(dirname($file) . PATH_SEPARATOR . get_include_path());
			require($file);
			return true;
		} else {
			if ($noError) return false;
			die("Third party module '$module' is not installed. <br />Download from <a href=\"".$def["url"]."\">".$def["url"]."</a>, and install to<br />".$path);
		}
	} else {
		if ($noError) return false;
		die("Third party module '$module' not defined.");
	}
}


function formatAsSearchText($data,$info = "") {
	switch (isset($info["type"]) ? $info["type"] : "") {
		default:
			return preg_replace("/[^\w\d]+/"," ",charNormalize($data));
	}
}

$debug_microtime = array();
function debugTimerStart() {
	global $debug_fp, $debug_microtime;
	$debug_microtime[] = microtime(true);
}

function debugTimerEnd($txt, $perLabel = false, $perCnt = 0) {
	global $debug_fp, $debug_microtime;
	
	$len = microtime(true) - array_pop($debug_microtime);
	$mt = sprintf("%01.5f", $len);
	if ($perLabel) {
		$mtp = sprintf("%01.5f", $len / $perCnt);
		$txt = str_repeat("\t", count($debug_microtime)).$txt.": ".$mt." (".$mtp."/".$perLabel." - ".$perCnt." ".$perLabel."s)\n";
	} else {
		$txt = str_repeat("\t", count($debug_microtime)).$txt.": ".$mt."\n";
	}
	if ($debug_fp) {
		fputs($debug_fp, $txt);
	} else {
		echo $txt;
	}
}

function tagAdd(&$text, $pos, $add) {
	$text = substr($text,0,$pos).$add.substr($text,$pos);
	return strlen($add);
}


function tagRemove(&$text, $start, $end, $replace = false) {
	$text = substr($text,0,$start).($replace ? $replace : "").substr($text,$end);
	return ($end - $start) - ($replace ? strlen($replace) : 0);
}

function tagParse($text,$replace = false) {
	if ($replace === false) $replace = $GLOBALS["slConfig"];
	
	if (strpos($text,"{{") !== false) {
		$pos = 0;
		while (($pos = strpos($text,"{{",$pos)) !== false) {
			$end = strpos($text,"}}",$pos);
			if ($end !== false) {
				$pos += 2;
				$tag = substr($text,$pos,$end - $pos);		
				$end += 2;		
				$pos = $end - tagRemove($text, $pos - 2, $end);
				
				if (($end = strpos($text,"{{/".$tag."}}",$pos)) !== false) {
					$out = array();
					$sect = substr($text,$pos,$end - $pos);
					if (isset($replace[$tag])) {
						foreach ($replace[$tag] as $n=>$v) {
							if (!is_array($v)) {
								$v = array("_VALUE"=>$v);
							}
							$v["_KEY"] = $n;
							$out[] = tagParse($sect,$v);
						}
					}
					
					$pos = $end - tagRemove($text, $pos, $end, implode("",$out));
					tagRemove($text, $pos, $pos + strlen($tag) + 5);
				}
			} else {
				$pos += 2;
			}
		}
	}
	
	if (strpos($text,"[") !== false) {
		$text = explode("[",$text);
		for ($i = 1, $len = count($text); $i < $len; $i++) {
			if (strpos($text[$i],"]") !== false) {
				list($tag,$t) = explode("]",$text[$i],2);
				if (($v = getDeep($replace,$tag)) !== null) {
					$text[$i] = $v.$t;
				} else {
					$text[$i] = "[".$tag."]".$t;
				}
			}
		}
		$text = implode("",$text);
	}
	
	return $text;
}

function getDeep($a,$path,$sep = "/") {
	$path = explode($sep,$path);
	$last = array_pop($path);
	while ($n = array_shift($path)) {
		if (!isset($a[$n])) return null;
		$a = $a[$n];
	}
	return isset($a[$last]) ? $a[$last] : null;
}

function iconResize($iconFile) {
	if (!is_file($iconFile."-24.png") || filemtime($iconFile.".png") > filemtime($iconFile."-24.png")) {
		$sizes = array(16,24,48,96);
		if ($imSource = imagecreatefrompng($iconFile.".png")) {
			$ratio = imagesx($imSource) / imagesy($imSource);
			foreach ($sizes as $size) {
				$imDest = imagecreatetruecolor($size*$ratio,$size);
				imagealphablending( $imDest, false );
				imagesavealpha( $imDest, true );
				imagecopyresampled(
					$imDest, $imSource,
					0, 0, 0, 0,
					$size*$ratio, $size, imagesx($imSource) ,imagesy($imSource)
				);
				imagepng($imDest, $iconFile."-".$size.".png");
				imagedestroy($imDest);
			}
			imagedestroy($imSource);
		}
	}
}

function delimToObject($s,$parts,$delim = ";") {
	$delimEnc = rawurlencode(";"); $rv = array();
	$s = explode($delim,$s);
	for ($i = 0; $i < min(count($s),count($parts)); $i++) {
		$rv[$parts[$i]] = str_replace($delimEnc,$delim,$s[$i]);
	}
	return $rv;
}

function objectToDelim($o,$parts,$delim = ";") {
	if (!$o) $o = array();
	$delimEnc = rawurlencode(";"); $rv = array();

	for ($i = 0; $i < count($parts); $i++) {
		$rv[] = isset($o[$parts[$i]]) ? str_replace($delim,$delimEnc,$o[$parts[$i]]) : "";
	}
	while (count($rv) && trim($rv[count($rv) - 1]) == "") {
		array_pop($rv);
	}
	return implode($delim,$rv);
};


function decodeAddress($address) {
	$address = delimToObject($address,array("address","type","lat","lng","timezone"));
	$address = array_merge(delimToObject($address["address"],array("street","city","state","postalCode","country","street2"),","),$address);
	unset($address["address"]);
	foreach ($address as &$p) {
		$p = trim($p);
	}
	return $address;
}

function encodeAddress($o) {
	$o["address"] = objectToDelim($o,array("street","city","state","postalCode","country","street2"),", ");
	return objectToDelim($o,array("address","type","lat","lng","timezone"));
}

function relevantServer() {
	$a = array("HTTP_USER_AGENT","REMOTE_ADDR","REMOTE_PORT","HTTP_USER_AGENT","HTTP_REFERER","REQUEST_URI");
	$rv = array();
	foreach ($a as $n) {
		if (isset($_SERVER[$n])) $rv[$n] = $_SERVER[$n];
	}
	return $rv;
}

function formatEmail($email) {
	if (strlen($email) && $email{0} == "'" && $email{strlen($email)-1} == "'") $email = substr($email,1,-1);
	$e = explode("@",$email);
	if (count($e) == 1) return $email;
	$e[0] = preg_replace("/[^\.\w\d\!\#\$\%\&\'\*\+\-\/\=\?\^\_\`\{\|\}\~]+/","",$e[0]);
	$e[1] = preg_replace("/[^\w\d\.\-]+/","",$e[1]);
	return preg_replace("/[\.]+/",".",$e[0]."@".$e[1]);
}

function getPublicLicenseKey() {
	$public = sha1(rand(0,0x7FFFFFFF)."-".microtime(true));
	return $public."-".sha1($public."-".$GLOBALS["slConfig"]["license"]["licensedTo"]."-".$GLOBALS["slConfig"]["license"]["key"]);
}

function silent_json_encode($o) {
	$GLOBALS["slConfig"]["bypassError"] = true;
	$rv = json_encode($o);
	$GLOBALS["slConfig"]["bypassError"] = false;
	return $rv;
}

function format() {
	$args = func_get_args();

	$txt = preg_split("/(%[\d\w\-\_]*%)/",array_shift($args),NULL,PREG_SPLIT_DELIM_CAPTURE);
	
	$replace = null;
	if (count($args) && is_array($args[0])) {
		$replace = array_shift($args);
	}
	
	for ($i = 1; $i < count($txt); $i += 2) {
		$n = ($i - 1) >> 1;
		if ($txt[$i] != "%%") {
			$a = substr($txt[$i],1,strlen($txt[$i]) - 2);
			if ($replace && isset($replace[$a])) {
				$txt[$i] = $replace[$a];
				continue;
			} else {
				$n = (int)(preg_replace("/[^\d]+/gi","",$txt[$i])) - 1;
			}
		}
		$txt[$i] = isset($args[$n]) ? $args[$n] : "";
	}
	return implode("",$txt);
}

function decodeMulti($v,$onlySafe = false) {
	$v = trim($v) == "" ? array() : explode("\n",$v);
	foreach ($v as &$item) {
		if ($onlySafe) $item = array_shift(explode(";",$item));
		$item = str_replace("%OA","\n",$item);
	}
	return $v;
}

function encodeMulti($v) {
	foreach ($v as &$item) {
		$item = str_replace("\n","%OA",$item);
	}
	return implode("\n",$v);
}

function formatSlContent($content) {
	return str_replace("\n","<br>",htmlspecialchars($content));
}

function formatSlContentAsText($content) {
	return $content;
}

function imageCreateFromFile($file) {
	$ext = strtolower(array_pop(explode(".",$file)));
	switch ($ext) {
		case "jpg": case "jpeg":
			return imagecreatefromjpeg($file);
		
		case "png":
			return imagecreatefrompng($file);
		
		case "gif":
			return imagecreatefromgif($file);
	}
	return false;
}

function imageURL($img,$dir = false, $returnLocal = false) {
	if ($dir) {
		makePath($dir);
	} else {
		$dir = SL_WEB_PATH."/images";
	}
	
	$iData = explode(";",$img);
	if (count($iData) >= 7) {
		$localPath = SL_DATA_PATH."/users/".(isset($iData[7])&&$iData[7]?$iData[7]:"super")."/file/image/".$iData[3].".".array_pop(explode("/",$iData[1]));
		if (is_file($localPath)) {
			if ($returnLocal) return $localPath;
			$dest = $dir."/".$iData[0];
			copy($localPath,$dest);
			return webPath($dest);
		} else return false;		
	}
	return $img;
}

function truncate($str,$len) {
	$s = preg_split("/\s/",$str);
	$rv = array();
	$l = 0;
	
	for ($i = 0; $i < count($s); $i++) {
		$l += strlen($s[$i]);
		
		if ($l > $len && $l > 0) {
			$word = &$rv[count($rv) - 1];
			$word .= substr($word, -1) == '.' ? '..' : '...';
			
			break;
		}
		
		$rv[] = $s[$i];
		
		$l++;
	}
	return implode(" ", $rv);
}

function getItem($ref,$id,$field = false) {
	$find = (is_numeric($id) || preg_replace("/[\d]+/","",$id) === "") ? array("_KEY"=>$id) : array("_UNIQUE"=>$id);
	
	if ($res = $GLOBALS["slCore"]->db->select($ref, $find, array("limit"=>1))) {
		if ($field) {
			$row = $GLOBALS["slCore"]->db->fetch($ref, $res);
			return $row[$field];
		}
		return array(
			"item"=>$GLOBALS["slCore"]->db->fetch($ref, $res),
			"info"=>translate($GLOBALS["slCore"]->db->getTableInfo($ref))
		);
	}
	return false;
}

function setAndTrue($var,$p) {
	return isset($var[$p]) && $var[$p];
}

function scriptHash($script, $public = false) {
	if ($public === false) $public = sha1(rand(0,0x7FFFFFFF)."-".microtime(true));
	return $public."-".sha1($public."-".$GLOBALS["slConfig"]["package"]["key"]."-".$script);
}

function runScript($script, $params = array()) {
	$params["_SKEY"] = scriptHash($script);
	
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, WWW_BASE.$script."?".slRequestInfo::encodeGet($params));
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt($ch, CURLOPT_USERAGENT, "YibblePoint/Script");

	$return_data = curl_exec ($ch);
	curl_close($ch);
	unset($ch);
	return $return_data;
}

function htmlToText($html) {
	if (strpos($html,"<") === false) return $html;
	libxml_use_internal_errors(true);

	$dom = new DOMDocument;
	$dom->loadHTML($html);
	
	ob_start();
	domNodesToText($dom);
	return trim(htmlspecialchars_decode(ob_get_clean()));
}

function domNodesToText($dom, $tree = false, &$data = false) {
	$tree = $tree ? implode(",",$tree) : "";
	
	$olNum = 0;
	
	foreach ($dom->childNodes as $node) {
		$continue = false;
		switch ($node->nodeName) {
			case "title":
				$continue = true;
				break;
				
			case "thead"; case "tbody";
				$t = explode(",",$tree);
			 	$t[] = $node->nodeName;
				if ($node->hasChildNodes()) domNodesToText($node,$t,$data);
				break;
			
			case "h1": case "h2": case "h3": case "h4": case "h5": case "h6": case "h7":
				echo str_repeat("-", 7 - (int)preg_replace("/[^\d]/","",$node->nodeName))." ";				
				break;
					
			case "p": case "ol"; case "ul";
				echo "\n";				
				break;
				
			case "li":
				if (substr($tree,-2) == "ol") {
					$olNum++;
					echo "  ".$olNum.") ";
				} else {
					echo "  • "; 
				}
				break;
			
			case "table":
					$table = array();
					$t = explode(",",$tree);
					$t[] = $node->nodeName;
					if ($node->hasChildNodes()) domNodesToText($node,$t,$table);
					
					$rowHeight = array();
					for ($i = 0; $i < count($table); $i++) {
						$rowHeight[] = 0;
					}
					
					$colWidth = array();
					$cols = 0;
					foreach ($table as $row) {
						$cols = max($cols,count($row));
					}
					for ($i = 0; $i < $cols; $i++) {
						$colWidth[] = 0;
					}
					
					$maxRowHeight = 0;
					foreach ($table as $rowN=>$row) {
						foreach ($row as $colN=>$col) {
							$col = explode("\n",$col);
							$width = 0;
							foreach ($col as $t) {
								$width = max(strlen($t),$width);
							}
							$colWidth[$colN] = max($colWidth[$colN],$width);
							$rowHeight[$rowN] = max($rowHeight[$rowN],count($col));
							$maxRowHeight = max($maxRowHeight,count($col));
						}
					}
					
					$cells = array();
					foreach ($table as $rowN=>$row) {
						$rowText = array();
						foreach ($row as $colN=>$col) {
							$text = array();
							$col = explode("\n",$col);
							$cw = ($colWidth[$colN] + 4);
							foreach ($col as $t) {
								$text[] = $t.str_repeat(" ",$cw - strlen($t));
							}
							if ($maxRowHeight > 1) {
								for ($i = 0; $i < $rowHeight[$rowN] - count($col) + 1; $i++) {
									$text[] = str_repeat(" ",$cw);
								}
							}
							$rowText[] = $text;
						}
						for ($y = 0; $y < count($rowText[0]); $y++) {
							$c = array();
							for ($x = 0; $x < count($rowText); $x++) {
								echo isset($rowText[$x][$y]) ? $rowText[$x][$y] : "";
							}
							echo "\n";
						}
					}				
					echo "\n";
					$continue = true;
					break;
			
			case "tr";
				$cell = array();
				$t = explode(",",$tree);
				$t[] = $node->nodeName;
				if ($node->hasChildNodes()) domNodesToText($node,$t,$cell);
				$data[] = $cell;
				$continue = true;
				break;
				
			case "td"; case "th";
				ob_start();
				$t = explode(",",$tree);
				$t[] = $node->nodeName;
				if ($node->hasChildNodes()) domNodesToText($node,$t,$cell);
				$data[] = str_replace("\t","  ",ob_get_clean());
				$continue = true;
				break;
				
			case "#text":
				echo trim(preg_replace('/\s?\n+\s?/'," ",$node->nodeValue));
				break;
		}
		
		if ($continue) continue;
		
		$t = explode(",",$tree);
		$t[] = $node->nodeName;
		
		if ($node->hasChildNodes()) domNodesToText($node,$t);
		
		switch ($node->nodeName) {
			case "div": case "p": case "li"; case "ol"; case "ul"; case "br";
				echo "\n";
				break;
				
			case "h1": case "h2": case "h3": case "h4": case "h5": case "h6": case "h7":
				echo " ".str_repeat("-", 7 - (int)preg_replace("/[^\d]/","",$node->nodeName))."\n";				
				break;
			
			case "a":
				$href = $node->getAttribute("href");
				if (substr($href,0,7) != "mailto:" && $href != $node->nodeValue) echo ":\n".$href."\n";
				break;
		}
	}
}

function getObHash(&$o) {
	return $o == $GLOBALS ? "GLOBALS" : md5(print_r($o,true));
}

function cleanRecursion(&$obIn,&$obs = false,$depth = 0) {
	if ($obs === false) $obs = array(getObHash($obIn));
	$ob = array();
	foreach ($obIn as $n=>&$o) {
		if (is_array($o)) {
			$obHash = getObHash($o);
			if (in_array($obHash,$obs)) {
				$ob[$n] = "(RECURSION)";
			} else {
				$obs[] = $obHash;
				$ob[$n] = $depth > 0 ? "(MAX DEPTH)" : cleanRecursion($o, $obs, $depth + 1);
			}
		} elseif (is_object($o)) {
			$ob[$n] = (string)$o;
		} else {
			$ob[$n] = $o;
		}
	}
	return $ob;
}

function urgentAlert($txt, $urgency = 1) {
	$urgeCnt = is_file(SL_DATA_PATH.'/urgent-alerts-cnt') ? (int)file_get_contents(SL_DATA_PATH.'/urgent-alerts-cnt') : 0;
	$urgeCnt += $urgency;
	file_put_contents(SL_DATA_PATH.'/urgent-alerts', $txt."\n", FILE_APPEND);
	if ($urgeCnt >= 20) {
		/*mail(
			$GLOBALS["slConfig"]["communication"]["admin"],
			"URGENT! ".$GLOBALS["slConfig"]["package"]["name"]." issue(s)", 
			file_get_contents(SL_DATA_PATH.'/urgent-alerts')
		);*/
		unlink(SL_DATA_PATH.'/urgent-alerts');
		$urgeCnt = 0;
	}
	file_put_contents(SL_DATA_PATH.'/urgent-alerts-cnt', $urgeCnt);
}
