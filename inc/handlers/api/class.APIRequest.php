<?php

class APIRequest {
	private $apiKey;
	private $responseFormat = "json";
	private $requestData;
	private $URL;
	public $subUser = "none";
	
	public function __construct($URL, $key = false) {
		$this->URL = $URL;
		$this->apiKey = $key;
	}
	
	public function setResponseFormat($format) {
		switch ($format) {
			case "json": case "serialize": case "binary":
				$this->responseFormat = $format;
				break;
			
			default:
				throw new Exception("Invalid responseFormat '$format'");
				break;
		}
	}
	
	public function GET() {
		return call_user_func_array(array($this,'request'),array_merge(array("GET"),func_get_args()));
	}

	public function PUT() {
		return call_user_func_array(array($this,'request'),array_merge(array("PUT"),func_get_args()));
	}

	public function UPDATE() {
		return call_user_func_array(array($this,'request'),array_merge(array("PUT"),func_get_args()));
	}
	
	public function POST() {
		return call_user_func_array(array($this,'request'),array_merge(array("POST"),func_get_args()));
	}
	
	public function INSERT() {
		return call_user_func_array(array($this,'request'),array_merge(array("POST"),func_get_args()));
	}
	
	public function DELETE() {
		return call_user_func_array(array($this,'request'),array_merge(array("DELETE"),func_get_args()));
	}
	
	public function request() {
		$args = func_get_args();
				
		if (count($args)) {
			$method = array_shift($args);
			
			if (count($args)) {
				$hash = md5(json_encode($args));
				$dir = YP_CACHE_DIR."/".substr($hash,0,2);
				if (!is_dir($dir)) mkdir($dir);
				$cacheFile = $dir."/".substr($hash,2);
				
				//if (is_file($cacheFile) && filemtime($cacheFile) > time() - 300) return json_decode(file_get_contents($cacheFile),true);
				
				$component = array_shift($args);

				if ($method != "GET" && count($args) && is_array($args[count($args)-1])) {
					$this->requestData = array("main"=>array_pop($args),"_"=>array());
				} else $this->requestData = false;
				
				$url = (strpos($this->URL,"//") === false ? "http://" : "").$this->URL.$this->responseFormat."/".rawurlencode($component)."/".$this->parseArgs($args, $method == "GET");
				
				$ch = curl_init($url);
			
				curl_setopt($ch, CURLOPT_HTTPHEADER,array(
					'Accept: application/json',
					'Accept-Charset: utf-8',
					'X-Yp-Key: '.$this->apiKey,
					'X-Yp-Subuser: '.$this->subUser
				));
				
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
				
				if ($this->requestData) curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($this->requestData));
				
				$response = curl_exec($ch);		
				
				if (curl_errno($ch)) throw new Exception(curl_error($ch));
				if (!$response) throw new Exception("No response");
				
				$res = API::decode($response);
				file_put_contents($cacheFile,json_encode($res));
				return $res;
			} else throw new Exception("Component not specified");
		} else throw new Exception("Method not specified");
	}
	
	public function parseArgs($args, $argsInGet = false) {
		foreach ($args as &$arg) {
			if (is_string($arg) || is_bool($arg) || is_numeric($arg)) {
				$arg = rawurlencode((string)$arg);
			} else {
				if ($argsInGet) {
					$arg = API::encodeGet($arg);
				} else {
					if (!$this->requestData) $this->requestData = array("extra"=>array());
					$this->requestData["_"][] = $arg;
					$arg = "_".(count($this->requestData["_"]) - 1);
				}
			}
		}
		return count($args) ? implode("/",$args)."/" : "";
	}
}
