<?php


class slUser extends slClass {
	public static $tokenFrom =  '0123456789abcdefghijklmnopqrstuv';
	public static $tokenChars = '0123456789ABCDEFGHJKLMNPQRTUVWXY';
	public $dir;
	public $parentDir;
	public $userStatusDir;
	private $fileData;
	private $parentFileData;
	private $session;
	public $table;
	public $isChild = false;
	private $destructed = false;
	private $data = array();
	private $dataUpdated = array();
	private $init = false;
	
	//Password
	public $prefix = "!__ZTEyN__";
	public $encryptionMethod = '$2a$07$';
	public $salt = "";
	public $aSalt = "";
		
	function __construct($session = false, $id = false) {
		
		$this->salt = $GLOBALS["slSetupMode"] ? "SETUP" : $GLOBALS["slConfig"]["user"]["salt"];
		$this->session = $session;
		$this->noBcrypt = setAndTrue($GLOBALS["slConfig"]["security"],"noBcrypt");
		
		if (!$this->noBcrypt && !$GLOBALS["slCronSession"] && CRYPT_BLOWFISH != 1) {
			throw new Exception("bcrypt not supported in this installation. See http://php.net/crypt");
		}
    
		$this->table = $GLOBALS["slSetupMode"] ? "user" : $GLOBALS["slConfig"]["user"]["table"];
		if ($id || $GLOBALS["slSetupMode"]) $this->initializeById($id);
		
	}
	
	function __destruct() {
		if ($this->destructed) return;
		
		if (count($this->dataUpdated) && isset($this->data["id"]) && $this->data["id"]) {
			if (isset($this->dataUpdated["permissions"])) $this->dataUpdated["permissions"] = implode(",",$this->dataUpdated["permissions"]);
			
			$GLOBALS["slCore"]->db->update($this->table,$this->dataUpdated,array("id"=>$this->data["id"],"_NO_USER"=>1),array("noUpdateFunction"=>1));
		}

		$this->destructed = true;
	}
	
	function initializeById($id) {
		if ($GLOBALS["slCronSession"] || $GLOBALS["slSetupMode"]) {
			$this->data = array(
				"id"=>0,
				"parentId"=>0,
				"user"=>"cron",
				"formattedUser"=>$GLOBALS["slSetupMode"] ? "initial-setup" : "cron",
				"email"=>"",
				"name"=>$GLOBALS["slSetupMode"] ? "Initial Setup" : "Cron Manager",
				"password"=>"",
				"privateKey"=>"",
				"permissions"=>$GLOBALS["slSetupMode"] ?  array("user","session","initial-setup") : array("user","session","cron")
			);
				
		} else if ($res = $GLOBALS["slCore"]->db->select($this->table,array("id"=>$id,"_NO_USER"=>1))) {
			$this->data = $res->fetch(array("ignoreWriteOnly"=>1));
			$this->data["permissions"] = $this->data["permissions"] == "" ? array() : explode(",",$this->data["permissions"]);
		} else {
			return $this->error("User (#".$id.") not found");
		}
		$this->updateSalt();
		$this->initialize();
	}
	
	function initialize() {
		$this->isChild = $this->data["parentId"] != $this->data["id"] && $this->data["parentId"] != 0;
		
		$this->init = true;
		//if ($this->session) {
			//$this->parentDir = $this->session->getUserParentDir();
			$this->parentDir = $this->session ? $this->session->getUserParentDir() : $GLOBALS["slCore"]->userParentDir($this->data["user"]);
			 
			$this->dir = $this->isChild ? $this->parentDir."/child/".$this->data["user"] : LGPHP_ROOT_DIR."/data/users/".$this->data["user"];
			
			makePath($this->dir);
			
			$userStatusDir = $this->parentDir."/user-status/";
			makePath($userStatusDir);
			
			$this->activity = new slRecord($this->dir."/activity",array("day","type"));
					
			require_once(SL_INCLUDE_PATH."/class.fileData.php");
			$this->fileData = new fileData($this->dir);
		//}
	}
	
	function setWindowOpen() {
		if (!$this->init || !$this->parentDir) return;
		file_put_contents($this->parentDir."/user-status/".$this->data["user"],json_encode(array(
			"windowOpen"=>true,
			"ip"=>$_SERVER["REMOTE_ADDR"],
			"agent"=>$_SERVER["HTTP_USER_AGENT"],
			"clockedIn"=>false
		)));
	}
	
	function setWindowClose() {
		if (!$this->init || !$this->parentDir) return;
		file_put_contents($this->parentDir."/user-status/".$this->data["user"],json_encode(array(
			"windowOpen"=>false,
			"clockedIn"=>false
		)));
	}
	
	function addActivity($type,$extra = false) {
		if (!$this->activity) return;
		
		$ts = time();
		
		$data = array(
			"ts"=>$ts,
			"day"=>floor($ts / 86400),
			"type"=>$type,
			"extra"=>$extra
		);
		
		$GLOBALS["slCore"]->dispatch($this->data["user"].":user-activity",$data);
		
		$this->activity->append($data);
	}
	
	function getFileData() {
		return $this->fileData;
	}
	
	function getParentFileData() {
		if (!$this->isChild) return $this->fileData;
		if (!$this->parentFileData) $this->parentFileData = new fileData($this->parentDir);
		return $this->parentFileData;
	}
	
	function getParentId() {
		return isset($this->data["parentId"]) && $this->data["parentId"] ? $this->data["parentId"] : $this->data["id"];
	}
	
	//Log In / Validation
	public function userNameExists($name) {
		return !!$GLOBALS["slCore"]->db->select($this->table,array('user'=>$this->unformatUserName($name),"_NO_USER"=>1));
	}
	
	public function emailExists($email) {
		return !!$GLOBALS["slCore"]->db->select($this->table,array('email'=>$email,"_NO_USER"=>1));
	}
	
	public function create($data) {
		if (isset($data["_NO_CONF_EMAIL"])) {
			$sendConfEmail = false;
			unset($data["_NO_CONF_EMAIL"]);
		} else $sendConfEmail = true;
		
		if (!isset($data["email"]) || trim($data["email"]) == "") $this->error("No E-mail specified");
		if ($this->emailExists($data["email"])) $this->error("E-mail already in use");
		
		//if (!$this->core->form->validate("email",$data["email"])) return $this->error("Invalid E-mail\n".$this->core->form->getLastError());
		
		if (!isset($data["user"]) || trim($data["user"]) == "") $this->error("No user name specified");
		if ($this->unformatUserName($data["user"]) && $this->userNameExists($data["user"])) $this->error("User name exists");
		
		if (!isset($data["password"])) $this->error("No Password specified");
		
		
		if ($this->hasErrors()) return false;
		
		$this->data = array(
			"formattedUser"=>$data["user"],
			"user"=>$this->unformatUserName($data["user"]),
			"email"=>$data["email"],
			"privateKey"=>self::generateToken(),
			"permissions"=>array()
		);
		
		if (isset($data["name"])) $this->data["name"] = $data["name"];
		
		$this->setPermission("user");
		$this->setPermission("session");
		
		if (isset($data["permissions"])) {
			$perms = explode(",",$data["permissions"]);
			foreach ($perms as $perm) {
				if (trim($perm)) $this->setPermission($perm);
			}
			unset($data["permissions"]);
		}
		
		if (isset($data["parentId"])) $this->data["parentId"] = $data["parentId"];
		
		foreach ($data as $n=>$v) {
			if (!isset($this->data[$n])) $this->data[$n] = $data[$n];
		}
		
		$this->data["id"] = $GLOBALS["slCore"]->db->insert($this->table,$this->data,false,array("direct"=>1));
		
		if (!isset($data["parentId"])) {
			$this->set("parentId",$this->data["id"]);
		}
		
		$this->isChild = $this->data["parentId"] != $this->data["id"];
		
		if ($this->session) {
			$this->session->setId($this->data["id"], $this->data["parentId"], $this->data["user"]);
			$_SESSION["loginTime"] = time();
		}
		
		$this->updateSalt();		
		
		$this->initialize();

		$this->addActivity("created");

		$this->setPassword($data["password"],true);
		
		$this->setWindowOpen();
		if ($sendConfEmail) $this->sendEmailValidation();
		
		$this->loggedIn();
		return true;
	}
	
	function loggedIn() {
		//Check for email account
		if (
			!$GLOBALS["slCore"]->db->select("db/mailAccounts",array('childUserId'=>$this->data["id"])) &&
			isset($GLOBALS["slConfig"]["communication"]["emailDomains"]) &&
			count($GLOBALS["slConfig"]["communication"]["emailDomains"])
		) {
			require_once(SL_INCLUDE_PATH."/class.emailAccount.php");
			
			$account = new emailAccount();
			
			$setup = isset($GLOBALS["slConfig"]["communication"]["emailDomains"]) ? $GLOBALS["slConfig"]["communication"]["emailDomains"][0] : array();
			
			$setup["user"] = $this->get("user");
			$setup["childUserId"] = $this->data["id"];
			$setup["key"] = $this->get("privateKey");
			
			$account->create($setup);
		}
	}
	
	//Data
	function set($n,$v) {
		$this->data[$n] = $v;
		$this->dataUpdated[$n] = $v;
	}
	
	function get($n,$def = false) {
		return isset($this->data[$n]) ? $this->data[$n] : $def;
	}
	
	function getName() {
		return isset($this->data["name"]) ? $this->data["name"] : $this->data["user"];
	}
	
	//Permissions
	function setPermission($permission) {
		if ($this->hasPermission($permission)) return;
		$this->data["permissions"][] = $permission;
		$this->set("permissions",$this->data["permissions"]);
	}

	function hasPermission($permissions) {
		if ($GLOBALS["slBypassPerimssions"]) return true;
		if (!isset($this->data["permissions"])) return false;
		if (!is_array($permissions)) $permissions = explode(",",$permissions);
		foreach ($permissions as $permission) {
			if (strpos($permission," OR ") !== false) {
				$or = explode(" OR ",$permission);
				$pass = false;
				foreach ($or as $orPerm) {
					if (in_array($orPerm,$this->data["permissions"])) return true;
				}
				return false;
			} else if (!in_array($permission,$this->data["permissions"])) return false;
		}
		return true;
	}
	
	// User name
	function setFormattedName($formatted) {
		if (!$formatted) return;
		$unformatted = strtolower($this->getData("name"));
		$j = 0; $f = "";
		for ($i = 0; $i < strlen($formatted); $i++) {
			$c = $formatted{$i};
			if ($c == "[") {
				$b = strpos(substr($formatted,$i+1),"[");
				if (($p = strpos(substr($formatted,$i),"[")) === false || ($b !== false && $b < $p)) {
					$f .= "[]";
				} else {
					$f .= substr($formatted,$i,$p+1);
					$i += $p;
				}				
			} else if (strpos("~!@#$%^&*()_+`-=:;{}<>,.?/\\|'\"",$c) !== false) {
				if ($c == $unformatted{$j}) {
					$j++; $f .= $c;
				}
			}  else {
				$f .= $c;
			}
		}
		if ($j < strlen($unformatted)) {
			$f .= substr($unformatted,$j);
		}
		$this->setData("formattedName",$f);
	}

	function unformatUserName($name) {
		return strtolower(preg_replace("/[^\w\d\_\.]+/","",$name));
	}
	
	//Password / Security
	function setPassword($password, $noEmail = false) {
		$password = $this->cleanPassword($password);
		require_once(SL_INCLUDE_PATH."/class.slCommunicator.php");
		require_once(SL_INCLUDE_PATH."/class.secuToken.php");

		$token = new secuToken(array("expires"=>"+1 day","page"=>"password"));
		
		$token->create(array(
			"type"=>"password-change",
			"user"=>$this->get("user"),
			"old"=>$this->get("password")
		));
			
		if (!$noEmail) {
			$com = new slCommunicator();
		
			$com->addRecipient("email/".$this->get("email"),$this->getName());
			$com->setSubject(translate("en-us|Password Changed"));
			$com->setMessage(translate("en-us|Your [package/name] password has been changed. If it was not you who changed it, or you did not want your password changed, then click the below link.")."\n\n".$token->getUrl());

			$com->send();
		}
				
		$this->set("password",$this->hashPassword($password));
		$this->addActivity("password-changed",array("_SERVER"=>relevantServer()));
		
		return true;
	}
	
	function cleanPassword($password) {
		return preg_replace("/\s+/","",$password);
	}
	
	function restorePassword($hashedPassword) {
		$this->set("password",$hashedPassword);
		$this->addActivity("password-restore");
	}
	
	function updateSalt() {
		$this->aSalt = $this->data["id"]."-".$this->data["user"]."-".$this->data["privateKey"];
	}

	function passwordMatch($pw) {
		return $this->match($pw,$this->data["password"]);
	}
	
	function match($pw1,$pw2) {
		return $this->hashPassword($this->cleanPassword($pw1)) === $this->hashPassword($this->cleanPassword($pw2));
	}
	
	function hashPassword($password) {
		if (substr($password,0,strlen($this->prefix)) == $this->prefix) return $password; //Already hashed
		
		if ($this->noBcrypt) return $this->prefix."-nbc-".preg_replace("/[^\w\d]+/","",base64_encode(sha1($password.$this->salt."-".$this->aSalt,true).sha1("meow!".$password.$this->salt."-".$this->aSalt,true)));
		
		return $this->prefix.substr(crypt($password, $this->encryptionMethod.substr(preg_replace("/[^\w\d]+/","",base64_encode(sha1($this->salt."-".$this->aSalt,true).sha1("meow!".$this->salt."-".$this->aSalt,true))),0,22).'$'),strlen($this->encryptionMethod));
	}
	
	function isHashed($password) {
		return substr($password,0,strlen($this->prefix)) == $this->prefix;
	}
	
	function generateSalt($len = 64) {
		$hash = "";
		do {
			$hash .= sha1(microtime(true).".".rand(0,0xFFFFFFFF));
		} while (strlen($hash) < $len);
		return substr(base64_encode($hash),0,$len);
	}
		
	public static function generateToken($length = 32, $enterable = false) {
		$rv = "";
		if ($enterable) {
			$t = bin2hex(pack("N",$id).sha1("SLT.".$enterable.".".microtime(true).".".rand(0,0x7FFFFFFF),true));
			$rv = "";
			for ($i = 0; $i < strlen($t); $i += 10) {
				$rv .= strtr(base_convert(substr($t,$i,10),16,32),self::$tokenFrom,self::$tokenChars);
			}
			return substr($rv,0,$length);
		}
		
		for ($i = 0; $i < $length; $i ++) {
			$rv .= chr(rand(0,255));
		}		
		return bin2hex($rv);
	}
	
	function sendEmailValidation() {
		require_once(SL_INCLUDE_PATH."/class.slCommunicator.php");
		require_once(SL_INCLUDE_PATH."/class.secuToken.php");
	
		$token = new secuToken(array("expires"=>"+1 week","page"=>"confirm"));
		
		$token->create(array(
			"type"=>"email",
			"user"=>$this->get("user")
		));
				
		$com = new slCommunicator();
		
		$com->addRecipient("email/".$this->get("email"),$this->getName());
		$com->setSubject(translate("en-us|Confirm Your E-mail Address"));
		$com->setMessage(translate("en-us|Welcome to [package/name]! Please follow the below link to confirm your E-mail address.")."\n\n".$token->getUrl());
		
		return $com->send();
	}
}
