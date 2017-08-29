<?php

class slNet extends slClass {
	private $requestInfo;
	private $uniqueID;
	private $response = array();
	private $connected = false;
	private $apps = array();
	
	private static $jsonErrors = array(
		JSON_ERROR_NONE=>"No error has occurred",
		JSON_ERROR_DEPTH=>"The maximum stack depth has been exceeded",
		JSON_ERROR_STATE_MISMATCH=>"Invalid or malformed JSON",
		JSON_ERROR_CTRL_CHAR=>"Control character error, possibly incorrectly encoded",
		JSON_ERROR_SYNTAX=>"Syntax error",
		JSON_ERROR_UTF8=>"Malformed UTF-8 characters, possibly incorrectly encoded",
		JSON_ERROR_RECURSION=>"One or more recursive references in the value to be encoded",
		JSON_ERROR_INF_OR_NAN=>"One or more NAN or INF values in the value to be encoded",
		JSON_ERROR_UNSUPPORTED_TYPE=>"A value of a type that cannot be encoded was given"
	);
			
	function __construct($requestInfo) {		
		$this->requestInfo = $requestInfo;
		$data = explode("\n",rawurldecode(file_get_contents("php://input")),2);
		if (count($data) != 2) return;
		list($uniqueID,$origRequest) = $data;
		
		$GLOBALS["_SEND_ERRORS_VIA_NET"] = true;
		
		if ($origRequest) {			
			$request = json_decode($origRequest,true);
			
			if ($uniqueID == "new") {
				$uniqueID = $this->newUID();
			}
			
			$this->uniqueID = $uniqueID;
			$this->connected = true;
			
			if (is_array($request)) {
				foreach ($request as $item) {
					$response = array();
					if (isset($item["hook"])) $response["hook"] = $item["hook"];
					
					$response["response"] = $this->doAction($item["action"],$item["data"]);
					if (!isset($response["response"]["success"])) $response["response"]["success"] = 1;
					$this->append($response);
				}
			} else {
				$this->append(array("error"=>"Bad request","text"=>"Original request:\n".bin2hex($origRequest)));
			}
		}
		$this->getEvents();
		
		if (isset($_SESSION["scriptRunTime"]) && count($_SESSION["scriptRunTime"]) > 1) {
			$runTime = 0;
			$notRunTime = 0;
			$lastRun = null;
			while (($run = array_shift($_SESSION["scriptRunTime"]))) {
				if (count($_SESSION["scriptRunTime"])) {
					$runTime += $run[1] - $run[0];
				}
				if ($lastRun) {
					$nrt = $run[0] - $lastRun[1];
					if ($nrt > 0) $notRunTime += $nrt;
				}
				$lastRun = $run;
			}
			array_unshift($_SESSION["scriptRunTime"],$lastRun);
			$this->append(array("load"=>array($runTime,$notRunTime)));
		}
	}
		
	//Events
	function getEvents() {
		$file = $GLOBALS["slCore"]->userDir()."/event-queue";
		if (!is_file($file)) return;
		if ($events = explode("\n",fileGetLock($file,true))) {
			
			foreach($events as $event) {
				if ($event) {
					list($event,$ts,$params) = explode(",",$event,3);
					$params = json_decode($params,true);
					
					switch ($event) {
						case "mobile-pass-login":
							if ($GLOBALS["slSession"]->logIn(array("user"=>$params["user"],"special"=>"mobile-pass"),true)) {
								$this->appendEvent($event,array("success"=>1,"user"=>$GLOBALS["slSession"]->user->get("user"),"formattedUser"=>$GLOBALS["slSession"]->user->get("formattedUser"),"loginTime"=>$_SESSION["loginTime"]));
							}
							break;
							
						default:
							$this->appendEvent($event,$params,$ts);
							break;
					}
				}
			}
		}
	}
	
	function appendEvent($event,$params,$ts = false) {
		if ($ts === false) $ts = time();
		$this->append(array(
			"event"=>$event,
			"ts"=>(int)$ts,
			"params"=>$params
		));
	}
	
	function doAction($action,$data) {
		switch ($action) {
			case "connect":
				return array("success"=>$this->connected,"uid"=>$this->uniqueID);
			
			case "app-module":
				if (!isset($data["module"])) return array("success"=>0,"error"=>"module not specified"); 
			case "app":
				require_once(SL_INCLUDE_PATH."/class.slApp.php");
				$app = new slApp($data);
				return $app->getNetResponse();
			
			case "lib-req":
				if (count($data)) {
					$reqFile = array_shift($data);
					if (count($data)) {
						$reqFunction = toCamelCase($reqFile)."_".array_shift($data);
						$file = SL_LIB_PATH."/".str_replace("../","",$reqFile).".php";
						
						if (is_file($file)) {
							require_once($file);
							if (function_exists($reqFunction)) {
								return array("success"=>1,"res"=>call_user_func_array($reqFunction,$data));
							}
							return array("success"=>0,"error"=>"reqFunction '$reqFunction' not found"); 
						}
						return array("success"=>0,"error"=>"reqFile '$file' not found"); 
					}
					return array("success"=>0,"error"=>"reqFunction not specified"); 
				}
				return array("success"=>0,"error"=>"reqFile not specified"); 
				
			case "app-req":
				require_once(SL_INCLUDE_PATH."/class.slApp.php");
				if (isset($data["windowDestruct"])) {
					$GLOBALS["slWindowClose"] = true;
					$GLOBALS["slSession"]->user->setWindowClose();
				}

				if (isset($data["uid"])) {
					if (isset($data["request"])) {
						$uids = is_array($data["uid"]) ? $data["uid"] : array($data["uid"]);
						foreach ($uids as $uid) {
							if (isset($this->apps[$uid])) {
								$app = $this->apps[$uid];
							} else {
								$app = new slApp(array("uid"=>$uid));
								$this->apps[$uid] = $app;
							}
							switch ($data["request"]) {
								case "destruct":
									$app->destructInstance();
									break;
									
								default:								
									if ($app->classExists()) {
										return $app->classCall($data["request"], isset($data["params"]) ? $data["params"] : array());
									} else return array("success"=>0,"error"=>"app hass no class file"); 
									break;
							}
						}
						return array("success"=>1);
					} else return array("success"=>0,"error"=>"'request' not specified");
				} else return array("success"=>0,"error"=>"app instance 'uid(s)' not specified");
				return array("success"=>0);
				
			case "listener-add":
				return array("id"=>$GLOBALS["slCore"]->addListener($data["event"]));
				
			case "listener-remove":
				$GLOBALS["slCore"]->removeListener($data["id"]);
				return array("success"=>true);
			
			case "broadcast-event":
				$GLOBALS["slCore"]-> dispatch("*:".$data["type"],$data["params"]);
				return array("success"=>true);
			
			case "send-event":
				if ($data["type"] == "chat-message") {
					$chatHistory = new slRecord($GLOBALS["slSession"]->user->dir."/chat-history",array("day","from"));
					
					$ts = isset($data["params"]["ts"]) ? $data["params"]["ts"] : time();
					
					$chatHistory->append(array(
						"ts"=>$ts,
						"date"=>floor($ts / 86400),
						"to"=>$data["user"],
						"from"=>$data["params"]["from"],
						"message"=>$data["params"]["message"]
					));
					
					if ($dir = $GLOBALS["slCore"]->userDir($data["user"])) {
						$chatHistory = new slRecord($dir."/chat-history",array("day","from"));
						$chatHistory->append(array(
							"ts"=>$ts,
							"date"=>floor($ts / 86400),
							"to"=>$data["user"],
							"from"=>$data["params"]["from"],
							"message"=>$data["params"]["message"]
						));
					}
				}
				$GLOBALS["slCore"]->dispatch($data["user"].":".$data["type"],$data["params"]);
				return array("success"=>true);
				
			case "login-status":
				if (setAndTrue($data,"user")) {
					return array(
						"success"=>true,
						"loggedIn"=>is_file(SL_DATA_PATH."/users/".safeFile($data["user"])."/loggedin")
					);
				} else {
					$loggedIn = $GLOBALS["slSession"]->isLoggedIn();
					return array(
						"success"=>true,
						"loggedIn"=>$loggedIn ? $GLOBALS["slSession"]->user->get("formattedUser") : false,
						"user"=>$GLOBALS["slSession"]->getUserName(),
						"name"=>$loggedIn ? $GLOBALS["slSession"]->user->get("name") : false,
						"credits"=>$GLOBALS["slSession"]->user->get("credits")
					);
				}

			case "item-info":
				return array(
					"success"=>true,
					"info"=>translate($GLOBALS["slCore"]->db->getTableInfo($data["ref"]))
				);
				
			case "item":
				if (isset($data["id"])) $find = array("_KEY"=>$data["id"]);
				if (isset($data["unique"])) $find = array("_UNIQUE"=>$data["unique"]);
				if ($res = $GLOBALS["slCore"]->db->select($data["ref"], $find, array("limit"=>1))) {
					return array(
						"success"=>true,
						"item"=>$GLOBALS["slCore"]->db->fetch($data["ref"],$res),
						"info"=>translate($GLOBALS["slCore"]->db->getTableInfo($data["ref"]))
					);
				}
				return array(
					"success"=>true,
					"item"=>NULL
				);
			
			case "item-name":
				if ($res = $GLOBALS["slCore"]->db->select($data["ref"], array("_KEY"=>$data["id"]), array("select"=>"_NAME","limit"=>1))) {
					$data = $GLOBALS["slCore"]->db->fetch($data["ref"],$res);
					return array(
						"success"=>true,
						"name"=>$data["_NAME"]
					);
				}
				return array(
					"success"=>true,
					"name"=>translate("en-us|(Deleted)")
				);
					
			case "create-item":
				return array(
					"success"=>true,
					"id"=>$GLOBALS["slCore"]->db->insert($data["ref"],$data["data"],null,isset($data["options"])?$data["options"]:array())
				);
				
			case "get-object-list":
				$info = translate($GLOBALS["slCore"]->db->getTableInfo($data["ref"]));
				$select = array("_KEY","_NAME","_UNIQUE");
				
				if (isset($data["fields"])) $select = array_merge($select,$data["fields"]);
				
				if (isset($info["parentField"])) $select[] = $info["parentField"];
				if (isset($info["optionGroup"]["parent"])) {
					$select[] = $info["optionGroup"]["parent"];
					$select[] = $info["optionGroup"]["typesField"];
					$select[] = $info["optionGroup"]["nameField"];
				}
				
				if ($res = $GLOBALS["slCore"]->db->select($data["ref"], "1", array("select"=>$select))) {
					return array(
						"success"=>true,
						"info"=>$info,
						"list"=>$res->fetchAll()
					);
				}
				return array(
					"success"=>true,
					"list"=>NULL
				);
				
			case "search":
				$where = array("_NAME"=>array("contains",$data["text"]));
				if (isset($data["match"]) && is_array($data["match"])) $where = array_merge($where,$data["match"]);
				if ($res = $GLOBALS["slCore"]->db->select($data["ref"], $where, array("limit"=>20,"select"=>array("_KEY","_NAME","_UNIQUE")))) {
					return array(
						"success"=>true,
						"matches"=>$GLOBALS["slCore"]->db->fetchAll($data["ref"],$res)
					);
				}
				return array(
					"success"=>true,
					"matches"=>NULL
				);
			
			case "force-logout":
				$data["force-logout"] = true;
			case "login":
				if (strpos($data["user"],"@") !== false) {
					$data["email"] = $data["user"];
					unset($data["user"]);
				}
				if ($GLOBALS["slSession"]->logIn($data)) {
					return array(
						"success"=>true,
						"user"=>$GLOBALS["slSession"]->user->get("user"),
						"name"=>$GLOBALS["slSession"]->user->get("name"),
						"credits"=>$GLOBALS["slSession"]->user->get("credits"),
						"formattedUser"=>$GLOBALS["slSession"]->user->get("formattedUser"),
						"loginTime"=>$_SESSION["loginTime"]
					);
				} else {
					return array("success"=>false,"error"=>$GLOBALS["slSession"]->getFailMessage());
				}
			
			case "user-check":
				if ($GLOBALS["slSetupMode"]) return array("success"=>1);
				$where = array('user'=>$data["user"],"_NO_USER"=>1);
				if (setAndTrue($data,"userID")) $where["_KEY"] = array("!=",$data["userID"]);
				$r = $GLOBALS["slCore"]->db->select($GLOBALS["slSession"]->user->table,$where);
				return array("success"=>$r ? 0 : 1);
			
			case "email-check":
				if ($GLOBALS["slSetupMode"]) return array("success"=>1);
				$where = array('email'=>$data["email"],"_NO_USER"=>1);
				if (setAndTrue($data,"userID")) $where["_KEY"] = array("!=",$data["userID"]);
				$r = $GLOBALS["slCore"]->db->select($GLOBALS["slSession"]->user->table,$where);
				return array("success"=>$r ? 0 : 1);
				
			case "logout":
				return array("success"=>$GLOBALS["slSession"]->logOut());
			
			case "file-store":
				$pDir = $GLOBALS["slSession"]->getUserParentDir();
				
				$file = $pDir."/tmp/".md5($data["uid"]);
				
				if (isset($data["fp"]) && $data["pos"] == 0) {
					if (is_file($file.".partial")) {
						$a = explode("\n",file_get_contents($file.".partial"));
						return array("success"=>1,"partial"=>array("pos"=>(int)$a[0],"fp"=>$a[1])); 
					}
				}
				
				if ($fp = openAndLock($file,"c+")) {
					fseek($fp,$data["pos"]);
					fwrite($fp,base64_decode($data["data"]));
					$pos = ftell($fp);
					closeAndUnlock($fp);
				}
				
				
				if ($pos >= $data["size"]) {					
					if (isset($data["fp"])) {
						unlink($file.".partial");
					}
					
					$mime = mimeInfo($data["type"],"mime");
									
					list($type,$ext) = explode("/",$data["type"]);
					
					if ($mime) $ext = array_shift(explode(",",$mime[2]));
					
					$md5 = md5_file($file);
					$dir = $pDir."/file/".$type;
					makePath($dir);
					rename($file, $dir."/".$md5.".".$ext);
					return array("success"=>1,"md5"=>$md5);
				}
				
				if (isset($data["fp"])) {
					file_put_contents($file.".partial",$pos."\n".$data["fp"]);
				}
				
				return array("success"=>1);
		}
		return array("success"=>0,"error"=>"Unknown action: '".$action."'");
	}
	
	function append($r) {
		$this->response[] = $r;
	}
	
	function newUID() {
		return base64_encode(sha1(microtime()."-".rand(0,1000000),1));
	}
	
	function respond() {
		while ($error = array_shift($GLOBALS["slCore"]->errorQueue)) {
			$this->append(array("error"=>$error[0],"critical"=>$error[1],"backtrace"=>$error[2]));
		}

		while ($debug = array_shift($GLOBALS["slCore"]->debugQueue)) {
			$this->append(array("debug"=>$debug));
		}

		$GLOBALS["slScriptLoad"] = 1;
		if (!isset($GLOBALS["slWindowClose"]) && $GLOBALS["slSession"]->isLoggedIn()) $GLOBALS["slSession"]->user->setWindowOpen();
		
		echo silent_json_encode($this->response);
		switch (json_last_error()) {
			case JSON_ERROR_UTF8:
				echo silent_json_encode(self::utf8ize($this->response));
				break;
			
			case JSON_ERROR_NONE:
				break;
				
			default:
				echo "Could not encode:\n\t".self::$jsonErrors[json_last_error()];
				break;
		}
	}
	
	public static function utf8ize($mixed) {
		if (is_array($mixed)) {
			foreach ($mixed as $key => $value) {
				$mixed[$key] = self::utf8ize($value);
			}
		} else if (is_string ($mixed)) {
			return utf8_encode($mixed);
		}
		return $mixed;
	}
}
