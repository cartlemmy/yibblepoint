<?php

class slRequestInfo extends slClass {	
	public static $tinyChars = "0123456789ABCDEFGHIJKLMNOPQRTSUVWXYZabcdefghijklmnopqrtsuvwxyz-_";
	
	function __construct($uri = false) {	
		$this->config = $GLOBALS["slConfig"];
		$docParent = $GLOBALS["slConfig"]["docParent"];
		$uriSpecified = false;
		
		if ($uri === false) {
			$uri = urldecode(substr($_SERVER["REQUEST_URI"],strlen($docParent)));
		} else $uriSpecified = true;
		
		$path = explode("#",$uri,2);
		$hash = count($path) == 2 ? $path[1] : "";
		$path = $path[0];
		
		$path = explode("?",$path,2);
		$params = count($path) == 2 ? $path[1] : "";
		$path = substr($path[0],1);
		
		$dev = false;
		
		if (strpos($path,"dev/") !== false) {
			$dev = true;
			$path = str_replace("dev/","",$path);
		}
		
		$fullPath = $this->config["root"]."/".$path;
		
		$get = explode("&", $params);
		
		$this->request = array(
			"root"=>$this->config["root"],
			"docParent"=>$docParent,
			"uri"=>$uri,
			"path"=>$path,
			"hash"=>$hash,
			"params"=>$this->decodeGet($params),
			"tiny"=>(count($get) == 1 && strpos($get[0],"=") === false && $get[0] != "") ? self::decodeTiny($get[0]) : false,
			"rawParams"=>$params,
			"fullPath"=>$fullPath,
			"isFile"=>is_file($fullPath),
			"isDir"=>is_dir($fullPath),
			"dev"=>$dev
		);
		if (!$uriSpecified) $GLOBALS["slRequestInfo"] = $this->request;	
	}
	
	function get() {
		return $this->request;
	}
	
	public static function decodeGet($get, $preserveKL = false) {
		$rv = array();
		$get = explode("&", $get);
		foreach ($get as $p) {
			if (strpos($p,"=") === false) {
				if ($p !== "") $rv[$p] = $preserveKL ? "!_KL_" : 1;
			} else {
				list($n,$v) = explode("=",$p,2);
				if ($v === "") {
					$rv[$n] = "";
				} elseif ($v{0} == "=") {
					if ($v == "=false") {
						$rv[$n] = false;
					} elseif ($v == "=true") {
						$rv[$n] = true;
					} else {
						$type = $v{1} ? $v{1} : "null";
						$o = ord($type);
						if ($o == 45 || ($o >= 48 && $o <= 57)) $type = "n"; 
						$decoded = urldecode(substr($v,$type == "n" ? 1 : 2));
						switch ($type) {
							case "null":
								$rv[$n] = NULL;
								break;
								
							case "n":
								$rv[$n] = json_decode($decoded);
								break;
								
							case "s":
								$rv[$n] = json_decode('"'.$decoded.'"');
								break;
								
							case "a":
								$rv[$n] = json_decode('['.urldecode(str_replace("+","%2C",substr($v,2))).']',true);
								break;
								
							case "o":
								$rv[$n] = json_decode('{'.$decoded.'}',true);
								break;
							
							case "b":
								$rv[$n] = base64ObjectDecode($decoded);
						}
					}
				} else {
					$rv[$n] = urldecode($v);
				}
			}
		}
		return $rv;
	}
	
	public static function encodeGet($o) {
		if (is_int($o)) return self::encodeTiny($o);

		if (is_array($o) || is_object($o)) {
			$rv = array();
			foreach ($o as $n=>$v) {
				if ($v == "!_KL_") {
					$rv[] = $n;
					continue;
				}
				switch (typeOf($v)) {
					case "null":
						$rv[] = $n."=="; break;
					
					case "string":
						$rv[] = $n."=".rawurlencode($v); break;
					
					case "number":
						$rv[] = $n."==".$v; break;
						
					case "bool":
						$rv[] = $n ? "=true" : "=false"; break;
					
					case "object":
						$rv[] = $n."==o".rawurlencode(substr(json_encode($v),1,-1)); break;
						
					case "array":
						$rv[] = $n."==a".str_replace(array("+","%2B"),array("%20","+"),urlencode(substr(str_replace(",","+",json_encode($v)),1,-1))); break;
				}
			}
			return implode("&",$rv);
		}
		return urlencode($o);
	}
	
	
	public static function encodeTiny($v) {
		$rv = "";
		while ($v > 0) {
			$rv = self::$tinyChars[$v % strlen(self::$tinyChars)].$rv;
			$v = floor($v / strlen(self::$tinyChars));
		}
		return $rv;
	}
	
	public static function decodeTiny($v) {		
		for ($i = 0; $i < 127; $i++) { $map[] = -1; }
		for ($i = 0, $len = strlen(self::$tinyChars); $i < $len; $i++) {
			$map[ord(self::$tinyChars{$i})] = $i;
		}
		
		$rv = 0;
		for ($i = 0, $len = strlen($v); $i < $len; $i++) {
			$rv *= strlen(self::$tinyChars);
			$rv += $map[ord($v{$i})];
		}
		return $rv;
	}
	
	function getLink($params = array(),$absolute = false) {
		$p = slRequestInfo::encodeGet(array_merge($this->request["params"],$params));
		return ($absolute?WWW_BASE:"").$this->request["path"].($p!=""?"?".$p:"");
	}
}
