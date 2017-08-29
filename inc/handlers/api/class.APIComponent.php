<?php

class APIComponent {
	protected $params = array();
	protected $keyInfo;
	
	private $log = "";
	
	public function __construct($params = false, $keyInfo = array()) {
		if ($params) $this->params = $params;
		$this->keyInfo = $keyInfo;
	}
		
	public function permCheck($permissions) {
		if ($GLOBALS["slSession"]->user->hasPermission($permissions)) return true;
		throw new Exception("You do not have $permissions permissions");
		return false;
	}
	
	public function getLog() {
		return $this->log;
	}
	
	public function GET($component) {
		
	}
	
	public function CACHE($func, $params, $cacheFor = 3600, $passCached = false) {
		//echo $func;
		$md5 = md5(json_encode(array($func,$params)));
		$dir = SL_DATA_PATH."/cache/".substr($md5,0,2);
		$file = $dir."/".$md5;
		
		if (is_dir($dir)) {
			if (is_file($file)) {
				if (filemtime($file) > time() - $cacheFor) {
					//echo " CACHED\n";
					return json_decode(file_get_contents($file),true);
				} elseif ($passCached) {
					//echo " PassCached ".json_encode($params);
					$params[] = json_decode(file_get_contents($file),true);
				}
			}
		}
		
		//echo "\n";
		if ($res = call_user_func_array(array($this,$func), $params)) {
			if (!is_dir($dir)) mkdir($dir);
			file_put_contents($file,json_encode($res));	
		}
		return $res;
	}
	
	public function fail($message) {
		return array("_FAIL"=>$message);
	}	
	
	public function debug($message) {
		$this->log .= $message."\n";
		
	}
}
