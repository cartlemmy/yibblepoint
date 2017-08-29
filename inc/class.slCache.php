<?php

function getQuickCache($n,$origFileOrCacheTime = false,$useJson = true) {
	if ($origFileOrCacheTime === false) $origFileOrCacheTime = 3600;
	$dir = SL_DATA_PATH."/cache/".substr(md5($n),0,2);
	makePath($dir);
	$file = $dir."/".slCache::CACHENAME($n);
	
	if (is_file($file)) {	
		if (defined('SL_DEBUG_QUICK_CACHE')) echo '<!-- cache? '.$n."\n\t".$file." ".date('Y-m-d H:i:s', filemtime($file))." -->\n";
		if (is_numeric($origFileOrCacheTime)) {
			if ($origFileOrCacheTime > 300000000) {
				if (defined('SL_DEBUG_QUICK_CACHE')) echo '<!-- cache defined as timestamp: '.date('Y-m-d H:i:s', $origFileOrCacheTime).' -->'."\n";
				if (filemtime($file) < $origFileOrCacheTime) {
					if (defined('SL_DEBUG_QUICK_CACHE')) echo '<!-- '.date('Y-m-d H:i:s', filemtime($file)).' LESS THAN '.date('Y-m-d H:i:s', $origFileOrCacheTime).' -->'."\n";
					return null;
				}
			} else {
				if (defined('SL_DEBUG_QUICK_CACHE')) echo '<!-- cache defined as elapsed time: '.($origFileOrCacheTime / 3600).' days -->'."\n";
				if (filemtime($file) + $origFileOrCacheTime < time()) return null;
			}
		} elseif (is_file($origFileOrCacheTime)) {
			if (defined('SL_DEBUG_QUICK_CACHE')) echo '<!-- cache compared against file time: '.$origFileOrCacheTime.' -->'."\n";
			if (filemtime($file) < filemtime($origFileOrCacheTime)) return null;
		}
		if (defined('SL_DEBUG_QUICK_CACHE')) echo "<!-- CACHED: ".$file." -->\n";
		return $useJson ? ($useJson === "file" ? $file : json_decode(file_get_contents($file),true)) : file_get_contents($file);
	}
	return null;		
}

function setQuickCache($n,$v,$useJson = true) {
	$dir = SL_DATA_PATH."/cache/".substr(md5($n),0,2);
	makePath($dir);
	$file = $dir."/".slCache::CACHENAME($n);
	
	if (defined('SL_DEBUG_QUICK_CACHE')) echo "\n<!-- ".$file.": ".sizeof($v)." -->\n";
	$data = $useJson ? json_encode($v, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0) : $v;
	if ($data === false && $useJson === "force") {
		slCache::cleanForJSON($v);
		$data = json_encode($v, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
	}
	if (!$data && defined('SL_DEBUG_QUICK_CACHE')) {
		$dbgFile = SL_DATA_PATH.'/debug-quick-cache-'.slCache::CACHENAME($n);
		file_put_contents($dbgFile, print_r($v, true));
		echo '<!-- json_encode issue'.(function_exists('json_last_error_msg') ? ' ('.json_last_error_msg().')' : '').', data saved in '.$dbgFile.' -->'."\n";
	}
	if ($data === false) return false;
	file_put_contents($file, $data);
	
	return $file;
}

class slCache extends slClass {	
	private $dir = "";
	private $files = array();
	private $fileLastModified = 0;
	private $content = false;
	
	public $cacheFile = "";
	private $cacheFileLastModified = 0;
	
	public $header = array();
	
	public static function CACHENAME($n) {
		return strlen($n) > 20 ? md5($n) : safeFile($n);
	}
	
	public static function cleanForJSON(&$o, $ignoreObjects = true) {
		if (is_object($o)) {
			if ($ignoreObjects) return;
			//TODO
		} elseif (is_array($o)) {
			foreach ($o as $n=>&$v) {
				self::cleanForJSON($v);
			}
		} elseif (is_string($o)) {
			if (json_encode($o) === false) {
				$charset = mb_detect_encoding($o, 'ASCII,UTF-8,ISO-8859-1');
				if (!$charset) $charset = "unknown";
				$o = 'base64:'.$charset.':'.base64_encode($o);
			}
		}
	}

	function __construct($file, $isCacheFile = false) {
		$this->dir = SL_DATA_PATH."/cache";
		makePath($this->dir);
		
		if ($isCacheFile) {
			$this->loadCacheFile($file);
		} else {
			$this->setFile($file);
		}
	}
	
	function loadCacheFile($file) {
		$this->files = array($file);
		$this->cacheFile = $file;
		$this->cacheFileLastModified = filemtime($file);
		
		if (file_get_contents($file,false,NULL,0,15) == "!lg-cache-head:") {
			list($header,$content) = explode("\n",file_get_contents($file),2);
			$this->header = json_decode(trim(substr($header,15)),true);
		} else {
			$content = file_get_contents($file);
		}
		$this->content = substr($content,0,6) == "!JSON:" ? json_decode(substr($content,6),true) : $content;
	}
	
	function setFile($file) {
		if (!is_array($file)) $file = array($file);
		$this->files = $file;
		
		$this->fileLastModified = 0;

		foreach ($this->files as $file) {
			$this->fileLastModified = max($this->fileLastModified,is_file($file) ? (int)filemtime($file) : 0);
		}
		$file = implode("-",$this->files);
				
		$dir = $this->dir."/".substr(md5($file),0,2);
		makePath($dir);
		
		$cacheFile = $dir."/".safeFile(str_replace(SL_BASE_PATH,"",$file),true);
		$this->cacheFile = $cacheFile;
		$this->cacheFileLastModified = is_file($cacheFile) ? filemtime($cacheFile) : 0;
	}
	
	function isCached() {
		return $this->cacheFileLastModified >= $this->fileLastModified;
	}
	
	function isExpired($skipValidityCheck = false) {
		
		if (setAndTrue($this->header,"expires") && time() > $this->header["expires"]) return true;
		
		if (isset($this->header["cacheVar"]) && $this->header["cacheVarValues"] != ($cv = $this->getCacheVarValues())) {
			$cacheVarValuesRaw = $this->getCacheVarValues(true);
			foreach ($this->header["cacheVar"] as $var) {
				if ($var[1] && $cacheVarValuesRaw[$var[0]] != $this->header["cacheVarValuesRaw"][$var[0]]) {
					if (!!$cacheVarValuesRaw[$var[0]]) return true;
				}
			}
				
			if (is_file($file = $this->cacheFile."-".md5($cv))) {
				$this->loadCacheFile($file);
				return $this->isExpired(true);
			}
			return true;
		}
			
		if (isset($this->header["dependency"])) {
			foreach ($this->header["dependency"] as $file) {
				if ($file && is_file($file) && filemtime($file) > $this->cacheFileLastModified) return true;
			}
		}

		return false;
	}
	
	function getCacheVarValues($asArray = false) {
		$rv = array();
		if (isset($this->header["cacheVar"])) {
			foreach ($this->header["cacheVar"] as $var) {
				$var[0] = '$'.preg_replace('/[^A-Za-z\d]+/','_',charNormalize(str_replace('$','',$var[0])));
				eval('$v = isset('.$var[0].') ? '.$var[0].' : null;');
				if ($asArray) {
					$rv[$var[0]] = $v;
				} else {
					if (is_array($v)) $v = $v ? md5(json_encode($v)) : "";
					$rv[] = safeFile(str_replace('$_','',$var[0])."-".$v);
				}
			}
		}
		return $asArray ? $rv : implode("-",$rv);
	}
	
	function start() {
		ob_start();
	}
	
	function complete() {
		$this->set(ob_get_flush());
	}
	
	function setExpires($v) {
		$this->header["expires"] = $v;
	}
	
	function setDependencyFile($v) {
		if (!isset($this->header["dependency"])) $this->header["dependency"] = array();
		if (!in_array($v,$this->header["dependency"])) $this->header["dependency"][] = $v;
	}
	
	function addCacheVar($var,$revalidate = false) {
		if (!isset($this->header["cacheVar"])) $this->header["cacheVar"] = array();
		if (!in_array($var,$this->header["cacheVar"])) $this->header["cacheVar"][] = array($var,$revalidate);
	}
	
	function setGroup($group) {
		$this->header["group"] = $group;
		//TODO: implement cache groups
	}
	
	function set($v) {
		if (is_array($v)) $v = "!JSON:".json_encode($v);
		if (isset($this->header["cacheVarValues"])) unset($this->header["cacheVarValues"]);
		if (isset($this->header["cacheVarValuesRaw"])) unset($this->header["cacheVarValuesRaw"]);
		
		if ($vv = $this->getCacheVarValues(true)) {
			$this->header["cacheVarValues"] = $this->getCacheVarValues();
			$this->header["cacheVarValuesRaw"] = $vv;
			$saveDefaultCache = true;

			foreach ($vv as $n=>$v2) {
				if ($v2) {
					$saveDefaultCache = false;
					break;
				}
			}
		} else $saveDefaultCache = true;
		
		if ($saveDefaultCache) file_put_contents($this->cacheFile,($this->header ? "!lg-cache-head:".json_encode($this->header)."\n":"").$v);

		if (isset($this->header["cacheVarValues"]) && $this->header["cacheVarValues"]) file_put_contents($this->cacheFile."-".md5($this->header["cacheVarValues"]),($this->header ? "!lg-cache-head:".json_encode($this->header)."\n":"").$v);
	}
	
	function out($sendHeaders = false) {
		if ($sendHeaders) {
			$tsstring = gmdate('D, d M Y H:i:s ', $this->cacheFileLastModified) . 'GMT';
			$etag = md5($this->cacheFile."-".$this->cacheFileLastModified);

			$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
			$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;
			
			if ((($if_none_match && $if_none_match == $etag) || (!$if_none_match)) &&
				($if_modified_since && $if_modified_since == $tsstring))
			{
				header('HTTP/1.1 304 Not Modified');
				return;
			} else {
				header("Last-Modified: $tsstring");
				header("ETag: \"{$etag}\"");
				header("Cache-Control: ".($_SESSION["userID"]?"private":"public"));
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
			}
		}
		echo $this->get();
	}

	function get() {
		if (!$this->content) $this->loadCacheFile($this->cacheFile);
		return $this->content;
	}
	
	function isNewerThan($ts) {
		return $this->cacheFileLastModified > $ts;
	}
	
}
