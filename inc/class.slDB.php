<?php

class slDB extends slClass {
	private $settings;
	public $connection = array();
	private $tableInfo = array();
	public $lastConnection = false;
	
	function __construct($settings = false) {
		if ($settings) $this->connect($settings);
	}
	
	function connect($settings) {
		$this->lastConnection = false;
		if (isset($settings["silentError"])) $this->silentError = $settings["silentError"];
		
		if (isset($settings["type"])) {
			switch ($settings["type"]) {
				case "mysql":
					$subType = "db";
					require_once(SL_INCLUDE_PATH."/class.slMysql.php");
					$this->setConnection($subType, new slMysql());
					break;
				
				case "sqlite":
					require_once(SL_INCLUDE_PATH."/class.slSQLite.php");
					
					$sqlite = new slSQLite();
					if ($sqlite->connect($settings)) {
							return $this->setConnection($sqlite->getSubType(),$sqlite);
					} else return $this->error($sqlite->getLastErrorText());
					
					break;
					
				case "user":
					$subType = "user";
					if (!$settings["session"]) return $this->error('$settings["session"] not defined.');
					$this->user = $settings["session"]->getUser();
					$this->setConnection($subType,$settings["session"]->getUserFileData());
					break;
				
				case "global":
					$subType = "global";
					require_once(SL_INCLUDE_PATH."/class.fileData.php");
					$this->setConnection($subType, new fileData(LGPHP_ROOT_DIR."/data/global"));
					break;
				
				case "xml":
					$subType = "xml";
					require_once(SL_INCLUDE_PATH."/class.slXMLData.php");
					$this->setConnection($subType, new slXMLData($settings));
					break;
				
				case "api":
					$subType = "api";
					require_once(SL_INCLUDE_PATH."/class.slAPIData.php");
					$this->setConnection($subType, new slAPIData($settings));
					break;
						
				default:
					$file = SL_LIB_PATH.'/db/'.safeFile($settings["type"]).'.php';
					if (is_file($file)) {
						$subType = $settings["type"];
						$settings["type"] = "custom";
						require_once($file);
						break;
						
					}
					return $this->error('Database type ($settings["type"]) \''.$settings["type"].'\' unknown.');
			}
			
			if ($this->connection[$subType]) {
				if ($this->connection[$subType]->connect($settings)) {
					$this->lastConnection = $this->connection[$subType];
					return $this->connection[$subType];
				} else return $this->error($this->connection[$subType] ? $this->connection[$subType]->getLastErrorText() : 'Connection failed.');
			} else return $this->error('Could not create connection.');
		} else return $this->error('$settings["type"] not defined.');
		
		$this->settings = $settings;
	}
	
	public static function generateUUID($name = false) {
		$parts = array('yp');
		
		$parts[] = array_reverse(explode('.', $_SERVER["SERVER_NAME"]));
		
		if (is_string($name) || is_numeric($name)) $parts[] = explode(' ',searchify($name));
			
		return str_replace('.www','',implode('.',self::flatten($parts)));
	}

	public static function flatten($a, &$rv = false) {
		if ($rv === false) $rv = array();
		
		foreach ($a as $v) {
			if (is_array($v)) {
				self::flatten($v, $rv);
			} else {
				$rv[] = $v;
			}
		}
		return $rv;
	}
	
	function refToNumber($ref) {
		if ($res = $this->select("db/dbRef",array("ref"=>$ref))) {
			$row = $res->fetch_assoc();
			return (int)$row["id"];
		}
		return $this->insert("db/dbRef",array("ref"=>$ref));
	}
	
	function numberToRef($id) {
		if ($res = $this->select("db/dbRef",array("id"=>$id))) {
			$row = $res->fetch_assoc();
			return (int)$row["ref"];
		}
		return false;
	}
	
	function convertFields($table, $data, $transferNonCorrelated = false) {
		$out = array();
		$info = $this->getTableInfo($table);
		
		foreach ($info["fields"] as $n=>$field) {
			//if (!(isset($field["import"]) && $field["import"] === true)) {
				if (isset($field["import"]) && $field["import"] !== true) $n = $field["import"];
				
				$names = array(searchify($n));
				if (isset($field["label"]) && !in_array(searchify($field["label"],''),$names)) $names[] = searchify($field["label"],'');
				if (isset($field["importNames"])) {
					$s = explode(",",$field["importNames"]);
					foreach ($s as $name) {
						$name = searchify($name,'');
						if (isset($field["label"]) && !in_array($name,$names)) $names[] = searchify($name,'');
					}
					
					foreach ($data as $dn=>$v) {
						if (in_array(searchify($dn,''),$names)) {
							$out[$n] = $v;
							unset($data[$dn]);
							break;
						}
					}
				}
			//}	
		}
		if ($transferNonCorrelated) $out = array_merge($out,$data);
		return $out;
	}
	
	function attach($table, $files, $find) {
		if ($res = $this->select($table, $find)) {
			while ($row = $res->fetch()) {
				$path = $this->getUserDir($row["userId"])."/attachments/".$table."/".$row["_KEY"];
				makePath($path);
				foreach ($files as $file=>$source) {
					if (is_numeric($file)) $file = array_pop(explode("/",$source));
					copy($source,$path."/".$file);
				}
			}
			return true;
		}
		return false;
	}
	
	function getUserDir($id) {
		if ($res = $this->select("db/user",array("_KEY"=>$id))) {
			$user = $res->fetch();
			return SL_DATA_PATH."/users/".$user["user"];
		}
		return false;
	}
	
	function updated($table) {
		$info = $this->getTableInfo($table);
		if (setAndTrue($info,'touch')) {
			if (!is_array($info['touch'])) $info['touch'] = array($info['touch']);
			foreach ($info['touch'] as $file) {
				touch($file);
			}
		}
	}
	
	function select($table, $find = false, $options = false) {		
		return $this->action("select", $table, $find, $options);
	}
	
	function where($table,$find,$delim = " AND ",$noExtra = false,$insert = false) {		
		return $this->action("where", $table, $find, $delim, $noExtra, $insert);
	}
	
	function query($table, $query) {		
		return $this->action("query", true, $table, $query, array_pop(explode("/",$table)));
	}
	
	function getSchema($table) {
		return $this->action("getSchema", true, $table);
	}
		
	function selectOne($table, $find = false, $options = false) {
		if ($options === false) $options = array();
		$options["limit"] = 1;
		return $this->action("select", $table, $find, $options);
	}
	
	function set($table,$n,$v) {
		return $this->action("set", $table,$n,$v);
	}
	
	function get($table,$n) {
		return $this->action("get", $table,$n);
	}
	
	function begin($table) {
		return $this->action("begin",$table);
	}
	
	function commit($table) {
		return $this->action("commit",$table);
	}
	
	function update($table,$data,$find, $options = array()) {
		return $this->action("update", $table,$data,$find, $options);
	}
	
	function upsert($table,$data,$find, $options = array()) {
		return $this->action("upsert", $table,$data,$find, $options);
	}
	
	function insert($table,$data,$find = false, $options = array("select"=>"id")) {
		return $this->action("insert",$table,$data,$find,$options);
	}
	
	function delete($table,$find) {
		return $this->action("delete",$table,$find);
	}
	
	function getTableKey($table) {
		return $this->action("getTableKey", $table);
	}
	
	function selectByKey($table, $key) {
		return $this->action("selectByKey", $table, $key);
	}
	
	function updateByKey($table, $key, $data) {
		return $this->action("updateByKey", $table, $key, $data);
	}
	
	function fetch($table,$res = null) {
		return $this->action("fetch", $table, $res);		
	}
	
	function fetchAll($table,$res = null) {
		return $this->action("fetchAll", $table, $res);		
	}
		
	function safe($table, $string, $noQuotes = false) {
		return $this->action("safe", true, $table, $string, $noQuotes);
	}
	
	function preventUpdateEvents($table) {
		return $this->action("preventUpdateEvents", $table);
	}	
	
	function setConnection($st, $conn) {
		$this->connection[$st] = $conn;
		if (!isset($GLOBALS["slDBConnections"])) $GLOBALS["slDBConnections"] = array();
		$GLOBALS["slDBConnections"][$st] = $conn;
	}
	
	function wasInserted($table) {
		return $this->action("wasInserted",$table);
	}
	
	function action() {
		$args = func_get_args();		
		
		$method = array_shift($args);
		
		$tableInParams = true;
		
		if ($args[0] === true) {
			$tableInParams = false;
			array_shift($args);
		}
		
		list($subType,$table) = $this->extractType(array_shift($args));
		
		//Not sure why this is here:
		//if ($tableInParams && ($info = $this->getTableInfo($subType."/".$table))) {
			
		//}
		
		if (!isset($this->connection[$subType])) return $this->error('Type \''.$subType.'\' invalid.');
		
		if (!method_exists($this->connection[$subType],$method)) $this->error('No such method '.$method.' for '.$subType.'.');
		
		if ($tableInParams) {
			array_unshift($args, $table);
		} 
		
		return call_user_func_array(array($this->connection[$subType],$method),$args);
	}
	
	function hashAll($subType) {
		return $this->connection[$subType]->hashAll();
	}
	
	function getTableInfo($table) {
		$fullTable = $table;
		$table = str_replace("/",".",$table);
		
		if (!isset($this->tableInfo[$table])) {
			$tableInfoDir = $GLOBALS["slConfig"]["root"]."/lib/definitions/".$table."/";

			$tableInfoFile = $tableInfoDir."info.php";
			$tableIconFile = $tableInfoDir."icon";
			if (is_file($tableInfoFile)) {
				
				$c = file_get_contents($tableInfoFile); //TODO: is it worth it to cache this?
				$match = array();
				if (preg_match('/class (\w+) extends slDBDefinition/',$c,$match)) {
					$className = $match[1];
					require_once($tableInfoFile);
					$def = new $className;
					$this->tableInfo[$table] = $def->getDefinition();
					$this->tableInfo[$table]["_O"] = $def;
				} else {
					$this->tableInfo[$table] = translate(require($tableInfoFile));
				}
				
				
				if (!isset($this->tableInfo[$table]["icon"])) {
					if (is_file($tableIconFile.".png")) iconResize($tableIconFile);
					$this->tableInfo[$table]["icon"] = is_file($tableIconFile.".png") ? "../lib/definitions/".$table."/icon" : "";
				}
				if (isset($this->tableInfo[$table]["fields"])) {
					$this->tableInfo[$table]["sourceMap"] = array();
					foreach ($this->tableInfo[$table]["fields"] as $n=>$v) {
						if (isset($v["source"])) {
							if (!isset($this->tableInfo[$table]["sourceMap"][$v["source"]])) {
								$this->tableInfo[$table]["sourceMap"][$v["source"]] = array();
							}
							$this->tableInfo[$table]["sourceMap"][$v["source"]][] = $n;
						}
					}
				}
				
				$this->tableInfo[$table]["reports"] = array();
				if (is_dir($tableInfoDir."reports")) {
					if ($dp = opendir($tableInfoDir."reports")) {
						while (($file = readdir($dp)) !== false) {
							if (array_pop(explode(".",$file)) == "php" && ($rInfo = require($tableInfoDir."reports/".$file))) {
								$this->tableInfo[$table]["reports"][str_replace(".php","",$file)] = $rInfo;
							}
						}
						closedir($dp);
					}
				}
				if (!$this->tableInfo[$table]["reports"]) $this->tableInfo[$table]["reports"] = null;
			} else {
				//$this->error("Table info file '".$tableInfoFile."' not found");
				$this->tableInfo[$table] = null;
			}
		}

		
		/*list($subType,$tableShort) = $this->extractType($fullTable);
		$conn = isset($this->connection[$subType]) ? $conn : false;
		if ($conn && method_exists($conn, 'extraTableInfo')) {
			TODO: parse ENUM and SET values
		}
		*/
		if (!isset($this->tableInfo[$table]["fields"])) $this->tableInfo[$table]["fields"] = array();
		foreach ($this->tableInfo[$table]["fields"] as $n=>&$field) {
			if (!isset($field["depLevel"])) $this->tableInfo[$table]["fields"][$n]["depLevel"] = 0;
			if (isset($field["type"]) && $field["type"] == "group") $this->tableInfo[$table]["groupField"] = $n;
		}
		
		unset($field);
		
		$cnt = 0;
		do {
			$changed = false;
			foreach ($this->tableInfo[$table]["fields"] as $n=>&$field) {
				if (isset($field["dependency"])) {
					$dep = explode(',', $field["dependency"]);
					foreach ($dep as $depN) {
						$depLevel = 1;
						if ($depLevel > $this->tableInfo[$table]["fields"][$n]["depLevel"]) {
							$this->tableInfo[$table]["fields"][$n]["depLevel"] = $depLevel;
						}
						
						if (
							$depN == "_KEY" &&
							!isset($this->tableInfo[$table]["fields"][$depN]) &&
							substr($table,0,4) == "xml."
						) {
							$this->tableInfo[$table]["fields"][$depN] = array(
								"label"=>"_KEY",
								"type"=>"placeholder",
								"searchable"=>false,
								"viewable"=>false,
								"editable"=>false,
								"depLevel"=>0
							);
						}
						
						if (isset($this->tableInfo[$table]["fields"][$depN])) {
							$depLevel ++;
							if ($depLevel > $this->tableInfo[$table]["fields"][$depN]["depLevel"]) {
								$changed = true;
								$this->tableInfo[$table]["fields"][$depN]["depLevel"] = $depLevel;
							}
						}
					}
				}
			}
			unset($field);
			$cnt++;
		} while ($changed && $cnt < 5);
		unset($field);
		
		//uasort($this->tableInfo[$table]["fields"], array($this, "fieldSort")); 
		
		$this->tableInfo[$table]["fullTable"] = $fullTable;
		
		return $this->tableInfo[$table];
	}
	
	public function fieldSort($a, $b) {
		return (isset($b["depLevel"]) ? $b["depLevel"] : 0) -
			(isset($a["depLevel"]) ? $a["depLevel"] : 0);
	}
	
	function extractType($table) {
		if ((int)$table) $table = $this->numberToRef($table);
		$a = explode("/",str_replace(".","/",$table),2);
		$table = array_pop($a);
		return array(count($a) ? $a[0] : "db",$table);
	}
	
	function isConnected($subType) {
		return isset($this->connection[$subType]);
	}
}

class slDBDefinition {
	
}
