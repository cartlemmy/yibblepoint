<?php

class slCore {
	private $user = false;
	public $db;
	public $errorQueue = array();
	private $config;
	private $listenerFp = array();
	private $listeners = array();
	private $listenersUpdated = array();
	private $dispatchCount = array();
	private $refreshDB = array();
	
	public $debugQueue = array();
	
	private $measureTs = 0;
	
	function __construct($config = false) {
		if ($config) $this->config = $config;
		
		$dirs = array("data","data/log");
		foreach ($dirs as $dir) {
			if (!is_dir($dir)) {
				if (!mkdir($dir)) {
					die("Failed to create dir '$dir'.");
				}
			}
		}
		
		$this->logFile = "data/log/".date("Y-m-d").".txt";
	}
	
	function __destruct() {

		foreach ($this->listenersUpdated as $n=>$lu) {
			if (!setAndTrue($this->listenerFp,$n)) {
			
				$userDir = $this->getUserDir($n);
				$this->listenerFp[$n] = openAndLock($userDir."/listeners","c+");
			}
			$fp = $this->listenerFp[$n];
			
			if (isset($this->listeners[$n]) && isset($this->listenersUpdated[$n]) && $this->listenersUpdated[$n]) {
				ftruncate($fp,0);
				fseek($fp,0);					
				foreach ($this->listeners[$n] as $type=>$d1) {
					foreach ($d1 as $id=>$d2) {
						fwrite($fp,$type.",".$id.",".$d2["ts"].",".$d2["user"]."\n");
					}
				}
			}
			closeAndUnlock($fp);
		}
		
	}	
	
	function beginMeasure() {
		$this->measureTs = microtime(true);
	}
	
	function endMeasure($text) {
		$this->debugQueue[] = "$text ".((microtime(true) - $this->measureTs) * 1000)."MS";
	}
	
	function debug($text) {
		$this->debugQueue[] = $text;
	}
	
	public function sendToHook($path,$event,$oldData,$data) {
		$changed = array();
		foreach ($data as $n=>$v) {
			if (!isset($oldData[$n]) || $oldData[$n] != $data[$n]) {
				$changed[$n] = $v;
			}
		}
		
		$sent = false;
		
		if ($dp = opendir($path)) {
			while (($file = readdir($dp)) !== false) {
				if (array_pop(explode(".",$file)) == "php") {
					require($path."/".$file);
					$sent = true;
				}
			}
			closedir($dp);
		}
		
		return $sent;
	}
	
	public function catchHook($event,$data = null) {
		$eventPath = $hookPath = explode("/",$event);
		$eventFile = array_pop($eventPath).".json";
		$eventPath = implode("/",$eventPath)."/".substr(md5($eventFile),0,2);
		$snapShotPath = SL_DATA_PATH."/hook-snapshot/".$eventPath;
		
		$sent = false;
		if (is_file($snapShotPath."/".$eventFile)) {
			$od = json_decode(file_get_contents($snapShotPath."/".$eventFile),true);
			$oldData = $od[1];
		} else $oldData = array();
		
		do {
			$path = SL_LIB_PATH."/hook/".implode("/",$hookPath);	
			if (is_dir($path)) {
				if ($this->sendToHook($path,$event,$oldData,$data)) $sent = true;	
			} 
			if (is_dir($path."/ALL")) {
				if ($this->sendToHook($path."/ALL",$event,$oldData,$data)) $sent = true;
			}
			array_pop($hookPath);
		} while (count($hookPath));
		if ($sent) {
			makePath($snapShotPath);
			file_put_contents($snapShotPath."/".$eventFile,json_encode(array($event,$data)));
		}
	}
	
	// Events / messages
	function nonUserSpecificDispatch($event,$params = null,$parentId = false) {
		if ($parentId === false) $parentId = "self";
		$e = explode("/",$event);
		
		$this->catchHook($event,$params);
		
		if (!isset($this->dispatchCount[$e[0]])) $this->dispatchCount[$e[0]] = 0;
		$this->dispatchCount[$e[0]] ++;

		if ($this->dispatchCount[$e[0]] > 20) {
			if (preg_match('/^(insert|change)\-[\w\d]+$/',$e[0])) {
				if (!in_array($e[1], $this->refreshDB)) {
					$this->refreshDB[] = $e[1];
					$this->nonUserSpecificDispatch('refresh-db/'.$e[1], $params, $parentId);
				}
				return;
			}
		}
		
		$this->getListeners($parentId);
		
		if (isset($this->listeners[$parentId]) && is_array($this->listeners[$parentId])) {
			$dispatched = array();
			
			if (isset($this->listeners[$parentId][$event])) {
				foreach ($this->listeners[$parentId][$event] as $id=>$d) {
					$dispatched[] = $d["user"].":".$event;
					$this->dispatch($d["user"].":".$event, $params, false);
				}
			}
			foreach ($this->listeners[$parentId] as $e=>$listeners) {
				if (strpos($e,"*") !== false) {
					$match = "/".str_replace("\\*",".+",preg_quote($e,"/"))."/";
					if (preg_match($match,$event)) {
						foreach ($listeners as $id=>$d) {
							$n = $d["user"].":".$event;
							if (!in_array($n,$dispatched)) {
								$dispatched[] = $n;
								$this->dispatch($d["user"].":".$event, $params, false);
							}
						}
					}
				}
			}			
		}
	}
	
	function dispatch($event,$params = null,$bypassPermissions = false) {
		$event = explode(":",$event);		
		$recipient = count($event) > 1 ? array_shift($event) : $this->getUser();
		$event = $event[0];
		
		$special = array(
			"*"=>'1',"!chat-available"=>'$s["chatAvailable"]',
			"!clocked-in"=>'$s["clockedIn"]',"!active"=>'$s["active"]'
		);
		
		if (isset($special[$recipient])) {
			$match = $special[$recipient];
			foreach ($GLOBALS["slSession"]->userStatus as $user=>$s) {
				$use = false;
				eval('$use = '.$match.';');
				if ($use) {
					$this->dispatch($user.":".$event,$params,$bypassPermissions);
				}
			}
			return;
		}
		
		//TODO: permissions check
		
		filePutLock($this->userDir($recipient)."/event-queue", $event.",".time().",".json_encode($params)."\n",true);
	}
	
	function addListener($event) {
		if (strpos($event,",") !== false) {
			$events = explode(",",$event);
			
			foreach ($events as $e) {
				$this->addListener($e);
			}
			return "(".$event.")/".$this->getUser();
		}
		
		$this->getListeners();

		$id = $event."/".$this->getUser();
		
		if (!isset($this->listeners["self"][$event])) $this->listeners["self"][$event] = array();
		
		$this->listenersUpdated["self"] = true;
		
		$this->listeners["self"][$event][$id] = array(
			"ts"=>time(),
			"user"=>$this->getUser()
		);
		return $id;
	}
	
	function removeListener($id) {
		if (strpos($id,",") !== false) {

			$events = explode(",",getStringBetween("(", ")", $id));
			$user = $this->getUser();
			foreach ($events as $event) {
				$this->removeListener($event."/".$user);
			}
			return;
		}
		
		$this->getListeners();
		
		$event = explode("/",$id);
		array_pop($event);
		$event = implode("/",$event);

		if (isset($this->listeners[$event][$id])) {
			unset($this->listeners[$event][$id]);
			if (count($this->listeners[$event]) == 0) unset($this->listeners[$event]);
			$this->listenersUpdated["self"] = true;
		}
	}
	
	private function getUserDir($parentId = "self") {
		if ($parentId === "self") return $this->userDir($GLOBALS["slSession"]->getParentUser());
		
		return $this->userDir($this->userFromId($parentId));
	}
	
	function getListeners($parentId = "self") {
		if ($parentId === "self" && !$GLOBALS["slSession"]->isLoggedIn() && !$GLOBALS["slSession"]->get("isAPI")) return;
		if (!isset($this->listenerFp[$parentId])) {
			$userDir = $this->getUserDir($parentId);
			
			if (is_file($userDir."/listeners")) {
				if ($this->listenerFp[$parentId] = openAndLock($userDir."/listeners","c+")) {
					
					$this->listeners[$parentId] = array();
					$this->listenersUpdated[$parentId] = false;
					
					fseek($this->listenerFp[$parentId],0);
					
					$this->listeners = array();
					while (!feof($this->listenerFp[$parentId])) {
						$line = fgets($this->listenerFp[$parentId],1024*1024);
						$d = explode(",",trim($line));
						if (count($d) == 4) {
							if (time() < $d[2] + 43200) {
								if (!isset($this->listeners[$parentId][$d[0]])) $this->listeners[$parentId][$d[0]] = array();
								$this->listeners[$parentId][$d[0]][$d[1]] = array(
									"ts"=>(int)$d[2],
									"user"=>$d[3]
								);
							}
						}
					}
					return true;
				}
			}
			$this->listeners[$parentId] = false;
		}
	}
	
	function userParentDir($user) {
		return $this->userDir($user,true);
	}

	function userDir($user = false, $returnParent = false) {
		$user = $user === false ? $this->getUser() : $user;
		if (substr($user,0,1) == "!") { //It's a session
			return LGPHP_ROOT_DIR."/data/sessions/".strtolower(preg_replace("/[^\w\d\_]+/","",substr($user,1)));
		} else {
			$dir = LGPHP_ROOT_DIR."/data/users/".strtolower(preg_replace("/[^\w\d\_]+/","",$user));
			if (is_dir($dir)) {
				return $dir;
			} else { //Is it a child account?
				if (isset($GLOBALS["slCore"]->db)) {
					$res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],array('user'=>$user,"_NO_USER"=>1));
					if ($res) {
						$userData = $res->fetch_assoc();
						if ($userData["parentId"] && $userData["parentId"] != $userData["id"]) {
							$res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],array('id'=>$userData["parentId"],"_NO_USER"=>1));
							if ($res) {
								$parentData = $res->fetch_assoc();
								return $returnParent ? LGPHP_ROOT_DIR."/data/users/".$parentData["user"] : LGPHP_ROOT_DIR."/data/users/".$parentData["user"]."/child/".strtolower(preg_replace("/[^\w\d\_]+/","",$user));
							}
						}
					}
				}
			}
		}
		return false;
	}
	
	function userFromId($id) {
		$res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],array('id'=>$id,"_NO_USER"=>1));
		if ($res) {
			$data = $res->fetch_assoc();
			return $data["user"];
		}
		return false;			
	}
	
	function getUser() {
		if ($this->user === false) {
			$this->user = isset($GLOBALS["slSession"]) ? $GLOBALS["slSession"]->getUserName() : "nobody";
		}
		return $this->user;
	}
	
	function getUserInfo($user) {
		if (substr($user,0,1) == "!") {
			$dir = $this->userDir($user);
			if (is_dir($dir)) {
				$fd = new fileData($dir);
				$rv = array(
					"user"=>"guest user",
					"name"=>$fd->get("user","name"),
					"email"=>$fd->get("user","email"),
					"contactID"=>$fd->get("user","contactID"),
					"server"=>$fd->get("_SERVER")
				);
				return $rv;
			}
		} else {
			$res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],array('user'=>$user,"_NO_USER"=>1));
			if ($res) {
				$info = $GLOBALS["slCore"]->db->fetch($GLOBALS["slConfig"]["user"]["table"],$res);
				unset($info["password"]);
				unset($info["privateKey"]);
				return $info;
			}
		}
		return NULL;
	}
	
	function log($text,$extra = NULL) {
		file_put_contents($this->logFile,date("H:i:sT")."\t ".str_replace("\n","\n\t",$text).($extra?"\t\t".json_encode($extra):"")."\n",FILE_APPEND);
	}
	
	function appendError($text,$critical,$bt) {
		$this->errorQueue[] = array($text,$critical,$bt);
	}
	
	function setDB($db) {
		$this->db = $db;
	}
}
