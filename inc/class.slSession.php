<?php

class slSession extends slClass {
	private $destructed = false;
	private $core;
	private $dir;
	public $user;
	private $userData;
	private $fileData;
	public $userStatus = null;
	private $APIDir;
	private $apiUserFileData = null;
	private $loggedInFile;
	
	function __construct($core = false) {
		if ($GLOBALS["slSetupMode"]) {
			$this->dir = SL_DATA_PATH."/initial-setup/session";
			$this->sessionFile = SL_DATA_PATH."/initial-setup/session.json";
			$_SESSION = is_file($this->sessionFile) ? json_decode(fileGetLock($this->sessionFile),true) : array();
		} elseif ($GLOBALS["slCronSession"]) {
			$this->dir = SL_DATA_PATH."/cron/session";
			$this->sessionFile = SL_DATA_PATH."/cron/session".($GLOBALS["indivCron"] ? "-".$GLOBALS["indivCron"] : "").".json";
			$_SESSION = is_file($this->sessionFile) ? json_decode(fileGetLock($this->sessionFile),true) : array();
		} else {
			if (!session_id()) {
				session_name(SL_SID);
				if (!session_start()) {
					echo "SESSION START FAILED";
					exit();
				}
			}
			$this->dir = SL_DATA_PATH."/sessions/".session_id();
		}
		
		if (is_dir($this->dir)) touch($this->dir);
		
		require_once(SL_INCLUDE_PATH."/class.fileData.php");
		
		if (is_dir($this->dir)) {
			$this->fileData = new fileData($this->dir);
		} else {
			$this->fileData = new lighweightData($this->dir);
		}
		
		if (!isset($_SESSION["init"])) $this->initialize();
		
		$this->user = new slUser($this,$_SESSION["userID"] ? $_SESSION["userID"] : false);
		
		if ($_SESSION["userID"]) $this->loggedInFile = SL_DATA_PATH."/users/".$this->getUserName()."/loggedin";
		
		if ($this->get("isAPI")) {
			require_once(SL_INCLUDE_PATH."/class.fileData.php");
			$this->APIDir = SL_DATA_PATH."/users/".$this->getParentUser();
			$this->apiUserFileData = new fileData($this->APIDir);
		}
	}
	
	function __destruct() {
		if ($this->destructed) return;
		$this->destructed = true;
		if ($this->user) $this->user->__destruct();
		if (isset($GLOBALS["slScriptLoad"]) && $GLOBALS["slScriptLoad"]) {
			$duration = (microtime(true) - $GLOBALS["slScriptStartTS"]) * $GLOBALS["slScriptLoad"];
			$_SESSION["scriptRunTime"][] = array($GLOBALS["slScriptStartTS"], $GLOBALS["slScriptStartTS"] + $duration);
		}
		if ($GLOBALS["slCronSession"] || $GLOBALS["slSetupMode"]) {
			filePutLock($this->sessionFile,json_encode($_SESSION));
		} else {
			session_write_close();
		}
	}
	
	function initialize() {
		$_SESSION["init"] = 1;
		$_SESSION["startTime"] = time();
		$_SESSION["loginTime"] = 0;
		$_SESSION["userID"] = 0;
		$_SESSION["userParentID"] = 0;
		$_SESSION["userStatusFp"] = "";

		$this->fileData->set("_SERVER",$_SERVER);
	}
	
	function set($n,$v) {
		$_SESSION[$n] = $v;
	}
	
	function get($n) {
		return isset($_SESSION[$n]) ? $_SESSION[$n] : NULL;
	}
	
	function getUserData($n) {
		return $this->isLoggedIn() ? $this->user->get($n) : $this->fileDataGet("user",$n);
	}
	
	function setUserData($n,$v) {
		if ($this->isLoggedIn()) {
			$this->user->set($n,$v);
		} else {
			$this->fileDataSet("user",$n,$v);
		}
	}
	
	function getParentUser() {
		if (!$this->isLoggedIn() && !$this->get("isAPI")) return "nobody";
		return isset($_SESSION["parentUser"]) ? $_SESSION["parentUser"] : "nobody";
	}
	
	function getUserStatus($user = false) {
		if ($user == "!self") $user = $this->getUserName();
		
		$states = array("chatAvailable","clockedIn");
		$fingerprintParams = array("chatAvailable","clockedIn","active");
		$fingerprint = array();
		$userStatusDir = $this->getUserParentDir()."/user-status";
		$this->userStatus = array();
		if (is_dir($userStatusDir) && $dp = opendir($userStatusDir)) {
			while ($file = readdir($dp)) {
				$path = $userStatusDir."/".$file;
				if ($file != "." && $file != ".." && is_file($path) && ($user === false || $user === $file)) {
					$data = json_decode(fileGetLock($path),true);
					if (!$data) $data = array();
					$data["activity"] = filemtime($path);
					
					if (isset($data["windowOpen"]) && $data["windowOpen"] === false) {
						$data["active"] = false;
					} else {
						$data["active"] = $data["activity"] > time() - ($GLOBALS["slConfig"]["net"]["pollFrequencyMax"] + 1);
					}
					
					foreach ($states as $n) {
						$data[$n] = !isset($data[$n]) || $data[$n];
					}
					
					if (!$data["active"]) $data["chatAvailable"] = false;
					
					if ($user === $file) {
						closedir($dp);
						return $data;
					}
					
					$this->userStatus[$file] = $data;
					
					$fp = 0;
					foreach ($fingerprintParams as $i=>$n) {
						if ($data[$n]) $fp += pow(2,$i);
					}
					$fingerprint[] = dechex($fp);
				}
			}
			closedir($dp);
		}
		
		if ($user) {
			return array(
				"active"=>false,
				"chatAvailable"=>false,
				"activity"=>time(),
				"windowOpen"=>false,
				"clockedIn"=>false
			);
		}
		
		$userStatusFp = implode(",",$fingerprint);
		if ($userStatusFp != $_SESSION["userStatusFp"]) {
			$GLOBALS["slCore"]->nonUserSpecificDispatch("user-status-change",$this->userStatus);
			/*$oldStatus = $this->getUserData("userStatus");
			foreach ($this->userStatus as $sn=>$status) {
				foreach ($fingerprintParams as $n) {
					if ($status[$n] != $oldStatus[$sn][$n]) {
						
					}
				}
			}
			$this->setUserData("userStatus",$this->userStatus);*/
		}
		$_SESSION["userStatusFp"] = $userStatusFp;
		return $this->userStatus;
	}

	function getUser() {
		return $this->user;
	}
		
	function getUserName() {
		return $this->isLoggedIn() ? $this->user->get("user") : "!".session_id();
	}
	
	function getUserFileData() {
		return $this->isLoggedIn() ? $this->user->getFileData() : $this->fileData;
	}
	
	function fileDataGet($table,$n,$def = null) {
		$fd = $this->isLoggedIn() ? $this->user->getFileData() : $this->fileData;
		$rv = $fd->get($table, $n);
		return $rv !== null ? $rv : $def;
	}
	
	function fileDataSet($table,$n,$v) {
		$fd = $this->isLoggedIn() ? $this->user->getFileData() : $this->fileData;
		return $fd->set($table,$n,$v);
	}
	
	function tableLastUpdated($table) {
		if (!$this->isLoggedIn()) return time();
		$fd = $this->getParentFileData();
		return $fd->get("table-mod",safeFile($table));
	}
	
	function tableUpdate($table,$dispatchEvent = false) {
		if (!$this->isLoggedIn()) return;
		
		if ($dispatchEvent) $GLOBALS["slCore"]->nonUserSpecificDispatch("refresh-".$table,"");
		
		$fd = $this->getParentFileData();
		return $fd->set("table-mod",safeFile($table),time());
	}
	
	function getParentFileData() {
		if ($this->get("isAPI")) return $this->apiUserFileData;
		if ($this->isLoggedIn()) return $this->user->getParentFileData();
		return $this->fileData;
	}
	
	function userFilePath($uid, $mime = false, $returnRelative = false) {
		if ($mime === false && is_file($uid)) {
			$mime = mime_content_type($uid);
		} elseif ($mime === false) $mime = "misc";
		
		$mime = explode("/",$mime);
		$ext = count($mime) > 1 ? array_pop($mime) : "";
		$mime = safeFile($mime[0]);
		
		if (count(explode(".",$uid)) == 1) $uid = $uid.".".$ext;
		
		$dir = ($GLOBALS["slCronSession"] ? $this->dir : $this->user->dir)."/file/".$mime;
		makePath($dir);
		
		return $returnRelative ? $mime."/".$uid : $dir."/".$uid;
	}
	
	function userFileURL($uid,$mime = "misc") {
		return WWW_BASE.CORE_NAME."/my-files/".$this->userFilePath($uid,$mime,true);
	}
	
	function getUserDir($makeDir = false) {
		if ($makeDir) makePath($this->dir);
		return $this->isLoggedIn() ? $this->user->dir : $this->dir;
	}
	
	function getUserParentDir() {
		return $this->isLoggedIn() || $this->get("isAPI") ? SL_DATA_PATH."/users/".$this->getParentUser() : $this->dir;
	}
	
	function createUser($data) {
		$this->user = new slUser($this);
		$this->user->create($data);
	}
	
	public function logIn($crit,$passwordLess = false) {		
		//Brute force check
		//if (!$this->core->bruteForceCheck($crit["name"]?$crit["name"]:$crit["email"])) return $this->fail("Too many log in attempts, try again later.");
		
		if ($this->isLoggedIn()) return $this->fail("logged-in");
		
		if (!$passwordLess && !$crit["password"]) return $this->fail("no-password");
		
		if (isset($crit["user"])) {
			$r = $GLOBALS["slCore"]->db->select($this->user->table,array(array('user'=>$this->user->unformatUserName($crit["user"]),'email'=>$crit["user"]),"_NO_USER"=>1));
		} elseif ($crit["email"]) {
			$r = $GLOBALS["slCore"]->db->select($this->user->table,array('email'=>$crit["email"],"_NO_USER"=>1));
		} else {
			$r = false;
		}
		if ($r) {
			$data = $r->fetch_assoc();
			
			$this->user->initializeById($data["id"]);

			if ($passwordLess || $this->user->hashPassword($crit["password"]) === $this->user->hashPassword($data["password"])) {		
				$_SESSION["loginTime"] = time();
				
				$this->setId($data["id"], isset($data["parentId"]) && $data["parentId"] ? $data["parentId"] : $data["id"], $data["user"]);
				
				$status = $this->getUserStatus("!self");
				if ($status["active"]) {
					if (setAndTrue($crit,"force-logout")) {
						$this->user->addActivity("force-logout",array());
						
						if ($crit["force-logout"] != "immediate") $this->resetUser();
						return true;						
					}
					
					$this->user->addActivity("logged-in-elsewhere",array("_SERVER"=>relevantServer()));
					$this->resetUser();
					return $this->fail("logged-in-elsewhere");
				} else {
					$this->user->setPermission("user");
					$this->user->setPermission("session");
					$this->user->setWindowOpen();
					$this->user->addActivity("logged-in",isset($crit["special"]) ? array("_SERVER"=>relevantServer(),"special"=>$crit["special"]) : array("_SERVER"=>relevantServer()));
					$this->user->loggedIn();
					
					$this->loggedInFile = SL_DATA_PATH."/users/".$this->getUserName();
					if (is_dir($this->loggedInFile)) {
						$this->loggedInFile .= "/loggedin";
						file_put_contents($this->loggedInFile, time()."\n".json_encode($_SERVER));
					} else $this->loggedInFile = false;
					return true;
				}
			} 
		}
		$crit["password"] = str_repeat("*",strlen($crit["password"]));
		if ($r) $this->user->addActivity("failed-login",array("_SERVER"=>relevantServer(),"crit"=>$crit));
		$this->resetUser();
		return $this->fail("incorrect");
	}
	
	function setId($id,$parentId, $user = false) {
		$this->set("userID",$id);
		$this->set("parentID",$parentId);
		if ($parentId != $id) {
			$pr = $GLOBALS["slCore"]->db->select($this->user->table,array('id'=>$parentId,"_NO_USER"=>1));
			if ($pr) {
				$pData = $pr->fetch_assoc();
				$this->set("parentUser",$pData["user"]);
			}
		} else {
			$this->set("parentUser",$user ? $user : $this->getUserName());
		}
	}
	
	function resetUser() {
		$_SESSION["loginTime"] = 0;
		$_SESSION["userID"] = 0;
		$_SESSION["userParentID"] = 0;
		$_SESSION["userStatusFp"] = "";
		$this->user = new slUser($this);
	}
	
	public function logOut() {
		if (!$this->isLoggedIn()) {
			return $this->fail("logged-out");
		}
		$this->user->setWindowClose();
		$this->user->addActivity("logged-out");
		
		if ($this->loggedInFile && is_file($this->loggedInFile)) unlink($this->loggedInFile);
		
		session_destroy();
		session_start();
		$this->initialize();
		return true;
	}
	
	function getValidationLevel() {
		$rv = $this->user->get("validation");
		return $rv ? explode(",",$rv) : array();
	}
	
	function hasValidationLevel($n) {
		$rv = $this->getValidationLevel();
		return in_array($n,$rv);
	}
	
	function setValidationLevel($n,$v) {
		$rv = $this->getValidationLevel();
		$pos = array_search($n,$rv);
		if ($pos !== false && !$v) {
			array_splice($rv,$pos,1);
		} elseif ($pos === false && !!$v) {
			$rv[] = $n;
		} else return false; //No change, so return
		
		$action = "validation-".($v?"set":"revoked")."-".$n;
		$this->user->addActivity($action);
		$GLOBALS["slCore"]->dispatch($action);
		$this->user->set("validation",implode(",",$rv));
		
		return true;
	}
	
	function isLoggedIn() {
		return !!$_SESSION["userID"];
	}
	
	function fail($message) {
		$this->failMessage = $message;
	}
	
	function getFailMessage() {
		return $this->failMessage;
	}
}

class lighweightData {
	private $dataFiles = array();
	private $dataTables = array();
	private $dir;
	private $hash;
	private $fd = null;
	
	public $tableKey = "id";
	
	function __construct($dir = false) {
		$this->dir = $dir;
		$this->hash = preg_replace('/[^A-Za-z0-9]+/','',base64_encode(sha1($dir,true)));
		if (!isset($_SESSION[$this->hash])) $_SESSION[$this->hash] = array();
	}
	
	public function __destruct() {
		if (strlen(json_encode($_SESSION[$this->hash])) > 10240) {
			$this->convertToFull();
		}
	}
	
	function convertToFull() {
		$this->fd = new fileData($this->dir);
		foreach ($_SESSION[$this->hash] as $table=>$o) {
			foreach ($o as $n=>$v) {
				$this->fd->set($table,$n,$v);
			}
		}
		unset($_SESSION[$this->hash]);
	}
	
	function connect($settings = null, $password = false) {
		return true;
	}
	
	function selectByKey($table, $key) {
		$this->convertToFull();
		return $this->fd->selectByKey($table, $key);
	}
	
	function select($table, $find = false, $options = false) {
		$this->convertToFull();
		return $this->fd->select($table, $find, $options);
	}
	
	function upsert($table,$data,$find) {
		$this->convertToFull();
		return $this->fd->upsert($table,$data,$find);
	}
	
	function update($table,$data,$find,$insert=false) {
		$this->convertToFull();
		return $this->fd->update($table,$data,$find,$insert);
	}
	
	function insert($table,$data) {
		$this->convertToFull();
		return $this->fd->insert($table,$data);
	}
	
	function find($table,$find,$returnData = false) {
		$this->convertToFull();
		return $this->fd->find($table,$find,$returnData);
	}
	
	//Core functions
	function get($table,$n = "*") {
		if ($n == "*") return isset($_SESSION[$this->hash][$table]) ? $_SESSION[$this->hash][$table] : null;
		return isset($_SESSION[$this->hash][$table][$n]) ? $_SESSION[$this->hash][$table][$n] : null;
	}
	
	function upset($table,$n,$v = false) {
		if ($oldData = $this->get($table,$n,"*")) {
			$v = $v === false ? $oldData : array_merge($oldData,$v);
		}
		$this->set($table,$n,$v);
	}
	
	function set($table,$n,$v = false) {
		if (is_array($n)) {
			foreach ($n as $n1=>$v1) {
				$this->set($table,$n1,$v1);
			}
			return true;
		}
		if (!isset($_SESSION[$this->hash][$table])) $_SESSION[$this->hash][$table] = array();
		$_SESSION[$this->hash][$table][$n] = $v;
	}
	
	function remove($table,$n) {
		if (is_array($n)) {
			foreach ($n as $n1) {
				$this->remove($table,$n1);
			}
			return true;
		}
		if (isset($_SESSION[$this->hash][$table][$n])) unset($_SESSION[$this->hash][$table][$n]);
	}
	
	function getAllData($table) {
		if (isset($_SESSION[$this->hash])) return $_SESSION[$this->hash];
		return null;
	}
	
	function getKeys($table) {
		echo "TODO";
		exit();
	}
	
	function drop($table = false) {
		if ($table) {
			if (isset($_SESSION[$this->hash][$table])) unset($_SESSION[$this->hash][$table]);
		} else {
			if (isset($_SESSION[$this->hash])) unset($_SESSION[$this->hash]);
		}
	}
	
	function error($txt) {
		$this->lastError = $txt;
		echo $txt;
		return false;
	}
}
