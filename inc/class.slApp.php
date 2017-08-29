<?php

class slApp extends slTranslatorBase {
	private $init = false;
	public $safeRef;
	public $translator;
	public $uid = false;
	private $newUid = false;
	public $classOb = null;
	private $fileDataInstance;
	private $fileDataApp = null;
	public $dir;
	public $dataDir = false;
	private $params;
	private $success = 1;
	private $failReason = "";
	private $manifest;
	private $response = array();
	private $moduleLoad = false;
	private $classFile = false;
	private $lackingPermissions = array();
	private $sufficientPermissions = false;
	private $log = false;
	
	function __construct($params) {
		$this->sufficientPermissions = true;	
		
		$this->params = $params;
		
		$this->uid = isset($params["uid"]) ? $params["uid"] : $this->createUid();
		
		require_once(SL_INCLUDE_PATH."/class.fileData.php");
		
		$this->dataDir = $GLOBALS["slSession"]->getUserDir()."/tmp/app-data/".$this->uid;
		
		$this->fileDataInstance = new fileData($this->dataDir);
		
		if (!isset($this->params["ref"])) {
			if (!($this->params["ref"] = $this->get("ref"))) return $this->fail("ref not specified");
		}
		
		if (isset($params["args"])) {
			$this->set("args",$params["args"]);
			$this->args = $params["args"];
		} else {
			$this->args = $this->get("args");
		}
		
		$this->safeRef = str_replace("..","",preg_replace("/[^\w\d\-\_\.\/]+/","",$this->params["ref"]));
		
		$this->dir = SL_LIB_PATH."/app/".$this->safeRef;
		if (!is_dir($this->dir)) return $this->fail("App not found.");
		
		$this->classFile = $this->dir."/class.php";
		
		$manifestFile = $this->dir."/manifest.json";
		if (!is_file($manifestFile)) return $this->fail("App manifest file (manifest.json) not found.");
		
		$this->manifest = translate(json_decode(file_get_contents($manifestFile),true));
		if ($this->manifest === false) return $this->fail("App manifest file corrupt.");

		if (isset($this->manifest["permissions"])) {
			$this->checkPermissions($this->manifest["permissions"]);
		}
		if (!$this->sufficientPermissions) return;
		
		if (isset($this->manifest["usesPersistentAppData"]) && $this->manifest["usesPersistentAppData"]) {
			$dir = $GLOBALS["slSession"]->getUserDir()."/app-data/".$this->safeRef;
			$this->fileDataApp = new fileData($dir);
		}
		
		$this->initClass();
		
		if ($this->newUid) {
			$this->loadIcon();
			if (!$this->load("main")) $this->fail("Failed to load app's main module.");
		} elseif (isset($params["module"])) {
			$this->moduleLoad = true;
			if (!$this->load($params["module"])) $this->fail("Failed to load module '".$params["module"]."'.");
		}
	}
	
	function checkPermissions($permissions) {
		if (!is_array($permissions)) $permissions = explode(",",$permissions);
		$sufficientPermissions = true;
		foreach ($permissions as $permission) {
			if (!$GLOBALS["slSession"]->user->hasPermission($permission)) {
				$this->respond("needsPemission",array("perm"=>$permission));
				if (!in_array($permission,$this->lackingPermissions)) $this->lackingPermissions[] = $permission;
				$sufficientPermissions = $this->sufficientPermissions = false;
			}
		}
		return $sufficientPermissions;
	}
	
	function permissionError() {
		return array("success"=>0,"error"=>"You do not have sufficient permissions to use this app (Need ".implode(", ", $this->lackingPermissions).")");
	}
	
	function destructInstance() {
		$this->fileDataInstance->drop();
		if ($this->fileDataApp) $this->fileDataApp->drop();
	}
	
	// App class
	function classExists() {
		return $this->classFile && is_file($this->classFile);
	}
	
	function classCall($method,$params) {
		if (!$this->sufficientPermissions) return $this->permissionError();
			
		$this->initClass();
		if (method_exists($this->classOb,$method)) {
			$rv = call_user_func_array(array($this->classOb, $method), $params);
			return array("success"=>$rv !== false,"result"=>$rv);
		}
		return array("success"=>0,"error"=>"app class has no method '".$method."'");
	}
	
	function initClass() {
		if ($this->init) return;
		require_once(SL_INCLUDE_PATH."/class.slAppClass.php");
		
		ob_start();
		
		require_once($this->classFile);
		$c = file_get_contents($this->classFile);
		$className = trim(getStringBetween("\nclass", "extends", $c));
		if (!$className) $className = trim(getStringBetween("//class", "\n", $c));
		$this->classOb = new $className($this);
		$this->classOb->ref = $this->safeRef;
		$this->init = true;
		
		$this->log = ob_get_clean();
	}
	
	//Loader
	function load($module) {
		if (!$this->sufficientPermissions) return;
		
		$useImplements = isset($this->manifest["implements"]) && $module == "main";
		
		if ($module{0} == "/") {
			$safe = str_replace("..","",$module);
			$jsFile = SL_LIB_PATH."/app".$safe.".js";
			$layoutFile = SL_LIB_PATH."/app".$safe;
		} else {
			$dir = $useImplements ? SL_LIB_PATH."/app/".$this->manifest["implements"] : $this->dir;
			$safe = preg_replace("/[^\w\d\-\_\.]+/","",$module);
			$jsFile = $dir."/".$safe.".js";
			$layoutFile = $dir."/".$safe;
		}
		if (!is_file($jsFile)) return false;
		
		$r["js"] = ($useImplements ? "/".$this->manifest["implements"]."/" : "").$safe.".js".(isset($this->manifest["debug"]) && $this->manifest["debug"] ? "?debug=1" : "");
		
		
		if (is_file($layoutFile.".html")) {
			$this->respond("html",array("html"=>tagParse($this->languageParse(file_get_contents($layoutFile.".html")))));
		} elseif (is_file($layoutFile.".php")) {
			ob_start();
			require($layoutFile.".php");
			$this->respond("html",array("html"=>tagParse($this->languageParse(ob_get_clean()))));
		}
		
		$this->respond("js",$r);
		return true;
	}
	
	function loadIcon($dir = false) {
		$respond = false;
		if ($dir === false) {
			$respond = true;
			$dir = $this->dir;
		}
		
		$iconFile = $dir."/resources/icon";
		if (is_file($iconFile.".png")) {
			iconResize($iconFile);
			//if ($respond) $this->respond("icon",array("file"=>"app/".$this->safeRef."/icon"));
			if ($respond) $this->respond("icon",array("file"=>str_replace(SL_LIB_PATH."/app","app",realpath($iconFile))));
		} elseif (($iconFile = $dir."/icon") && is_file($iconFile.".png")) {
			iconResize($iconFile);
			//if ($respond) $this->respond("icon",array("file"=>"app/".$this->safeRef."/icon"));
			if ($respond) $this->respond("icon",array("file"=>str_replace(SL_LIB_PATH."/app","app",realpath($iconFile))));
		} else {
			$dir = realpath($dir."/..");
			if ($dir && $dir != "/" && $dir != SL_LIB_PATH) return slApp::loadIcon($dir);
			return false;
		}
		return webPath(str_replace(SL_LIB_PATH."/app","app",realpath($dir)),true);
	}
		
	function respond($type,$data) {
		$data["type"] = $type;
		$this->response[] = $data;
	}
	
	//Args
	function setArg($i,$v) {
		$this->args[$i] = $v;
		$this->set("args",$this->args);
	}
	
	//Data
	function set() {
		$args = func_get_args();
		$table = count($args) > 2 ? array_shift($args) : "data";
		return $this->fileDataInstance->set($table,$args[0],$args[1]);
	}
	
	function get() {
		$args = func_get_args();
		$table = count($args) > 1 ? array_shift($args) : "data";
		return $this->fileDataInstance->get($table,$args[0]);
	}
	
	function setPersistent() {
		$args = func_get_args();
		$table = count($args) > 2 ? array_shift($args) : "data";
		return $this->fileDataApp->set($table,$args[0],$args[1]);
	}
	
	function getPersistent() {
		$args = func_get_args();
		$table = count($args) > 1 ? array_shift($args) : "data";
		return $this->fileDataApp->get($table,$args[0]);
	}
	
	// Response	
	function fail($reason) {
		$this->failReason = $reason;
		$this->success = 0;
	}
	
	function getNetResponse() {
		if (!$this->success) return array("success"=>0,"error"=>$this->failReason,"r"=>$this->response);
		
		$rv = array("success"=>1,"r"=>$this->response);
		
		if (!$this->moduleLoad) $rv["manifest"] = $this->manifest;
		
		if ($this->newUid) {
			$rv["uid"] = $this->uid;
			$this->set("ref",$this->params["ref"]);
			$this->set("initialized",time());
		}		
		
		if ($this->log) {
			$rv["_LOG"] = $this->log;
			$this->log = "";
		}
		
		return $rv;
	}
	
	function createUid() {
		$this->newUid = true;
		return sha1(json_encode($_SERVER)."-".microtime(true)."-".rand(0,1000000));
	}
	
	function fileContents($file) {
		$file = $this->dir."/".$file;
		return is_file($file) ? file_get_contents($file) : false;
	}
}
