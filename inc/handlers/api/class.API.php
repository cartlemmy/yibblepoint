<?php

require_once(dirname(__FILE__)."/config.php");
require_once(dirname(__FILE__)."/class.APIRequest.php");

$je = array(
	'JSON_ERROR_NONE'=>"No error has occurred",
	'JSON_ERROR_DEPTH'=>"The maximum stack depth has been exceeded",
	'JSON_ERROR_STATE_MISMATCH'=>"Invalid or malformed JSON",
	'JSON_ERROR_CTRL_CHAR'=>"Control character error, possibly incorrectly encoded",
	'JSON_ERROR_SYNTAX'=>"Syntax error",
	'JSON_ERROR_UTF8'=>"Malformed UTF-8 characters, possibly incorrectly encoded",
	'JSON_ERROR_RECURSION'=>"One or more recursive references in the value to be encoded",
	'JSON_ERROR_INF_OR_NAN'=>"One or more NAN or INF values in the value to be encoded",
	'JSON_ERROR_UNSUPPORTED_TYPE'=>"A value of a type that cannot be encoded was given"
);
$GLOBALS["jsonErrors"] = array();
$num = 0;
foreach ($je as $n=>$txt) {
	if (!defined($n)) define($n, $num);
	eval('$GLOBALS["jsonErrors"]['.$n.'] = $txt;');
	$num++;
}

class API {
	protected $responseFAPIComponentormat = "json";
	protected $APIKey = "0";
	protected $permissionLevel = 0;
	
	protected $getParams = array();
	protected $requestData = array();
	protected $params = array();
	protected $error = false;
	private $rootAPIKey = "";
	public $keyInfo;

	public function __construct($params = false) {	
		if ($params !== false) {
			foreach ($params as $n=>$v) {
				if (isset($this->$n)) $this->$n = $v;
			}
			$this->setPermissionLevel();
		}
	}
	
	protected function hasPermission($level) {
		$p = $this->permissionLevel >= $level;
		if (!$p) $this->error = "Insufficient permission level";
		return $p;
	}
	
	protected function getError() {
		return array("error"=>$this->error);
	}
	
	private function setPermissionLevel($level = false) {
		if ($level === false) {
			if ($this->APIKey == "0") {
				$level = 0;
			} else {
				$level = $this->applyAPIKey();				
			}
		}
		$this->permissionLevel = $level;
	}
	
	public function getPrivateAPIKey($permissionLevel = 1) {
		$this->loadRootAPIKey();
		$rand = dechex(max(0,min(15,$permissionLevel))).substr(sha1(microtime(true)."-".rand(0,0x7FFFFFFF)."-mewbew"),1);
		return self::componentsToKey($rand,$this->rootAPIKey,time(),$info);
	}
	
	public static function generatePublicAPIKey($privateKey, $info = array()) {
		if (self::validatePrivateAPIKey($privateKey)) {
			
			if (!isset($info["name"])) $info["name"] = "anon";
			
			$rand = sha1(microtime(true)."-".rand(0,0x7FFFFFFF)."-hersheypb");
			$key = self::componentsToKey($rand,$privateKey,time(),20);
			
			$keyFile = dirname(__FILE__)."/.privateKeys";
			
			$id = is_file($keyFile) ? (filesize($keyFile) / (strlen($key)+1)) : 0;
			file_put_contents($keyFile,$key."\n",FILE_APPEND);
			file_put_contents(dirname(__FILE__).'/.keyInfo',$id.": ".json_encode($info)."\n",FILE_APPEND);
			return $id.".".$key;
		}
		return false;
	}
	
	public static function validatePrivateAPIKey($key) {
		$c = self::keyComponents($key);
		return $key == self::componentsToKey($c["rand"],self::getRootAPIKey(),$c["ts"]);
	}
	
	private static function keyComponents($key,$id = false) {
		$info = array();
		if ($id !== false && $fp = fopen(dirname(__FILE__).'/.keyInfo',"r")) {
			while (!feof($fp)) {
				$l = explode(":",fgets($fp),2);
				if ($l[0] == $id) {
					$info = json_decode(trim($l[1]),true);
					break;
				}
			}
			fclose($fp);
		}
		
		$key = bin2hex(self::b64dec($key));

		$len = (strlen($key)-8)/2;
		$ts = unpack("N",pack("H*",substr($key,$len*2)));
			
		$info["rand"] = substr($key,0,$len);
		if (!isset($info["permissionLevel"])) $info["permissionLevel"] = hexdec(substr($key,0,1));
		$info["hash"] = substr($key,$len,$len);
		$info["ts"] = $ts[1] * 60;
		
		return $info;
	}
	
	private static function componentsToKey($rand,$key,$ts,$len = 40) {
		$ts = floor($ts / 60);
		
		$rand = substr($rand,0,$len);
		
		$bin = pack("H*",$rand.substr(sha1($ts.".".$rand.".".$key."-rightonenone"),0,$len)).pack("N",$ts);
		return self::b64enc($bin);
	}
	
	private function applyAPIKey($key = false) {
		if ($key === false) $key = $this->APIKey;
		$this->loadRootAPIKey();
		
		list($i,$key) = explode(".",$key,2);
		
		$keyFile = dirname(__FILE__)."/.privateKeys";
		
		$len = strlen($key);

		if ($key == file_get_contents($keyFile, false, NULL, ($len+1) * $i, $len)) {
			$c = self::keyComponents($key,$i);
			$this->keyInfo = $c;
			
			return $c["permissionLevel"];
		}
		
		return -1;
	}
	
	private function loadRootAPIKey() {
		if (!$this->rootAPIKey) {
			$this->rootAPIKey = self::getRootAPIKey();
		}	
	}
	
	public static function getRootAPIKey() {
		$file = dirname(__FILE__)."/.apiKeyPrivate";
		if (is_file($file)) {
			if ($key = file_get_contents($file)) {
				return sha1($key."-whatisthisonebe").sha1($key."-wegonbirdin");
			} else throw new Exception("API error 2");
		} else throw new Exception("API error 1");
	}
	
	public static function handleError($code, $description, $file = null, $line = null, $context = null) {
		if (in_array($code,array(E_STRICT, E_NOTICE, E_WARNING))) return;
		//if ($code === E_ERROR) {
			echo self::fail(array(
				'code'=>array_flip(array_slice(get_defined_constants(true)['Core'], 1, 15, true))[$code],
				'description'=>$description,
				'file'=>$file,
				'line'=>$line,
				'context'=>$context
			));
			exit();
		//}
	}
	
   public static function process() {
	  $oldHandler = set_error_handler('API::handleError');
	  error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
	  ini_set("display_errors", "off");
	  
      $params = self::parseURI($_SERVER["REQUEST_URI"],true);
      
      if (!isset($params["responseFormat"])) throw new Exception("responseFormat not specified");
      if (!isset($params["APIKey"])) throw new Exception("APIKey not specified");  
      if (!isset($params["component"])) throw new Exception("component not specified");
      
      if (isset($params["error"])) throw new Exception($params["error"]);
       
      if ($params["getParams"]) $params["params"][] = $params["getParams"];
	    
		$api = new API($params);

		if ($api->permissionLevel >= 0) {
			$keyDir = safeFile($api->keyInfo["name"]);
			if ($params["APIUserDir"] && !is_dir($params["APIUserDir"]."/".$keyDir)) {
				$params["APIUserDir"] .= '/'.$keyDir;
				mkdir($params["APIUserDir"]);
				chmod($params["APIUserDir"],0775);
				$cfg = $params["APIUserDir"].'/config.php';
				file_put_contents($cfg, "<?php\nreturn ".var_export(array("name"=>$api->keyInfo["name"],"created"=>$api->keyInfo["ts"],"matchReferrers"=>false),true).';');
				chmod($cfg,0775);
			}
				
			$params["perms"] = $api->permissionLevel;
			$component = "API_".self::safeFile($params["component"]);
			$file = (defined('API_COMPONENT_PATH') ? API_COMPONENT_PATH : dirname(__FILE__)."/component")."/class.".$component.".php";
			
			if (is_file($file)) {
				require_once(dirname(__FILE__)."/class.APIComponent.php");
				
				require_once($file);
				if (class_exists($component)) {
					
					$interface = new $component($params,$api->keyInfo);
					
					if (!isset($interface->keyPerms) || $api->permissionLevel >= $interface->keyPerms) {
						if (isset($params["action"])) {
							
							$method = preg_replace('/[^\w\d]+/','',$params["action"]);
							if (method_exists($interface,$method)) {
								return call_user_func_array(array($interface,$method),$params["params"]);
							}
						}
						
						if (method_exists($interface,$params["method"])) {
							if (isset($params["action"])) array_unshift($params["params"],$params["action"]);
							
							$res = call_user_func_array(array($interface,$params["method"]),$params["params"]);
							if (method_exists($interface,"getLog") && ($log = $interface->getLog())) {
								set_error_handler($oldHandler);
								$GLOBALS["API_LOG"] = $log;
								return $res;
							} else {
								set_error_handler($oldHandler);
								return $res;
							}
						} elseif (method_exists($interface,'defaultAction')) {
							return $interface->defaultAction();
						} else throw new Exception("Component ".$params["component"]." has no default action");
					} else throw new Exception("Component ".$params["component"]." requires API Key level ".$interface->keyPerms." or higher.\n(Your key level is ".$api->permissionLevel.")");
				} else throw new Exception("Component class ".$component." not found");
			} else throw new Exception("Component handler '".$params["component"]."' not found");
		} else throw new Exception("Invalid API Key");
	}
   
	public static function logRequest($params, $response = false) {		
		$ts = time();
		$dir = dirname(__FILE__)."/log/".date("Y",$ts);
		if (!is_dir($dir)) mkdir($dir);
		
		$dir .= "/".date("m",$ts);
		if (!is_dir($dir)) mkdir($dir);
		
		$dir .= "/".date("d",$ts);
		if (!is_dir($dir)) mkdir($dir);
		
		$dir .= "/".safeFile($params["APIUser"]);
		if (!is_dir($dir)) mkdir($dir);
		 
		$logFile = $dir."/".safeFile($params["APIUser"]);
		
		unset($params["APIKey"]);
				
		$logData = array("params"=>$params);
		if ($response !== false) $logData["response"] = $response;
		
		file_put_contents($logFile,json_encode($logData)."\n",FILE_APPEND);
		
		$count = is_file($logFile.".cnt") ? json_decode(file_get_contents($logFile.".cnt"),true) : array("day"=>0);
		$count["day"]++;
		
		$n = "hour".date("H",$ts);
		if (!isset($count[$n])) $count[$n] = 0;
		$count[$n]++;
		
		$n = $params["method"]."-".$params["component"];
		if (!isset($count[$n])) $count[$n] = 0;
		$count[$n]++;
		
		file_put_contents($logFile.".cnt", json_encode($count));
		
		return true;
	}
	
	public static function decodeGet($get, $main = true) {
		if (strpos($get,'&') === false && strpos($get,',') === false && strpos($get,'=') === false) return self::dec($get);
		$rv = array();
		$res = array('',0,'');
		$pos = 0;
		while ($res = self::next($get,$pos)) {
			switch ($res[0]) {
				case '&':
				case ',':
					$o = explode('=',$res[2],2);
					if (count($o) == 1) {
						$rv[] = self::dec($o[0]);
					} else {
						$rv[self::dec($o[0])] =  self::dec($o[1]);
					}
					$pos = $res[1] + 1;
					break;
				
				case '[':
					$o = explode('=',$res[2],2);

					if (count($o) == 1) {
						$rv[] = self::dec($o[0]);
						$pos = $res[1] + 1;
					} else {
						$r = self::decodeGet(substr($get,$res[1]+1),false);
						$rv[self::dec($o[0])] = $r[0];
						$pos += $r[1] + strlen($res[2]) + 3;
					}					
					break;
					
				case ']':
					if ($res[2]) {
						$o = explode('=',$res[2],2);
						if (count($o) == 1) {
							$rv[] = self::dec($o[0]);
						} else {
							$rv[self::dec($o[0])] = self::dec($o[1]);
						}
					}
					return $main ? $rv : array($rv,$res[1]);
			}
		}
		return $rv;
	}

	public static function dec($p) {
		$p = rawurldecode($p);

		if (preg_match('/\w+[\w\d]+(\>|\>\=|\<|\<\=|\!\=)/',$p,$match)) {
			$p = explode($match[1], $p, 2);
			return array(self::dec($p[0])=>array($match[1],self::dec($p[1]))); 
		}
		
		switch (substr($p,0,2)) {
			case "_N": case "_U":
				return null;
			
			case "_E":
				return str_replace('_E','_',$p);
				
			case "_S":
				return str_replace('_E','_',substr($p,2));
			
			case "_T":
				return true;
				
			case "_F":
				return false;
		}
		
		return $p !== '' && preg_replace('/[^\d\.]+/','',$p) === $p && substr($p,0,1) != '0' ? (preg_replace('/[^\d]+/','',$p) === $p ? (int)$p : (float)$p) : $p;
	}
	
	public static function next($s,$pos) {
		if ($pos > strlen($s)) return array(']',strlen($s),substr($s,$pos));
		$c = array('&',',','[',']');
		$bestPos = 1000000;
		$best = false;
		foreach ($c as $m) {
			if (($p = strpos($s, $m, $pos)) !== false && $p < $bestPos) {
				$bestPos = $p;
				$best = $m;
			}
		}
		return $best ? array($best,$bestPos,substr($s,$pos,$bestPos-$pos)) : array(']',strlen($s),substr($s,$pos));
	}
	
	
	public static function encodeGet($o) {
		if (is_array($o) || is_object($o)) {
			$rv = array();
			if (self::typeOf($o) === "array") {
				foreach ($o as $v) {
					$rv[] = self::enc($v);
				}
			} else {
				foreach ($o as $n=>$v) {
					$rv[] = $n."=".self::enc($v);
				}
			}
				
			return implode("&",$rv);
		}
		return self::enc($o);
	}
	
	public static function enc($v) {
		switch (self::typeOf($v)) {
			case "null":
				return "_N";
			
			case "string":
				return ($v !== '' && preg_replace('/[^\d]+/','',$v) === $v ? '_S' : '').self::encS($v); break;
			
			case "number":
				return $v;
				
			case "bool":
				return $v ? "_T" : "_F";
			
			case "object": case "array":
				return "[".self::encodeGet($v)."]";
		}
		return "_U";
	}
	
	public static function encS($s) {
		$s = str_replace('_','_E',$s);
		return rawurlencode($s);
	}
	
	public static function typeOf($o) {
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
	
	public static function parseHeaders($headers) {
		$headers = explode("\n",$headers);
		$rv = array();
		foreach ($headers as $header) {
			$header = explode(":",$header,2);
			if (count($header) == 2) {
				$rv[trim($header[0])] = trim($header[1]);
			}
		}
		return $rv;
	}	
	
	public static function parseURI($uri, $parseInput = false) {
		$rv = array(
			"origin"=>$_SERVER['HTTP_ORIGIN'],
			"method"=>isset($_SERVER["REDIRECT_REDIRECT_REQUEST_METHOD"]) ? $_SERVER["REDIRECT_REDIRECT_REQUEST_METHOD"] : $_SERVER['REQUEST_METHOD']
		);
		
		$headers = apache_request_headers();

		$uri = explode("?",$uri,2);
		
		$rv["getParams"] = count($uri) == 2 ? self::decodeGet(array_pop($uri)) : array();
		$uri = explode("/",trim($uri[0],"/"));
		
		
		$b = explode("/",WWW_BASE);
				
		array_shift($b); array_shift($b); array_shift($b);
		while (count($b) > 0 && $b[count($b) - 1] === "") { array_pop($b); }
						
		while (count($b) && $b[0] == $uri[0]) {
			array_shift($b);
			array_shift($uri);
		}
			
		$apiUserDir = SL_INCLUDE_PATH.'/handlers/'.array_shift($uri).'/users';
		if (!is_dir($apiUserDir)) $apiUserDir = false;
				
		$rv["params"] = array();
		
		if (count($uri)) {
			$rv["responseFormat"] = array_shift($uri);

 			if (isset($headers["X-Yp-Subuser"])) $rv["APISubUser"] = $headers["X-Yp-Subuser"];

			if (count($uri) || isset($headers["X-Yp-Key"])) {
				
				if (isset($headers["X-Yp-Key"])) {
					$rv["APIKey"] = $headers["X-Yp-Key"];
				} else $rv["APIKey"] = array_shift($uri);
				
				
			
				if (isset($headers["X-Yp-User"])) {
					$rv["APIUser"] = $headers["X-Yp-User"];
				} else $rv["APIUser"] = "MAIN";
				
				$uid = @array_shift(explode(".", $rv["APIKey"]));
				$fp = fopen(dirname(__FILE__).'/.keyInfo',"r");
				while (!feof($fp)) {
					$line = explode(":",trim(fgets($fp)),2);
					if ((int)$line[0] === (int)$uid) {
						if ($ki = json_decode($line[1],true)) {					
							$rv = array_merge($rv,$ki);
							if (isset($rv["name"])) {
								$rv["APIUser"] = $rv["name"];
								unset($rv["name"]);
							}
						}
						break;
					}
				}
				fclose($fp);

				
				$rv["APIUserSafe"] = safeFile($rv["APIUser"]);
				$rv["APIUserDir"] = $apiUserDir;
				
				if (count($uri)) {
					$c = rawurldecode(array_shift($uri));
					if (in_array($c,array("GET","PUT","UPDATE","POST","INSERT","DELETE"))) {
						$rv["method"] = $c;
						if (!count($uri)) return $rv;
						$c = rawurldecode(array_shift($uri));
					}
					$rv["component"] = $c;
								
					/*if (count($uri)) {
						$rv["action"] = rawurldecode(array_shift($uri));
					}*/
					
				
					while (count($uri)) {
						$rv["params"][] = self::decodeGet(array_shift($uri));
					}
				}
			}
		}

		if ($parseInput) {
			if ($p = self::decode(self::getInputBuffer())) {				
				if (isset($p["_"])) {
					foreach ($p["_"] as $n=>$v) {
						if (($pos = array_search("_".$n,$rv["params"])) !== false) $rv["params"][$pos] = $v;
					}
					$rv["params"][] = $p["main"];
				} else {
					$rv["params"][] = $p;
				}				
			} elseif ($p === null && json_last_error() !== JSON_ERROR_NONE) {
				$rv["error"] = 'JSON Decode: '.(isset($GLOBALS["jsonErrors"][json_last_error()]) ? $GLOBALS["jsonErrors"][json_last_error()] : 'Unknown');
			}
		}
		return $rv;
	}
   
	public static function getInputBuffer() {
		if (isset($GLOBALS["_INPUT_BUFF"])) return $GLOBALS["_INPUT_BUFF"];
		return $GLOBALS["_INPUT_BUFF"] = file_get_contents("php://input");
	}
	
   public static function decode($string) {
	   $string = trim($string);
	   switch (1) {
			case (substr($string,0,2) == "a:"):
				return unserialize($string);
			
			case (substr($string,0,1) == "{"):
				return json_decode($string, true);
			
			case (substr($string,0,2) == "!B"):
				return substr($string,2);
		}
		return $string;
	}
	
	public static function fail($data) {
		self::respond($data,false);
	}
		
	public static function respond($res, $success = true) {
		if (is_array($res) && isset($res["_FAIL"])) {
			$success = false;
			$res = $res["_FAIL"];
		}
		
		$data = array("success"=>$success,"res"=>$res);
		if (isset($GLOBALS["API_LOG"])) $data["LOG"] = $GLOBALS["API_LOG"];
		$params = self::parseURI($_SERVER["REQUEST_URI"], true);
		
		if (defined('YP_API_LOG_RESPONSE') && YP_API_LOG_RESPONSE) API::logRequest($params, $data);

		switch (isset($params["responseFormat"]) ? $params["responseFormat"] : "json") {
			case "serialize": case "php":
				echo serialize($data);
				break;
				
			case "pretty-json":
				if (defined('JSON_PRETTY_PRINT')) {
					echo json_encode($data, JSON_PRETTY_PRINT);
					break;
				}
			
			case "csv":
				if ($data["success"]) {
					$name = isset($data["_TABLE"]["name"]) ? $data["_TABLE"]["name"] : date("Y-m-d")."-".(count($params["params"]) ? safeFile(implode("-AND-",$params["params"])) : "").$params["component"];
					
					$tmpFile = "/tmp/ppapi-".md5(json_encode($_SERVER));
					if ($fp = fopen($tmpFile,"w")) {
						if (isset($data["res"]["_TABLE"])) {
							if (isset($data["res"]["_TABLE"]["labels"])) fputcsv($fp, $data["res"]["_TABLE"]["labels"]);
							if (isset($data["res"]["_TABLE"]["data"])) {
								foreach ($data["res"]["_TABLE"]["data"] as $row) {
									fputcsv($fp, $row);
								}
								
							}
						} else {
							if (self::isAssoc($data["res"])) $data["res"] = array($data["res"]);
							$labels = self::extractLabels($data["res"]);
							fputcsv($fp, $labels);
							foreach ($data["res"] as $row) {
								$out = array();
								foreach ($labels as $n) {
									$out[] = isset($row[$n]) ? $row[$n] : "";
								}
								fputcsv($fp, $out);
							}
						}
						fclose($fp);
						
						header("Content-type: text/csv");
						header("Content-length: ".filesize($tmpFile));
						header("Content-Disposition: attachment; filename=".$name.".csv");
						header("Pragma: no-cache");
						header("Expires: 0");
						
						readfile($tmpFile);
						unlink($tmpFile);
						exit();
					}
				} else echo $res["res"];
				break;
				
			case "json":
				echo json_encode($data);
				break;
			
			case "bson":
				if (function_exists('bson_encode')) {
					echo bson_encode($data);
				} else throw new Exception("bson_encode not supported (Mongo not installed)");
				break;
			
			case "xml":
				echo self::array_to_xml($data);
				break;
				
			default:
				echo json_encode(array("success"=>false,"res"=>"responseFormat '".$params["responseFormat"]."' not recognized"), defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
				break;
		}
	}
	
	private static function isAssoc(array $arr) {
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
	
	private static function extractLabels($arr) {
		$rv = array();
		foreach ($arr as $row) {
			foreach ($row as $n=>$v) {
				if (!in_array($n, $rv)) $rv[] = $n;
			}
		}
		return $rv;
	}

	public static function array_to_xml( $data, &$xml_data = false ) {
		if (!$xml_data) {
			$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');
			$ret = true;
		} else $ret = false;
		
		foreach( $data as $key => $value ) {
			if( is_array($value) ) {
				if (is_numeric($key)) {
					$key = 'item'.$key;
				}
				$subnode = $xml_data->addChild($key);
				self::array_to_xml($value, $subnode);
			} else {
				$xml_data->addChild("$key",htmlspecialchars("$value"));
			}
		}
		if ($ret) return $xml_data->asXML();
	}
   
   public function defaultAction() {
		return array("error"=>"No default action");
	}
	
   public static function safeFile($file,$noLoss = false,$sep = "_") {
		if ($noLoss) return str_replace(array("%20","%"),array($sep,"-"),rawurlencode($file));
		return preg_replace('/[^\w\d\-\_\.]+/',$sep,self::charNormalize($file));
	}
	
	public static function charNormalize($string) {
		$a = array('À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','¥','Þ','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ð','ñ','ò','ó','ô','õ','ö','ø','ù','ú','û','ý','ý','þ','ÿ','Ŕ','ŕ');
		$b = str_split('AAAAAAACEEEEIIIIDNOOOOOOUUUUYYbSaaaaaaaceeeeiiiidnoooooouuuyybyRr'); 	
		return str_replace($a, $b, $string); 
	}
	
	public static function b64enc($v) {
		return strtr(base64_encode($v), '+/=', '-_.');
	}
	
	public static function b64dec($v) {
		return base64_decode(strtr($v, '-_.', '+/='));
	}
	
	public static function loggableObject($ob, $showToLevel = 2, $level = 1, $indent = "\t", $top = true) {
		$iStr = str_repeat($indent, $level);
		ob_start();
		if (is_array($ob) && isset($ob[0]) && $ob[0] === "!ARGS") {
			$args = $ob[2];
			$ob = $ob[1];
		}
		if (!$top) echo '('.gettype($ob).')';
		switch (gettype($ob)) {
			case "array":
				if (self::isAssoc($ob)) {
					if ($level >= $showToLevel) {
						echo "(associative ".count($ob).")";
					} else {
						foreach ($ob as $n=>$v) {
							echo $iStr.$n.": ".self::loggableObject($v, $showToLevel, $level + 1, $indent, false)."\n";
						}
					}
				} else {
					if ($level >= $showToLevel) {
						echo "(numeric ".count($ob).")";
					} else {
						foreach ($ob as $v) {
							echo $iStr."• ".self::loggableObject($v, $showToLevel, $level + 1, $indent, false)."\n";
						}
					}
				}
				break;
			
			case "object":
				if (is_a($ob, 'ReflectionFunctionAbstract')) {
					$classMeth = is_a($ob, 'ReflectionMethod');
					$p = array();
					if ($classMeth) {
						$check = array('Static','Public','Protected','Private','Abstract','Final');
						foreach ($check as $n2) {
							if (call_user_func(array($ob, 'is'.$n2))) {
								$p[] = strtolower($n2);
							}
						}
					}
				
					//if ($ob->hasReturnType()) $p[] = $ob->getReturnType();
					echo $iStr.implode(' ', $p).' '.($classMeth?$ob->class.'::':'').$ob->name.'(';
					if ($level >= $showToLevel) {
						echo "\n";
					} else {
						$out = array();
						foreach ($ob->getParameters() as $i=>$param) {
		
							
							$p = array();
							//if ($param->hasType()) $p[] = $param->getType();
							$p[] = ($param->isPassedByReference() ? '&' : '').'$'.$param->getName();
							
							if (isset($args[$i])) {
								$out[] = implode(' ', $p)." = ".self::loggableObject($args[$i], $showToLevel + 1, $level + 2, $indent, false);
							} else{
								$out[] = implode(' ', $p);
							}
						}
						echo "\n".$iStr.$indent.implode(",\n".$iStr.$indent, $out)."\n".$iStr.")";
					}
				} else {
					echo get_class($ob);;
				}
				
				break;
				
			case "resource":
				break;
			
			case "integer": case "float": case "string": case "boolean":
				echo json_encode($ob);
				break;
			echo "\n";
		}
		return ob_get_clean();
	}
}
