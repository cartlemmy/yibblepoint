<?php

class slInitialSetup extends slAppClass {
	private $config = array();

	function __construct($app) {
		$this->app = $app;
		
		$this->config = $GLOBALS["slSession"]->getUserData("config");
		
		if (!$this->config) {
			$GLOBALS["slSession"]->setUserData("config",$this->config = $this->getDef());
		}
		
		parent::__construct($app);
	}
	
	function getDefaultConfig($n) {
		$ns = explode(".",$n);
		$o = $GLOBALS["slConfig"];
		while ($n = array_shift($ns)) {
			if (isset($o[$n])) {
				$o = $o[$n];
			} else return null;
		}
		return $o;
	}
	
	function thirdPartyStatus($n = false) {
		$data = $this->getDefaultConfig("thirdparty");
		if ($n === false) {
			foreach ($data as $n=>$o) {
				$data[$n]["installed"] = $this->isInstalled($n,$data);
			}
			return $data;
		}
		if (!isset($data[$n])) return false;
		
		$data[$n]["installed"] = $this->isInstalled($n,$data);
		return $data[$n];
	}
	
	function isInstalled($n,$data) {
		if (isset($data[$n]["className"])) {
			if (class_exists($data[$n]["className"])) return true;
			
			$paths = explode(":",get_include_path());
			foreach ($paths as $path) {
				if (!$path != "." && file_exists($path."/".$data[$n]["packageDir"]."/".$data[$n]["include"])) return true;
			}
		}
		
		$dir = SL_INCLUDE_PATH."/thirdparty/".$n;
		if (isset($data[$n]["include"])) {
			$file = $dir."/".$data[$n]["include"];
			return is_file($file);
		} 
		return is_dir($dir);
	}
	
	function getConfig($n) {
		$ns = explode(".",$n);
		$o = $this->config;
		while ($n = array_shift($ns)) {
			if (isset($o[$n])) {
				$o = $o[$n];
			} else return null;
		}
		return $o;
	}
	
	function setConfig($no,$v) {
		$ns = explode(".",$no);
		$o = &$this->config;
		while ($n = array_shift($ns)) {
			if (!isset($o[$n])) $o[$n] = array();
			$o = &$o[$n];
		}
		
		if (is_array($v)) {
			$o = array_merge($o,$v);
		} else {
			$o = $v;
		}
		$GLOBALS["slSession"]->setUserData("config",$this->config);
		if ($no == "db") return $this->testDB($o);
		return true;
	}
	
	function testDB($params) {
		try {
			$db = new slDB();
			$db->silentError = true;
			$conn = $db->connect($params);
		} catch (Exception $e) {
			return array("success"=>false,"error"=>$e->getMessage());
		}
		
		if (!$conn) {
			return array("success"=>false,"error"=>$db->getLastErrorText());
		}
		return array("success"=>true,"db"=>$db,"conn"=>$conn);
	}
	
	function checkLicense() {
		
		require_once(SL_INCLUDE_PATH."/class.slRemote.php");

		$req = new slRemote();

		$res = $req->request(array(
			CURLOPT_URL=>$GLOBALS["slConfig"]["updater"]."admin/update",
			"post"=>array(
				"version"=>$GLOBALS["slConfig"]["version"]
			),
			"validateLicense"=>true,
			"encode"=>"json"
		));

		return array(
			"check"=>$res,
			"license"=>$GLOBALS["slConfig"]["license"]
		);
	}
	
	function complete($step) {		
		ob_start();
		$error = false;
		switch ($step) {
			case 0: //Initialize DB
				echo "<b>Initializing DB</b>\n";
				$db = $this->testDB($this->config["db"]);
				if ($db && $db["success"]) {
					$allSql = explode(";",file_get_contents("install-tmp/core.sql"));
					foreach ($allSql as $sql) {
						if ($sql = trim($sql)) {
							echo array_shift(explode("\n",$sql))." ...\n";
							if ($db["conn"]->query($sql)) {
								if ($db["conn"]->conn->affected_rows) {
									echo "\tCREATED\n";
								} else {
									echo "\tALREADY EXISTS\n";
								}
							} else {
								echo "\tFAILED\n";
								$error = true;
							}
						}
					}
				} else {
					echo $db["error"]."\n";
					$error = true;
				}
				break;
				
			case 1: //Create super user
				echo "<b>Creating Super User</b>\n";
				//print_r($this->config["superUser"]);
				
				$GLOBALS["slCore"]->setDB(new slDB($this->config["db"]));
				if ($GLOBALS["slSession"]->user->create($this->config["superUser"])) {
					$GLOBALS["slSession"]->user->setPermission("user");
					$GLOBALS["slSession"]->user->setPermission("session");
					$GLOBALS["slSession"]->user->setPermission("admin");
					$GLOBALS["slSession"]->user->setPermission("super");
					$GLOBALS["slSession"]->user->setPermission("useradmin");
					$GLOBALS["slSession"]->user->setPermission("viewprotected");
					$GLOBALS["slSession"]->user->setPermission("developer");
					echo "Super user (".$this->config["superUser"]["user"].") created.\n";
				} else {
					echo $GLOBALS["slSession"]->user->getAllErrorText();
				}
				break;
				
			case 2: // Write config
				echo "<b>Creating Config File</b>\n";
				$config = $this->config;
				unset($config["superUser"]);
				
				$config["db"]["password"] = encryptToConfig($config["db"]["password"]);
				$config["communication"]["smtp"]["Password"] = encryptToConfig($config["communication"]["smtp"]["Password"]);
				 
				$file = SL_INCLUDE_PATH."/config.php";
				
				if (!is_file($config) && file_put_contents($file,"<?php\n\nreturn ".cleanVarExport($config).";")) {
					echo "Config file successfully created.\n";
				} else {
					echo "Failed to write to config file ($file).\n";
				};
				break;			
		}
		return array("error"=>$error,"out"=>ob_get_clean());
	}
	
	function getDef() {
		return array(
			"package"=>array(
				"name"=>"¥ibblePoint",
				"key"=>sha1(rand(0,0x7FFFFFFF)."-".microtime(true))
			),
			"dev"=>array(
				"debug"=>false,
				"netDebug"=>false,
				"logDispatcher"=>true,
				"verbose"=>true
			),
			"db"=>array(
				"initializeIfNonExistent"=>false,
				"type"=>"mysql",
				"server"=>"localhost",
				"user"=>"",
				"db"=>"",
				"password"=>"",
				"prefix"=>""
			),
			"user"=>array(
				"table"=>"db/user",
				"salt"=>$this->getSalt(),
				"validation"=>array("email")
			),
			"international"=>array(
				"country"=>"us",
				"language"=>"en-us",
				"currency"=>"USD",
				"dateOrder"=>"mdy",
				"date"=>"n/j/Y",
				"month"=>"M Y",
				"year"=>"Y",
				"time"=>"g:ia",
				"date-time"=>"n/j/Y g:ia",
			),
			"preferences"=>array(
				"autoSave"=>false
			),
			"communication"=>array(
				"smtp"=>array(
					"Host"=>"localhost",
					"SMTPAuth"=>false,
					"Port"=>25,
					"Username"=>"",
					"Password"=>""
				),
				"defaultFrom"=>array(
					"name"=>$GLOBALS["slConfig"]["license"]["licensedTo"],
					"email"=>"noreply@".$_SERVER["SERVER_NAME"]
				)
			),
			"superUser"=>array(
				"name"=>$GLOBALS["slConfig"]["license"]["licensedTo"],
				"user"=>"super",
				"email"=>"",
				"password"=>""
			)
		);
	}
	
	function getSalt($len = 64) {
		$rv = "";
		while (strlen($rv) < $len) {
			$rv .= str_replace(array("_","/","+","="),array("","","",""),base64_encode(sha1(rand(0,0x7FFFFFFF)."-".microtime(true),true)));
		}
		return substr($rv, 0, $len);
	}
}

function cleanVarExport($var,$ind = "  ",$level = 0) {
	if (is_array($var)) {
		if (is_assoc($var)) {
			$rv = array();
			$chars = strlen($ind) * $level;
			foreach ($var as $v) {
				$t = cleanVarExport($v, $ind, $level + 1);
				$rv[] = $t;
				$chars += strlen($t);
			}
			if ($chars < 200) {
				return str_replace("\n","\n".str_repeat($ind,$level),"array(".implode(", ",$rv).")");
			} else {
				return str_replace("\n","\n".str_repeat($ind,$level),"array(\n".$ind.implode(",\n".$ind,$rv)."\n)");
			}
		} else {
			$rv = array();
			foreach ($var as $n=>$v) {
				$rv[] = var_export($n,true)."=>".cleanVarExport($v, $ind, $level + 1);
			}
			return str_replace("\n","\n".str_repeat($ind,$level),"array(\n".$ind.implode(",\n".$ind,$rv)."\n)");
		}	
	}
	return var_export($var,true);
}

function is_assoc($var) {
	$i = 0;
	foreach ($var as $n=>$v) {
		if ($n !== $i) return false;
		$i++;
	}
	return true;
}

