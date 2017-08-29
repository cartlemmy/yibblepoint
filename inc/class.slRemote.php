<?php

require_once(SL_INCLUDE_PATH."/class.U.php");

class slRemote extends slClass {
	public $noSlHeaders = false;
	private $encode = "raw";
	private $constantConv;
	public $licenseValid = false;
	public $curlInfo;
	public $debug = false;
	private $ch = null;
	private $curlOpts = array();
	private $curlOptsCummulative = array();
	
	function __construct() {
		$arr = get_defined_constants(true);
		$this->constantConv = $arr['curl'];
		
		if (function_exists("getallheaders")) {
			$headers = getallheaders();
			if (isset($headers["slRemote-Encoding"])) $this->encode = $headers["slRemote-Encoding"];
		}

		if (isset($_POST["licensedTo"]) && isset($_POST["key"])) {
			$licenseFile = "inc/license/to/".safeFile($_POST["licensedTo"],true);
			if (is_file($licenseFile)) {
				$license = json_decode(file_get_contents($licenseFile),true);
				$key = explode("-",$_POST["key"]);
				if ($key[1] == sha1($key[0]."-".$_POST["licensedTo"]."-".$license["key"])) {
					$this->licenseValid = true;
					$GLOBALS["_SESSION"]["LICENSE_SESSION"] = 1;
				} else {
					$GLOBALS["_SESSION"]["LICENSE_SESSION"] = 0;
					$this->error("Invalid key.");
				}
			} else {
				$GLOBALS["_SESSION"]["LICENSE_SESSION"] = 0;
				$this->error("License not found.");
			}
		} elseif (isset($GLOBALS["_SESSION"]["LICENSE_SESSION"]) && $GLOBALS["_SESSION"]["LICENSE_SESSION"]) {
			$this->licenseValid = true;
		}
	}
	
	public function __destruct() {
		$this->closeCurl();
	}
	
	public function closeCurl() {
		if ($this->ch) {
			curl_close($this->ch);
			$this->ch = null;
			$this->curlOpts = array();
			$this->curlOptsCummulative = array();
		}
	}
	
	function setOpt($n, $v) {
		$this->curlOpts[$n] = $v;
		$this->curlOptsCummulative[] = array($n,$v);
	}
	
	function request($o) {
		$md5 = md5(json_encode($o));
		if (isset($o["cacheFor"])) {
			$cacheFile = SL_DATA_PATH."/cache/".substr($md5,0,2);
			makePath($cacheFile);
			$cacheFile .= "/slrem-".$md5;
			$this->dbg('Checking for cached result ('.$cacheFile.')');
			if (is_file($cacheFile) && filemtime($cacheFile) > time() - $o["cacheFor"]) return json_decode(file_get_contents($cacheFile),true);
		}
		$this->ch = curl_init();
	
		setDefaults($o,array(
			CURLOPT_CONNECTTIMEOUT=>2,
			CURLOPT_TIMEOUT=>20,
			"followRedir"=>2,
			CURLOPT_USERAGENT=>"Mozilla/5.0 (YibblePoint/".$GLOBALS["slConfig"]["version"]." en-us)",
			CURLOPT_HTTPHEADER=>array("Accept-Language: en"),
			"encode"=>$this->encode
		));
						
		if (!$this->noSlHeaders && !in_array("slRemote-Encoding: ".$o["encode"],$o[CURLOPT_HTTPHEADER])) $o[CURLOPT_HTTPHEADER][] = "slRemote-Encoding: ".$o["encode"];
		
		foreach ($o as $n=>$v) {
			if (is_numeric($n)) {
				$this->setOpt($n, $v);
			} elseif (isset($this->constantConv[$n])) {
				$this->setOpt($this->constantConv[$n], $v);
			}
		}
		
		if (isset($o["validateLicense"])) {
			if (!isset($o["post"])) $o["post"] = array();
			$o["post"]["licensedTo"] = $GLOBALS["slConfig"]["license"]["licensedTo"];
			$o["post"]["key"] = getPublicLicenseKey();
		}
		
		if (isset($o["post"])) {
			if (setAndTrue($o,"postAsJSON")) {
				if (!isset($o[CURLOPT_CUSTOMREQUEST])) $this->setOpt( CURLOPT_POST, 1);
				$this->setOpt( CURLOPT_POSTFIELDS, json_encode($o["post"]));
			} elseif (is_array($o["post"])) {
				foreach ($o["post"] as $n=>&$v) {
					if (!is_string($v) && !is_numeric($v)) {
						$v = json_encode($v);
					}
				}
				if (!isset($o[CURLOPT_CUSTOMREQUEST])) $this->setOpt( CURLOPT_POST, 1);
				$this->setOpt( CURLOPT_POSTFIELDS, http_build_query($o["post"]));
			}
		}
				
		if (isset($GLOBALS["slSession"])) {
			$cookieFile = $GLOBALS["slSession"]->getUserDir(true)."/cookies";
		} else {
			$cookieFile = SL_DATA_PATH."/cache/cookies-".session_id();
		}

		//echo file_get_contents($cookieFile)."\n------\n";
		
		if (!is_file($cookieFile)) {
			$path = explode("/",$cookieFile);
			array_pop($path);
			makePath(implode("/",$path));
			touch($cookieFile);
		}
		$this->setOpt( CURLOPT_COOKIEFILE, $cookieFile);
		$this->setOpt( CURLOPT_COOKIEJAR, $cookieFile);
		
		$this->setOpt( CURLOPT_RETURNTRANSFER, 1);

		if (isset($o["followRedir"])) {
			@$this->setOpt( CURLOPT_FOLLOWLOCATION, 1);
			$this->setOpt( CURLOPT_MAXREDIRS, 1);
		}
		
		$this->dbg($this->curlOpts);

		if (!setAndTrue($o,"dummyRequest")) {
			
			foreach ($this->curlOpts as $n=>$v) {
				curl_setopt($this->ch, $n, $v);
			}
					
			$rv = curl_exec ($this->ch);
			
			if (curl_errno($this->ch)) {
				$this->dbg("CURL Error: ".curl_error($this->ch));
				if (setAndTrue($o,"returnCachedIfError")) {
					$cacheFile = SL_DATA_PATH."/cache/".substr($md5,0,2)."/slrem-".$md5;
					if (is_file($cacheFile)) return json_decode(file_get_contents($cacheFile),true);
				}
				return $this->error(curl_error($this->ch)."\nTried: ".$o[CURLOPT_URL],false,true);
			}
		}
		
		$this->curlInfo = curl_getinfo($this->ch);
		
		$this->dbg("CURL Info: ".json_encode($this->curlInfo,JSON_PRETTY_PRINT));
		
		if ($o["followRedir"] == 2) {
			if ($this->curlInfo) {
				if ($o[CURLOPT_URL] != $this->curlInfo["url"]) { 
					//TODO: Cache permanent redirects
					$this->closeCurl();
					
					$o["followRedir"] = 0;

					$o[CURLOPT_URL] = $this->curlInfo["url"];
					$rv = $this->request($o);
					if (isset($o["cacheFor"])) file_put_contents($cacheFile,json_encode($rv));
					return $rv;
				}
			}
		}
		
		$this->dbg("\n--------\nResponse Body: \n".$rv."\nEND Response Body\n--------\n");
		
		switch ($o["encode"]) {
			case "json":
				if ($rv === '') {
					$json = '';
				} else {
					$json = json_decode($rv,true);
					if (!$json) return $this->error("Could not decode JSON.\n\nResponse:\n'".$rv."'",false,true);
				}
				if (isset($o["cacheFor"])) file_put_contents($cacheFile,json_encode($json));
				$this->closeCurl();
				return $json;
		}
		
		$this->closeCurl();	
		
		if (isset($o["cacheFor"])) file_put_contents($cacheFile,json_encode($rv));
		
		return $rv;
	}

	
	function respondError($message) {
		$this->respond(array("error"=>1,"message"=>$message,"licenseValid"=>$this->licenseValid));
	}
	
	function respond($res) {
		if (isset($_POST["licensedTo"])) $res["licenseValid"] = $this->licenseValid;
		switch ($this->encode) {
			case "json":
				echo json_encode($res);
				return;
		}
	}
	
	public function optName($opt) {
		if (($n = array_search($opt, $this->constantConv)) !== false) return $n;
		return $opt;
	}
	
	private function dbg($text) {
		if (is_array($text)) {
			if (array_intersect(
				array_keys($text),
				array(CURLOPT_RETURNTRANSFER, CURLOPT_USERAGENT, CURLOPT_URL)
			)) {
				
				//CURL OPTS
				$table = array(array("Curl Opt", "Value"));
				foreach ($text as $opt=>$value) {
					$table[] = array($this->optName($opt), json_encode($value, JSON_PRETTY_PRINT));
				}
				
				$text = U::arrayToTextTable($table);
			}
		}
		if ($this->debug) echo $text."\n";
	}
	
}
