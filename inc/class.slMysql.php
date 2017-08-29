<?php

if (!defined('MYSQLND_QC_ENABLE_SWITCH')) define('MYSQLND_QC_ENABLE_SWITCH','qc=on');
if (!defined('MYSQLND_QC_DISABLE_SWITCH')) define('MYSQLND_QC_DISABLE_SWITCH','qc=off');
if (!defined('MYSQLND_QC_TTL_SWITCH')) define('MYSQLND_QC_TTL_SWITCH','qc_ttl=');

define('SL_MYSQL_MAX_CACHE_ROWS',500);

class slMysql extends slClass {
	public $conn = false;
	public $showQuery = false;
	private $nameFromDisplayName = false;
	private $fieldHash = array();
	private	$tables = array();
	private	$tableKeys = array();
	private $inserted = false;
	private $fieldSize = array("tinyint"=>1,"smallint"=>2,"mediumint"=>3,"int"=>4,"integer"=>4,"bigint"=>8);
	private $noUpdateEvents = false;
	private $oldData = null;
	private $inTransaction = false;
	
	function __construct() {
		if (setAndTrue($GLOBALS["slConfig"]["dev"],"dbDebug")) $this->showError = true;
	}
	
	function connect($settings) {
		if (isset($settings["silentError"])) $this->silentError = $settings["silentError"];
		
		if (!isset($settings["server"])) return $this->error('$settings["server"] not defined.');
		if (!isset($settings["user"])) return $this->error('$settings["user"] not defined.');
		if (!isset($settings["password"])) return $this->error('$settings["password"] not defined.');
		if (!isset($settings["db"])) return $this->error('$settings["db"] not defined.');
		
		try {
			$this->conn = new slMySQLi($this, $settings["server"], $settings["user"], $settings["password"]);
			if (!$this->conn->connect_error) {
				if ($this->selectDb($settings)) {
					$this->tableRefresh();
					return true;
				} else return $this->error('Failed to select db ($settings["db"]) `'.$settings["db"].'`.');
			} else return $this->error($this->conn->connect_error);
		} catch (Exception $e) {
			return $this->error($e->getMessage());
		} 
		return false;
	}	

	function selectDb($settings) {
		if ($this->conn->select_db($settings["db"])) return true;
		if ($settings["initializeIfNonExistent"]) {
			$this->log('Database `'.$settings["db"].'` does not exist, but $settings["initializeIfNonExistent"] is set. Attempting to create database.');
			if ($this->query("CREATE DATABASE `".$settings["db"]."` CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'")) {
				$this->log("Success.");
				return true;
			}
		}
		return false;
	}
	
	function query($query, $table = false, $options = false) {
		//if (strpos($query,"UPDATE") !== false || strpos($query,"INSERT") !== false) { echo $query."\n";return; }
		if ($this->showQuery || setAndTrue($GLOBALS,"showQuery")) {
			echo "\n<!-- \n".$query."\n -->\n";
			$GLOBALS["showQuery"] = $this->showQuery = false;
		}
				
		if (isset($GLOBALS["showAllQueries"])) echo $query.";\n";
		$res = $this->conn->query($query, null, $table, $options);
		
		if (!$res && $this->conn->error) {
			$this->error($this->conn->error."\n".$query);
		}
		return $res;
	}
	
	
	function insert($table,$data,$find = false, $options = array("select"=>"id")) {
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
		
		if (!(is_array($options) && (isset($options["direct"]) || isset($options["returnQuery"])))) {
			$ar = array(&$data, $find, $options);
			if (isset($info["insertFunction"])) {
				return call_user_func_array($info["insertFunction"], $ar);
			} elseif (isset($info["_O"])) {
				if (method_exists($info["_O"],"insertTable")) return call_user_func_array(array($info["_O"],"insertTable"),$ar);
			}
		}
		
		$dataOrig = $data;
				
		foreach ($data as $n=>$v) {
			if (isset($info["fields"][$n]) && isset($info["fields"][$n]["dependency"])) {
				$dep = explode(",", $info["fields"][$n]["dependency"]);
				foreach ($dep as $dn) {
					if (!isset($data[$dn])) $data[$dn] = "";
				}
			}
		}
		
		
		foreach ($data as $n=>$v) {
			if (isset($info["fields"][$n])) {
				if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group" && strlen($v) && ($v{0} == "+" || $v{0} == "-")) $array[$n] = substr($v,1);
			
				if (!isset($options["noUpdateFunction"]) && isset($dataOrig[$n]) && !isset($options["returnQuery"])) {
					$this->processCustomFieldUpdate($info,$n,$data);
				}
			}
		}

		if (isset($info["historyField"]) && !isset($options["returnQuery"])) $this->addHistory(array(),$data,$info);
		
		if (isset($info["broadSearchField"])) $data[$info["broadSearchField"]] = $this->getBroadSearchField($data,$info);
		
		if (isset($info["conform"])) {
			foreach ($info["conform"] as $n=>$v) {
				$data[$n] = $v;
			}
		}

		$insertQuery = $this->arrayToSql($table,$data,true,$options);
		
		if (isset($options["returnQuery"]) && $options["returnQuery"]) return "INSERT INTO ".$this->realTable($table)." SET ".$insertQuery;
		
		if (isset($info["unique"]) && isset($data[$info["unique"]]) && trim($data[$info["unique"]]) != "") {
			if ($res = $this->select($table,array($info["unique"]=>$data[$info["unique"]]),array("select"=>($key = $this->getTableKey($table)),"limit"=>"1"))) {
				$o = $res->fetch();
				if (isset($data[$key])) unset($data[$key]);
				$this->update($table,$data,array($key=>$o[$key]),$options);
				return isset($options["returnUnique"]) && $options["returnUnique"] ? $this->getUnique($table,$o[$key]) : (int)$o[$key];
			}
		}
		
		$this->inserted = false;
		if ($find) {
			if ($res = $this->select($table,$find,$options)) {
				$data = $res->fetch();
				return (int)$data[$this->getTableKey($table)];
			}
		}
	
		$this->inserted = true;

		//$GLOBALS["slCore"]->beginMeasure();

		$this->query("INSERT INTO ".$this->realTable($table)." SET ".$insertQuery);
		
		$data["_KEY"] = $id = $this->conn->insert_id;
		
		//$GLOBALS["slCore"]->endMeasure("INSERT");
		
		foreach ($dataOrig as $n=>$v) {
			if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group") {
				$this->groupUpdate("",$v,$info["fields"][$n],$table,$id);
			}
		}
		
		$data["_INSERTED"] = true;
		
		if (isset($GLOBALS["slConfig"]["sync"]) && is_array($GLOBALS["slConfig"]["sync"]["tables"]) && in_array("db/".$table,$GLOBALS["slConfig"]["sync"]["tables"])) {
			file_put_contents(SL_DATA_PATH."/ypSync/".safeFile("db/".$table).".upd",time().":".json_encode(array(array(),$data))."\n",FILE_APPEND);
		}  
					
		$this->processCustomUpdate($data, $find, $info);
			
		if (!$this->inTransaction) {
			$GLOBALS["slCore"]->nonUserSpecificDispatch("insert-db/".$table,$data);
			foreach ($data as $n=>$v) {
				if ($v === '' || $v === null) unset($data[$n]);
			}
			$GLOBALS["slCore"]->nonUserSpecificDispatch("change-db/".$table."/".$data["_KEY"],$data);
		}
		
		if (!$this->noUpdateEvents && isset($GLOBALS["slSession"])) $GLOBALS["slSession"]->tableUpdate("db/".$table);
		
		$GLOBALS["slCore"]->db->updated("db/".$table);
		
		return isset($options["returnUnique"]) && $options["returnUnique"] ? $this->getUnique($table,$id) : $id;
		
	}
	
	function processCustomUpdate(&$data, $find, $info) {
		$ar = array(&$data, $find, $info);
		if (isset($info["updateFunction"])) {
			call_user_func_array($info["updateFunction"],$ar);
		} elseif (isset($info["_O"])) {
			if (method_exists($info["_O"],"updateTable")) call_user_func_array(array($info["_O"],"updateTable"),$ar);
		}
	}
	
	function processCustomFieldUpdate($info,$n,&$data) {
		$v = $data[$n];
		
		$field = $info["fields"][$n];
		$ar = array($v,&$data,$info);
		
		if (isset($field["updateFunction"])) { //OLD way, might want to phase this out
			call_user_func_array($field["updateFunction"],$ar);
		} elseif (isset($info["_O"])) {
			$method = "update_".$n;
			if (method_exists($info["_O"],$method)) call_user_func_array(array($info["_O"],$method),$ar);
		}
	}
					
	function preventUpdateEvents($table) {
		$this->noUpdateEvents = true;
	}
	
	function wasInserted() {
		return !!$this->inserted;
	}
	
	function begin($table) {
		$this->query("START TRANSACTION");
		$this->inTransaction = true;
		//$this->query("LOCK TABLES `$table` WRITE");
	}
	
	function commit($table) {		
		//$this->query("UNLOCK TABLES");
		$this->query("COMMIT");
		$this->inTransaction = false;
	}
	
	function getUnique($table,$key) {
		if ($res = $this->selectByKey($table, $key, array("select"=>"_UNIQUE"))) {
			$o = $res->fetch_assoc();
			return $o["_UNIQUE"];
		}
		return $key;
	}
	
	function upsert($table,$data,$find) {
		if ($res = $this->select($table, $find)) {
			$row = $res->fetch();
			$update = array();
			foreach ($data as $n=>$v) {
				if (!isset($row[$n])) {
					$update[$n] = $v;
				} elseif (is_string($v) && $row[$n] !== $v) {
					$update[$n] = $v;
				} elseif (json_encode($row[$n]) !== json_encode($v)) {
					$update[$n] = $v;
				}
			}
			if (count($update)) {
				$this->update($table,$update,array('_KEY'=> $row["_KEY"]));
			}
			unset($update);
			return $row["_KEY"];
		}
		return $this->insert($table,$data,$find);
	}
	
	function updateByKey($table, $key, $data) {
		$keyField = $this->getTableKey($table);
		return $this->update($table,$data,array($keyField=>$key));
	}
	
	function update($table, $data, $find, $options = array()) {
		
		$dataOrig = $data;
		
		if (isset($options["showQuery"])) $this->showQuery = true;
		if (isset($info["historyField"]) && isset($data[$info["historyField"]])) unset($data[$info["historyField"]]);
		
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
		
		if (count($find) == isset($find["_NO_USER"]) ? 2 : 1) {
			$findKey = array_pop(array_keys($find));
			if (!($findKey == "_KEY" || $findKey == (isset($info["key"]) ? $info["key"] : "id") || (isset($info["unique"]) && $findKey == $info["unique"]))) {
				if ($res = $this->select($table,$find)) {
					$key = (isset($info["key"]) ? $info["key"] : "id");
					
					if ($res->num_rows == 1) {
						$row = $res->fetch_assoc();
						if (isset($find["_NO_USER"])) {
							$find = array($key=>$row[$key],"_NO_USER"=>1);
						} else {
							$find = array($key=>$row[$key]);
						}
					} else {
						while ($row = $res->fetch_assoc()) {
							$this->update($table,$data,array($key=>$row[$key]));
						}
					}
				} else return;
			}
		}
		
	
		$getData = false;
		$dependencies = array();
		

		foreach ($data as $n=>$v) {
			if (isset($info["fields"][$n])) {
				if (isset($info["fields"][$n]["dependency"])) {
					$d = explode(",",$info["fields"][$n]["dependency"]);
					foreach ($d as $dep) {
						if (!in_array($dep,$dependencies)) $dependencies[] = $dep;
					}
				}
				$dependencies[] = $n;
			} else $getData = true;
		}
		
		foreach ($data as $n=>$v) {
			if (!isset($this->tables[$this->_T($table)][$n])) $getData = true;
		}
		
		
		if (isset($info["historyField"])) $dependencies[] = $info["historyField"];
		
		if (isset($info["broadSearchField"])) $info["oldData"] = true;
		
		if ($getData || isset($info["oldData"]) || isset($info["historyField"]) || count($dependencies)) {

			$select = $getData && isset($this->tables[$this->_T($table)]["data"]) ? array("data") : array();
			
			if (isset($info["oldData"]) || isset($info["historyField"])) {
				$select = "*";
			} else if ($dependencies) {
				$select = array_merge($select,$dependencies);
			}
			
			if (is_array($select)) {
				foreach ($select as &$field) {
					if (substr($field,0,1) != "`") $field = "`".$field."`";
				}
				unset($field);
			}
			
			if ($res = $this->select($table,$find,array("select"=>$select))) {
				$this->oldData = $o = $res->fetch();
				
				$options["skipUpdateFunction"] = array();
				foreach ($dependencies as $n) {
					if (!isset($dataOrig[$n])) $options["skipUpdateFunction"][] = $n;
					if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group") {
						$this->groupUpdate($o[$n],$data[$n],$info["fields"][$n],$table,$find);
						continue;
					}
					if (!(isset($data[$n]) || !empty($data[$n]))) $data[$n] = $o[$n];
				}

				if ($getData) {
					foreach ($o as $n=>$v) {
						if (!isset($this->tables[$this->_T($table)][$n]) && !setAndTrue($data,$n)) $data[$n] = $o[$n];
					}
				}
				
				$bsdata = $data;
				if ($this->oldData) {
					foreach ($this->oldData as $n=>$v) {
						if (!isset($bsdata[$n]) && !in_array($n, array("_KEY","_UNIQUE","_USER","_NAME"))) $bsdata[$n] = $v;
					}

					if (isset($info["broadSearchField"])) $data[$info["broadSearchField"]] = $this->getBroadSearchField(array_merge($o,$bsdata),$info);
				}
			}
		}
		
		
		
		$query = "UPDATE ".$this->realTable($table,$options)." SET ".$this->arrayToSql($table,$data,false,$options)." WHERE ".$this->where($table,$find," AND ",false,false,setAndTrue($options,"ignoreExtraWhere"));
		
		if (isset($options["returnQuery"]) && $options["returnQuery"]) return $query;
		
		$this->query($query);
		
		if (isset($info["historyField"]) && isset($data[$info["historyField"]])) unset($data[$info["historyField"]]);
		if (isset($info["broadSearchField"]) && isset($data[$info["broadSearchField"]])) unset($data[$info["broadSearchField"]]);
		
		$this->dispatchAffected($table,$data,$find,false,$options);
	
		$this->oldData = null;
	}
	
	function addHistory($old,&$new,$info) {
		if (isset($new[$info["historyField"]])) unset($new[$info["historyField"]]);
		
		$history = isset($old[$info["historyField"]]) ? $this->decodeHistory($old[$info["historyField"]],true) : '';
		if (isset($old[$info["historyField"]])) unset($old[$info["historyField"]]);
		
		
		$out = array("__TS"=>time(),"__UID"=>$this->getPID());
		$change = false;
		foreach ($new as $n=>$v) {
			if ($n == "broadSearch") continue;
			if (!isset($old[$n]) || $old[$n] != $new[$n]) {
				$change = true;
				switch (isset($info["fields"][$n]["type"]) ? $info["fields"][$n]["type"] : "") {
					case "image":
						$out[$n] = array_shift(explode(";data",$v));
						break;
						
					default:
						if (is_array($v)) break;
						if (!is_string($v)) $v = (string)$v;
						if (substr($v,0,1) == "!") $v = "!".$v;
						if (isset($old[$n]) && $old[$n] !== "" && substr($v, 0, strlen($old[$n])) == $old[$n]) $v = "!APPENDED:".substr($v,strlen($old[$n]));
						if (strlen($v) > 1024) $v = "!LARGE:".md5($v);
						$out[$n] = $v;
						break;
				}
			}
		}
		if (!$change) return;
		
		$history .= json_encode($out)."\n";
		$new[$info["historyField"]] = strlen($history) > 65536 ? "z".gzcompress($history,6) : "u".$history;
	}
	
	function decodeHistory($hist, $noJSON = false) {
		if ($hist == '') return $noJSON ? "" : array();
		if (substr($hist,0,1) == "u") {
			$hist = substr($hist,1);
		} else {
			$hist = gzuncompress(substr($hist,1));
		}
		if ($noJSON) return $hist;
		$rv = array();
		
		$hist = explode("\n",$hist);
		foreach ($hist as $o) {
			$o = json_decode($o,true);
			if (!$o) continue;
			$ts = $o["__TS"]; unset($o["__TS"]);
			$uid = $o["__UID"]; unset($o["__UID"]);
			$rv[] = array(
				"ts"=>$ts,
				"uid"=>$uid,
				"upd"=>$o				
			);
		}
		return $rv;
	}
	
	function getBroadSearchField($nsd,$info) {
		$nsdText = array();
		foreach ($nsd as $n=>$v) {
			$out = false;
			if (isset($info["fields"][$n])) {
				$field = $info["fields"][$n];
				switch (isset($field["type"]) ? $field["type"] : "") {
					case "object":
						if (!setAndTrue($field,"useID")) $out = $v;
						break;
					
					case "date":
						if ($v) $out = date("Ymd\nFY\nFjY",is_string($v) && preg_replace('/[^\d]/','',$v) != $v ? strtotime($v) : $v);
						break;
					
					default:
						$out = $v;
						break;
				} 
			}
			if ($out) {
				$out = explode("\n",$out);
				foreach ($out as $o) {
					$nsdText[] = $n.":".broadSearchify($o);
				}
			}
		}
		return implode(",",$nsdText);
	}
	
	function realTable($table,$options = array()) {
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);

		if (isset($info["table"])) $table = array_pop(explode("/",$info["table"],2));
			
		$tables[] = $table;
		if (isset($options["extraTables"])) {
			if (!is_array($options["extraTables"])) $options["extraTables"] = array($options["extraTables"]);
			$tables = array_merge($tables,$options["extraTables"]);
		}
		foreach ($tables as &$table) {
			//$table = array_shift(explode("/",$table));
			if ($table{0} != "`") $table = "`".$table."`";
		}
		return implode(",",$tables);
	}
		
	function _T($table) {
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
		
		return $info && isset($info["table"]) ? array_pop(explode("/",$info["table"],2)) : $table;
	}
	
	function groupUpdate($oldValue,&$newValue,$fieldInfo,$table,$id) {
		$remove = $append = false;
		$oldValue = trim($oldValue) ? explode(",",$oldValue) : array();
		
		if ($newValue) {
			$nv = explode(",",$newValue);
			$newValueOut = array();
			
			$found = false;
			foreach ($nv as $v) {
				if ($v{0} != "+" && $v{0} != "-") {
					$newValueOut[] = $v;
					$found = true;
				}
			}
			
			if (!$found) $newValueOut = $oldValue;
			
			foreach ($nv as $v) {
				if ($v{0} == "+") {
					$v = substr($v,1);
					if (!in_array($v,$newValueOut)) $newValueOut[] = $v;
				} elseif ($v{0} == "-") {
					$v = substr($v,1);
					if (($pos = array_search($v,$newValueOut)) !== false) unset($newValueOut[$pos]);
				}
			}
		} else $newValueOut = array(); // Remove them all
				
		if (is_array($id)) {
			$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
			$findKey = array_pop(array_keys($id));
			if ($findKey == (isset($info["key"]) ? $info["key"] : "id") || (isset($info["unique"]) && $findKey == $info["unique"])) {
				$id = array_pop(array_values($id));
			} else {
				if ($res = $this->select($table,$id)) {
					$key = (isset($info["key"]) ? $info["key"] : "id");
					$row = $res->fetch_assoc();
					$id = $row[$key];
				} else return; // Not found
			}
		}

		$diff = array_merge(array_diff($newValueOut,$oldValue),array_diff($oldValue,$newValueOut));
				
		if (count($diff)) {
			$ids = array();

			foreach ($diff as $v) {
				if (in_array($v,$oldValue)) { //Group removed
					$this->groupDiff($id,$fieldInfo["ref"],$v,-1);
				} else { // Group added
					$this->groupDiff($id,$fieldInfo["ref"],$v,1);
				}
			}
		}
		$newValue = implode(",",$newValueOut);
	}

	function groupDiff($id,$ref,$name,$change) {
		$nameSafe = safeName($name);
 		if ($res = $this->select("groups",array("ref"=>$ref,"nameSafe"=>$nameSafe))) {
			$group = $res->fetch_assoc();
		} else {
			$group = array(
				"name"=>$name,
				"ref"=>$ref,
				"links"=>0
			);
			$group["id"] = $this->insert("groups",$group);
		}
		
		$group["links"] += $change;
		
		
		if ($change == 1) {
			$this->insert("groupLink",array("groupId"=>$group["id"],"linkedId"=>$id));
		} else {
			$this->delete("groupLink",array("groupId"=>$group["id"],"linkedId"=>$id));
		}			
		
		if ($group["links"] <= 0) {
			$this->delete("groups",array("id"=>$group["id"]));
		} else {
			$this->update("groups",array("links"=>$group["links"]),array("id"=>$group["id"]));
		}
	}

	function delete($table, $find, $options = array()) {
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);

		$this->dispatchAffected($table,array("DELETE"=>true),$find,true,$options);
		
		if (isset($info["fields"])) {
			foreach ($info["fields"] as $n=>$field) {
				if (isset($field["type"]) && $field["type"] == "group") {
					$newFind = $find;
					$newFind[$info["groupField"]] = array("!","");
					if ($res = $this->select($table,$newFind)) {
						while ($row = $res->fetch_assoc()) {
							if (isset($GLOBALS["slConfig"]["sync"]) && in_array("db/".$table,$GLOBALS["slConfig"]["sync"]["tables"])) {
								file_put_contents(SL_DATA_PATH."/ypSync/".safeFile("db/".$table).".upd",time().":".json_encode(array($row,array("DELETE"=>true)))."\n",FILE_APPEND);
							}  
							$blank = "";
							$this->groupUpdate($row[$n],$blank,$field,$table,$row["id"]);
						}
					}
				}
			}
		}
		
		$res = $this->query("DELETE FROM ".$this->realTable($table)." WHERE ".$this->where($table,$find).(isset($options["limit"])?" LIMIT ".$options["limit"]:""));
		if ($this->conn->affected_rows && !$this->noUpdateEvents) $GLOBALS["slSession"]->tableUpdate("db/".$table);
	}

	function dispatchAffected($table,$data,$find,$force = false,$options = array()) {
		if ($this->noUpdateEvents || isset($options["noUpdateEvents"])) return;
		
		$delete = isset($data["DELETE"]);
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
		
		$options["select"] = "*";
		//if (isset($info["userField"])) $options["select"][] = $info["userField"];
		
		if ($force || $this->conn->affected_rows) {
			
			//$options["dbg"] = 1;
			if ($res = $this->select($table,$find,$options)) {
				while ($row = $res->fetch()) {	
					
					if (!$delete && is_array($data)) $row = array_merge($row,$data);
					$row["_KEY"] = $row[$this->tableKeys[$this->_T($table)]];
					unset($row[$this->tableKeys[$this->_T($table)]]);
					
					foreach ($row as $n=>$v) {
						if (isset($info["fields"][$n]) && isset($info["fields"][$n]["type"])) {
							switch ($info["fields"][$n]["type"]) {
								case "password":
									unset($row[$n]);
									break;
							}
						}
					}

					if (isset($GLOBALS["slConfig"]["sync"]) && is_array($GLOBALS["slConfig"]["sync"]["tables"]) && in_array("db/".$table,$GLOBALS["slConfig"]["sync"]["tables"]) && !isset($data["_NO_SYNC"])) {
						file_put_contents(SL_DATA_PATH."/ypSync/".safeFile("db/".$table).".upd",time().":".json_encode(array($row,$data))."\n",FILE_APPEND);
					}  
					
					if ($delete) {
						$changed = array("DELETE"=>1);
					} else {
						$changed = array();
						foreach ($data as $n=>$v) {
							if (!isset($this->oldData[$n]) || $this->oldData[$n] != $v) $changed[$n] = $v;
						}
					}
					$changed["_KEY"] = $row["_KEY"];
					
					$GLOBALS["slCore"]->nonUserSpecificDispatch("change-db/".$table."/".$row["_KEY"],$changed,isset($info["userField"])?$row[$info["userField"]]:false);
					
					$info['changed'] = $changed;
					$this->processCustomUpdate($row,$this->oldData,$info);

				}
			}
		}
		
		$GLOBALS["slCore"]->db->updated("db/".$table);
	}
	
	function getTableKey($table) {
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);

		if (isset($info["table"])) $table = array_pop(explode("/",$info["table"]));
		
		if (!isset($this->tables[$table])) return $this->error("Table `".$table."` does not exist.");
		if (!isset($this->tableKeys[$table])) return $this->error("Table `".$table."` has no key.");
		
		return $this->tableKeys[$table];
	}
	
	function selectByKey($table, $key, $options = false) {
		$keyField = $this->getTableKey($table);
		return $this->select($table,array($keyField=>$key), $options);
	}
	
	function select($table, $find = false, $options = false) {
		if (isset($options["dbg"])) {
			echo "EXPLAIN SELECT ".$this->selectFields($table,$options)." FROM ".$this->realTable($table,$options)." WHERE ".$this->where($table,$find," AND ",false,false,setAndTrue($options,"ignoreExtraWhere")).(isset($options["groupby"])?" GROUP BY ".$options["groupby"]:"").(isset($options["orderby"])?" ORDER BY ".$options["orderby"]:"").(isset($options["limit"])?" LIMIT ".$options["limit"]:"");
			exit();
		}
		$query = "SELECT ".$this->selectFields($table,$options)." FROM ".$this->realTable($table,$options)." WHERE ".$this->where($table,$find," AND ",false,false,setAndTrue($options,"ignoreExtraWhere")).(isset($options["groupby"])?" GROUP BY ".$options["groupby"]:"").(isset($options["orderby"])?" ORDER BY ".$options["orderby"]:"").(isset($options["limit"])?" LIMIT ".$options["limit"]:"");
				
		//$res = $this->query((setAndTrue($options,"cache")?:"/*" . MYSQLND_QC_ENABLE_SWITCH . "*/").$query, $table, $options);
		$res = $this->query($query, $table, $options);
		
		$this->table = $table;
		$this->res = $res;
		if ($res && $res->num_rows) return $res;
	
		return false;
	}
	
	function selectFields($table,$options) {
		$this->nameFromDisplayName = false;
		if (isset($options["select"])) {
			$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
			if (!is_array($options["select"])) $options["select"] = explode(",",$options["select"]);
			if (($pos = array_search("_EXTRA",$options["select"])) !== false) {
				$options["select"][$pos] = "`data`";
			}
			
			if (($pos = array_search("_KEY",$options["select"])) !== false) {
				if ($key = $this->getTableKey($table)) {
					$options["select"][$pos] = "`".$key."` AS '_KEY'";
				} elseif (isset($info["unique"])) {
					$options["select"][$pos] = "`".$info["unique"]."` AS '_KEY'";
				} else {
					unset($options["select"][$pos]);
				}
			}
			if (($pos = array_search("_UNIQUE",$options["select"])) !== false) {
				if (isset($info["unique"])) {
					$options["select"][$pos] = "`".$info["unique"]."` AS '_UNIQUE'";
				} elseif ($key = $this->getTableKey($table)) {
					$options["select"][$pos] = "`".$key."` AS '_UNIQUE'";
				} else {
					unset($options["select"][$pos]);
				}
			}
			
			if (($pos = array_search("_NAME",$options["select"])) !== false) {
				if (isset($info["nameField"])) {
					$options["select"][$pos] = "`".$info["nameField"]."` AS '_NAME'";
				} elseif (isset($info["displayName"])) {
					unset($options["select"][$pos]);
					$n = implode(" ",$info["displayName"]);
					$matches = array();
					$this->nameFromDisplayName = array("+"=>".");
					if (preg_match_all("/item\.[\w\d]+/",$n,$matches)) {
						foreach ($matches[0] as $item) {
							$field = str_replace('item.','',$item);
							$this->nameFromDisplayName[$item] = '$item["'.$field.'"]';
							if (!in_array($field,$options["select"])) $options["select"][] = $field;
						}
					}
					} else {
					unset($options["select"][$pos]);
				}
			}

			$extra = false;
			for ($i = 0; $i < count($options["select"]); $i++) {
				if (isset($options["select"][$i])) {
					$field = &$options["select"][$i];
					if (preg_match('/^[\w\d]+$/',$field)) {
						if (!isset($this->tables[$table][$field])) {				
							$extra = true;
							array_splice($options["select"], $i, 1);
							$i--;
						} else $field = '`'.$field.'`';
					}
				}
			}

			if ($extra && $this->tables[$table]["data"]) $options["select"][] = "`data`";

			if (count($options["select"])) return implode(",",$options["select"]);
		}
		return "*";
	}	
	
	function fetch($table=null,$res=null,$options = false) {
		if ($table === null) $table = $this->table;
		if ($res === null) $res = $this->res;

		return $this->sqlToArray($table,$res,$options);
	}
	
	function fetchAll($table=null,$res=null,$options = false) {
		$rv = array();
		while ($row = $this->fetch($table,$res,$options)) {
			$rv[] = $row;
		}
		return $rv;
	}
	
	function where($table,$find,$delim = " AND ",$noExtra = false,$insert = false, $ignoreExtraWhere = false) {
		$res = $this->__where($table, $find, $delim, $noExtra, $insert, $ignoreExtraWhere);
		return $res === "" ? "1" : $res;
	}
	
	private function __where($table, $find, $delim, $noExtra, $insert, $ignoreExtraWhere) {
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
		
		$realTable = isset($info["table"]) ? array_pop(explode("/",$info["table"])) : $table;
		
		if (is_string($find)) return $noExtra ? $find : $this->extraWhere($table,$find,$info,false,$ignoreExtraWhere);
		if (!$find) return $noExtra ? "1" : $this->extraWhere($table,"1",$info,false,$ignoreExtraWhere);
		
		$noUser = false;
		if (isset($find["_NO_USER"])) {
			$noUser = true;
			unset($find["_NO_USER"]);
		}
		$rv = array();
		
		if (isset($find["PREP"])) {
			foreach ($find["PREP"][1] as $n=>$v) {
				$find["PREP"][0] = str_replace('['.$n.']', $this->safe($v), $find["PREP"][0]);
			}
			return $find["PREP"][0];
		}
		
		if (isset($find["_RAW_QUERY"])) {
			$rv[] = $find["_RAW_QUERY"];
			unset($find["_RAW_QUERY"]);
		}
		
		if (is_numeric($find)) $find = array("_KEY"=>$find);
		
		foreach ($find as $n=>$v) {
			switch ("".$n) {					
				case "_KEY":
					$n = (int)preg_replace('/[^\d]+/','',$v) || !isset($info["unique"]) ? $this->getTableKey($table) : $info["unique"];
					break;
				
				case "_UNIQUE":
					$n = $info["unique"];
					break;
				
				case "_USER":
					$n = $info["userField"];
					break;
					
				case "_NAME":
					if (isset($info["nameSafeField"])) {
						$n = $info["nameSafeField"];
						if (is_array($v)) {
							$v[1] = safeName($v[1]);
						} elseif (is_string($v) && $v) {
							$v = safeName($v{0} == "+" ? substr($v,1) : $v);
						}
					} else {
						$n = isset($info["nameField"]) ? $info["nameField"] : "name";
					}
						
					break;
			}
						
			if (isset($info["unique"]) && $n == $info["unique"] && isset($this->fieldHash[$realTable][$n]) && !isset($find[$n."Hash"]) && !is_array($v)) {
				$v = array("_lit","(`$n`=".$this->__safe($v)." AND `".$n."Hash`=0x".$this->hash($v,$this->fieldHash[$realTable][$n]).")");
			}
			
			if (is_array($v)) {
				if (is_numeric($n)) {			
					$m = array();
					foreach ($v as $sn=>$sv) {
						if (is_array($sv)) {
							if ($av = $this->__where($table, array($sn=>$sv))) {
								$m[] = $av;
							}
						} else {
							$m[] = "`$sn`=".$this->__safe($sv);
						}
					}
					$rv[] = count($m) == 1 ? "1" : "(".implode(" OR ",$m).")";
				} elseif (isset($v[0]) && is_string($v[0]) && isset($v[1])) {
					switch (strtolower($v[0])) {
						case "_lit":
							$rv[] = $v[1];
							break;
						
						case "_hex":
							$rv[] = "`$n`=0x".$v[1];
							break;
								
						case "!": case "!=":
							$rv[] = "`$n`!=".(count($v) == 3 ? $v[1] : $this->__safe($v[1]));
							break;
							
						case "+=":
							$rv[] = $insert ? "`$n`=".(float)$v[1] : "`$n`=(`$n`+".(float)$v[1].")";
							break;
						
						case "-=":
							$rv[] = $insert ? "`$n`=-".(float)$v[1] : "`$n`=(`$n`-".(float)$v[1].")";
							break;
						
						case "*=":
							$rv[] = $insert ? "`$n`=0" : "`$n`=(`$n`*".(float)$v[1].")";
							break;
							
						case "/=":
							$rv[] = $insert ? "`$n`=0" : "`$n`=(`$n`/".(float)$v[1].")";
							break;		
						
						case "|=":
							$rv[] = $insert ? "`$n`=".(int)$v[1] : "`$n`=(`$n`|".(int)$v[1].")";
							break;	
							
						case "&=":
							$rv[] = $insert ? "`$n`=0" : "`$n`=(`$n`&".(int)$v[1].")";
							break;	
							
						case "=":
						case "<": case "<=":
						case ">": case ">=":
							$rv[] = "`".$n."`".$v[0].(count($v) == 3 ? $v[1] : $this->__safe($v[1]));
							break;
						
						case "range":
							$o = array();
							if ($v[1] !== null) $o[] = "`$n`>=".(int)$v[1];
							if ($v[2] !== null) $o[] = "`$n`<".(int)$v[2];
							if (count($o)) $rv[] = "(".implode(" AND ",$o).")";
							break;
						
						case "within":
							$rv[] = "ABS(`$n` - ".(float)$v[2].") <= ".(float)$v[1];
							break;
							
						case "contains":
							$rv[] = "`$n` LIKE '%".(count($v) == 3 ? $v[1] : $this->__safe($v[1],true))."%'";
							break;
							
						case "beginswith":
							$rv[] = "`$n` LIKE '".$this->__safe($v[1],true)."%'";
							break;
							
						case "lastwordbeginswith":
							$rv[] = "SUBSTRING_INDEX(`$n`, ' ', -1) LIKE '".$this->__safe($v[1],true)."%'";
							break;							
								
						case "endswith":
							$rv[] = "`$n` LIKE '".$this->__safe($v[1],true)."%'";
							break;
							
						case "like":
							$rv[] = "`$n` LIKE ".(count($v) == 3 ? $v[1] : $this->__safe($v[1]));
							break;
						
						case "bmatch":
							$rv[] = "`".$info["broadSearchField"]."` LIKE '%".$n.":".$this->__safe(broadSearchify($v[1]),true)."%'";
							break;

						case "any":
							$m = array();
							for ($i = 1; $i < count($v); $i++) {
								$m[] = "`$n`=".$this->__safe($v[$i]);
							}
							$rv[] = count($m) == 1 ? $m[0] : "(".implode(" OR ",$m).")";
							break;
						
						case "all":
							$m = array();
							for ($i = 1; $i < count($v); $i++) {
								if (is_array($v[$i])) {
									if ($av = $this->__where($table, array($n=>$v[$i]))) {
										$m[] = $av;
									}
								} else {
									$m[] = "`$n`=".$this->__safe($v[$i]);
								}
							}
							$rv[] = count($m) == 1 ? $m[0] : "(".implode(" AND ",$m).")";
							break;
							
						case "likeany":
							$m = array();
							for ($i = 1; $i < count($v); $i++) {
								$m[] = "`$n` LIKE ".$this->__safe($v[$i]);
							}
							$rv[] = count($m) == 1 ? $m[0] : "(".implode(" OR ",$m).")";
							break;
						
						case "substr":
							$str = $v[1];
							$pos = isset($v[2]) ? (int)$v[2] : 0;
							$len = isset($v[3]) ? (int)$v[3] : strlen($str);
							$break[] = "`$n`=SUBSTR(".$this->__safe($str).", ".$pos.", ".$len.")";
							break;
							
						case "soundslike":
							$rv[] = "SOUNDEX(`$n`)=SOUNDEX(".$this->__safe($v[1]).")";
							break;
							
						case "containsany":
							$m = array();
							for ($i = 1; $i < count($v); $i++) {
								$m[] = "`$n` LIKE '%".$this->__safe($v[$i],true)."%'";
							}
							$rv[] = count($m) == 1 ? $m[0] : "(".implode(" OR ",$m).")";
							break;
						
						case "weekfromunixts":
						case "weekdayfromunixts":
						case "weekofyearfromunixts":
						case "yearfromunixts":
						case "yearweekfromunixts":
						case "week": case "weekday": case "weekofyear":
						case "year": case "yearweek":
							$p1 = $v[0];
							if (substr($vlc,-10) == "fromunixts") {
								$p1 = substr(strtoupper($p1),0,-10)."(FROM_UNIXTIME(`$n`))";
							} else {
								$p1 = strtoupper($p1)."(`$n`)";
							}
							$rv[] = $p1."=".$this->__safe($v[1]);
							break;
					}
				} else {	
					$rv[] = "`$n`=".$this->__safe("!JSON:".json_encode($v));
				}
			} else $rv[] = "`$n`=".$this->__safe($v);
		}
		return $noExtra ? implode($delim,$rv) : $this->extraWhere($table,implode($delim,$rv),$info,$noUser,$ignoreExtraWhere);
	}
	
	function extraWhere($table,$where,$info,$noUser = false, $ignoreExtraWhere = false) {
		$realTable = isset($info["table"]) ? array_pop(explode("/",$info["table"])) : $table;
		
		$ew = array();
		if (isset($info["extraWhere"]) && !$ignoreExtraWhere) {
			if (($res = eval("return ".$info["extraWhere"].";")) !== false) {
				$ew[] = $res;
			}
		}
		
		if (isset($info["conform"])) {
			foreach ($info["conform"] as $n=>$v) {
				$ew[] = "`".$n."`='".$this->__safe($v)."'";
			}
		}
		
		if (!$noUser && $info && isset($info["userField"]) && !$GLOBALS["slCronSession"] && isset($GLOBALS["slSession"])) {
			$pid = $this->getPID();
			$ew[] = "(`".$realTable."`.`".$info["userField"]."`=".$pid.($info["table"]=="db/user"?" OR `".$realTable."`.`id`=".$pid:"").")";
		}
		return count($ew) ? implode(" AND ", $ew)." AND (".$where.")" : $where;
	}
	
	function getPID() {
		return isset($GLOBALS["FORCE_USER"]) ? $GLOBALS["FORCE_USER"] : (int)$GLOBALS["slSession"]->get("parentID");
	}
	
	function arrayToSql($table,&$array,$insert = false, $options = array()) {
		$data = array();
		if (isset($array["data"])) {
			$data = $array["data"];
			unset($array["data"]);
		}
		
		$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
		
		if ($info && isset($info["nameField"]) && isset($array["_NAME"])) {
			$array[$info["nameField"]] = $array["_NAME"];
			unset($array["_NAME"]);
		}
		

		if ($info && isset($info["fields"])) {
			foreach ($info["fields"] as $n=>$field) {
				if (isset($array[$n])) {
					if (isset($field["readOnly"]) && $field["readOnly"]) {
						unset($array[$n]);
					} elseif (isset($field["useID"]) && $field["useID"] && preg_replace('/[\d]+/','',$array[$n]) != '') { // Use ID
						if ($res = $GLOBALS["slCore"]->db->select($field["ref"],array("_NAME"=>array_pop(explode(";",$array[$n]))),array("select"=>"_KEY"))) {
							$row = $res->fetch_assoc();
							$array[$n] = $row["_KEY"];
						}
					}
				}
			}
		}		
		
		if ($insert && $info && isset($info["userField"]) && !isset($array[$info["userField"]]) && isset($GLOBALS["slSession"]) && !$GLOBALS["slCronSession"]) $array[$info["userField"]] = $this->getPID();
		
		$tData = $this->tables[$this->_T($table)];

		$rv = array();
		
		$suf = setAndTrue($options,"skipUpdateFunction") ? $options["skipUpdateFunction"] : array();

		foreach ($array as $n=>$v) {
			if (isset($info["fields"][$n])) {
				if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group" && strlen($v) && ($v{0} == "+" || $v{0} == "-")) $array[$n] = substr($v,1);
			
				$field = $info["fields"][$n];

				if (!isset($options["noUpdateFunction"]) && !$insert && !in_array($n, $suf)) $this->processCustomFieldUpdate($info,$n,$array);
			}
		}
		
		if (!$insert && isset($info["historyField"])) $this->addHistory($this->oldData,$array,$info);
		
		
		if ($insert) { //Handle missing default values
			foreach ($tData as $n=>$o) {
				if (!isset($array[$n]) && !$o["Default"]) $array[$n] = '';
			}
		}
		
		if (isset($array["_KEY"])) unset($array["_KEY"]);
		if (isset($array["_UNIQUE"])) unset($array["_UNIQUE"]);

		foreach ($array as $n=>$v) {
			if ($n == "_NO_SYNC" || isset($rv[$n])) continue;
			
			if (isset($this->fieldHash[$table][$n])) {
				$rv[$n."Hash"] = array("_HEX",$this->hash($v,$this->fieldHash[$table][$n]));
			}
			
			$field = isset($info["fields"][$n]) ? $info["fields"][$n] : array();
			
			if (isset($field["saveUpdateTS"])) {
				$rv[$field["saveUpdateTS"]] = time();
			}
				
			if (isset($tData[$n])) {
				$type = explode(" ",preg_replace('/\(.*?\)/','',$tData[$n]["Type"]));
				
					
				switch ($type[0]) {
					case "tinyint": case "smallint":  case "mediumint":
					case "integer": case "int": case "bigint":
						$rv[$n] = (int)$v;
						break;
					
					case "float": case "double":
						$rv[$n] = (float)$v;
						break;
					
					//TODO: date and time types
					
					default:
						$rv[$n] = $v;
						break;
				}
				
			} else {
				$data[$n] = $v;
			}
		}
		
		if ($insert) {
			$createdField = isset($info["createdField"]) ? $info["createdField"] : "created";
			$createdByField = isset($info["createdByField"]) ? $info["createdByField"] : "createdBy";
			
			if (isset($tData[$createdField])) $rv[$createdField] = time();
			if (isset($tData[$createdByField])) $rv[$createdByField] = $this->getPID();
		}
		$updatedField = isset($info["updatedField"]) ? $info["updatedField"] : "updated";
		if (isset($tData[$updatedField])) $rv[$updatedField] = time();
		
		if ($data && isset($tData["data"])) $rv["data"] = json_encode($data);
		
		return $this->where($table,$rv,",",true,$insert);
	}
	
	function hashAll() {
		foreach ($this->fieldHash as $table=>$fields) {
			$keyField = $this->getTableKey($table);
			foreach ($fields as $field=>$size) {
				if ($res = $this->select($table,array($field."Hash"=>0))) {
					while ($row = $res->fetch_assoc()) {
						$this->query("UPDATE `$table` SET `".$field."Hash`=0x".$this->hash($row[$field],$size)." WHERE `$keyField`=".$row[$keyField]." LIMIT 1");
					}
				}
			}
		}
	}
	
	function hash($v,$size) {
		$rv = substr(md5($v),0,$size * 2);
		return $rv == str_repeat("00",$size) ? str_repeat("0",$size * 2 - 1)."1" : $rv;
	}
	
	function sqlToArray($table,$res,$options = false) {
		if ($item = $res->fetch_assoc()) {			
			if (!$options) $options = array();
			
			foreach ($item as $n=>&$v) {
				if (substr($v,0,6) == "!JSON:") {
					$v = json_decode(substr($v,6),true);
				}
			}
			unset($v);
			
			$info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
	
			if (isset($item["data"])) {
				$data = json_decode($item["data"],true);
				unset($item["data"]);
				
				if (!$data) {
					$data = array();
				} elseif (isset($options["select"]) && $options["select"] != "*") {
					if (!is_array($options["select"])) $options["select"] = explode(",",$options["select"]);		
					if (!(in_array("`data`",$options["select"]) || in_array("data",$options["select"]))) {
						foreach ($data as $n=>$v) {
							if (!in_array($n,$options["select"])) unset($data[$n]);
						}
					}
				}
				$item = array_merge($data,$item);
			}
			
			
			
			$key = $table ? $this->getTableKey($table) : "id";
			if (isset($item[$key])) $item["_KEY"] = $item[$key];
			if (isset($info["unique"]) && isset($item[$info["unique"]])) $item["_UNIQUE"] = $item[$info["unique"]];
			
			if (isset($info["fields"])) {
				foreach ($info["fields"] as $n=>$field) {
					if (isset($field["eval"])) {
						$item[$n] = eval('return '.$field["eval"].';');
					}
					
					if (isset($field["func"])) {
						$item[$n] = call_user_func($field["func"], $item, $info);
					}
					if (isset($field["writeOnly"]) && $field["writeOnly"] && !setAndTrue($options,"ignoreWriteOnly")) {
						unset($item[$n]);
					}
				}
			}
			
			if ($this->nameFromDisplayName) {
				$this->nameFromDisplayName["sl."] = "";
				foreach ($info["displayName"] as $n) {
					$v = false;
					eval('$v = '.str_replace(array_keys($this->nameFromDisplayName),array_values($this->nameFromDisplayName),$n).";");
					if (trim($v)) {
						$item["_NAME"] = $v;
						break;
					}
				}
				if (!isset($item["_NAME"])) $item["_NAME"] = "?";
			}
			
			if (isset($info["displayImage"])) {
				$rep = array("+"=>".","sl."=>"");
				foreach ($info["displayImage"] as $n) {
					$n = preg_replace('/item\.([\w\d]+)/','(isset(\$item["$1"])?\$item["$1"]:null)',$n);
					$n = str_replace(array_keys($rep),array_values($rep),$n);
					eval('$v = '.$n.";");
					if (trim($v)) {
						$item["_IMAGE"] = webPath($v);
						break;
					}
				}
			} else {
				if (is_array($info["fields"])) {
					foreach ($info["fields"] as $n=>$field) {
						if (setAndTrue($field,"useAsIcon") && isset($item[$n])) {
							switch (isset($field["type"]) ? $field["type"] : "") {
								case "image":
									if (!isset($item[$n])) break;
									$im = explode(";",$item[$n]);
									if (isset($im[5]) && isset($im[6])) $item["_IMAGE"] = "!".$im[5].";".$im[6];
									break;
								
								default:
									if (!isset($item[$n])) break;
									$item["_IMAGE"] = $item[$n];
									break;
							}
							break;
						}
					}
				}
			}
	
			if (setAndTrue($options,"history") && isset($info["historyField"]) && isset($item[$info["historyField"]])) { 
				$item[$info["historyField"]] = $this->decodeHistory($item[$info["historyField"]]);
			}	
			
			return $item;
		}
		return null;
	}
	
	function getSchema() {
		return $this->tables;
	}
	
	function tableRefresh() {
		require_once(SL_INCLUDE_PATH.'/class.slCache.php');
		
		if ($res  = getQuickCache('sl.db.mysql.tables')) {
			$this->tables = $res["tables"];
			$this->tableKeys = $res["tableKeys"];
			$this->fieldHash = $res["fieldHash"];
		} else {
			$this->tables = array();
			$this->tableKeys = array();
			$this->fieldHash = array();
			
			$r = $this->query("SHOW TABLES");

			if ($r) {
				while ($d = $r->fetch_row()) {
					$describe = $this->query('DESCRIBE `'.$d[0].'`');
					
					if ($describe) {
						$tmp = array();
						while ($row = $describe->fetch_assoc()) {
							$field = $row["Field"];
							
							if (substr($field,-4) == "Hash") {
								if (!isset($this->fieldHash[$d[0]])) $this->fieldHash[$d[0]] = array();
								$this->fieldHash[$d[0]][substr($field,0,-4)] = $this->fieldSize[array_shift(explode("(",$row["Type"]))];
							}
							
							unset($row["Field"]);
							$tmp[$field] = $row;
							if ($row["Key"] == "PRI") {
								$this->tableKeys[$d[0]] = $field;
							}
						}
						$this->tables[$d[0]] = $tmp;
					}
				}
			}
			setQuickCache('sl.db.mysql.tables',array(
				"tables"=>$this->tables,
				"tableKeys"=>$this->tableKeys,
				"fieldHash"=>$this->fieldHash
			));
		}
	}
	
	public function __safe($v, $noQuotes = false) {
		return self::safe($v, $noQuotes, $this->conn);
	}
	
	public static function safe($v, $noQuotes = false, $conn = false) {
		if ($conn === false) $conn = $GLOBALS["slDBConnections"]["db"]->conn;
		$q = $noQuotes ? "" : "'";
		if (is_int($v)) {
			return $v;
		} else {
			if (is_array($v)) {
				return $q.json_encode($v).$q;
			} else {
				return $q.$conn->escape_string($v).$q;
			}
		}
	}
	
	public static function createTableQuery($table,$schema) {
		return "CREATE TABLE `".$table."` (\n\t".self::tableSchema($schema)."\n);";
	}
	
	public static function tableSchema($schema, $includeIndexes = true) {
		$rv = array();
		$keys = array();
		foreach ($schema as $field=>$o) {
			$rv[] = "`".$field."` ".$o["Type"].($o["Null"] == "NO" ? " NOT NULL":"").self::translateExtra($o["Extra"]);
			if (setAndTrue($o,"Key")) {
				$keys[$o["Key"]] = $field;
			}
		}
		if ($includeIndexes) {
			foreach ($keys as $type=>$field) {
				$rv[] = self::keyTrans($type)." ".($type=="PRI"?"":"`".$field."` ")."(`".$field."`)";
			}
		}
		return implode(",\n\t",$rv);
	}
	
	public static function keyTrans($type) {
		$t = array("PRI"=>"PRIMARY KEY","UNI"=>"UNIQUE KEY","MUL"=>"KEY");
		return $t[$type];
	}
	
	public static function changeField($table, $field, $schema) {
		return "ALTER TABLE  `".$table."` CHANGE  `".$field."`  ".self::tableSchema(array($field=>$schema),false).";";
	}	
	
	public static function translateExtra($extra) {
		switch ($extra) {
			default:
				return " ".strtoupper($extra);
		}
		return "";
	}
}

class slMySQLi extends \mysqli {
	private $lastResult;
	private $slMysql;
	
	public function __construct($slMysql, $host = false, $username = false, $passwd = false, $dbname = false, $port = false, $socket = false) {
		if ($host === false) $host = ini_get("mysqli.default_host");
		if ($username === false) $username = ini_get("mysqli.default_user");
		if ($passwd === false) $passwd = ini_get("mysqli.default_pw");
		if ($dbname === false) $dbname = "";
		if ($port === false) $port = ini_get("mysqli.default_port");
		if ($socket === false) $socket = ini_get("mysqli.default_socket");

		$this->slMysql = $slMysql;
		
		parent::__construct($host, $username, $passwd, $dbname, $port, $socket);
	}
	
	public function query($query, $resultmode = null, $table = false, $options = false) {
		if (!$options) $options = array();
		$this->lastResult = parent::query($query, $resultmode);
		return is_bool($this->lastResult) ? $this->lastResult : new slMysqlResult($this->lastResult, $this->slMysql, $table, $options);
	}
}


class slMysqlResult {
	private $result;
	private $slMysql;
	private $table;
	private $options;
	private $info;
	
	public function __construct(mysqli_result $result, $slMysql, $table, $options = false) {
		$this->result = $result;
		$this->slMysql = $slMysql;
		$this->table = $table;
		$this->options = $options;
		
		if ($GLOBALS["slCore"]->db) $this->info = $GLOBALS["slCore"]->db->getTableInfo("db/".$table);
	}
		
	public function __call($name, $arguments) {
		return call_user_func_array(array($this->result, $name), $arguments);
	}
	
	public function __set($name, $value) {
		$this->result->$name = $value;
	}

	public function __get($name) {
		return $this->result->$name;
	}
	
	public function fetch($options = false) {
		return $this->slMysql->fetch($this->table,$this->result,$options?$options:$this->options);
	}
	
	public function fetchAsText($includeRaw = false) {
		require_once(SL_INCLUDE_PATH."/class.slValue.php");
		$row = $this->fetch();
		if ($row) {
			$raw = array();
			foreach ($row as $n=>&$v) {
				if ($includeRaw) $raw[$n.".raw"] = $v;
				switch (isset($this->info["fields"][$n]["type"]) ? $this->info["fields"][$n]["type"] : "text") {
					case "text": case "number": break;
					
					default:
						$v = valueToString($v,$this->info["fields"][$n],$n);
						break;
				}
			}
			if ($includeRaw) $row = array_merge($row,$raw);
		}
		return $row;
	}
	
	public function fetchAll() {
		return $this->slMysql->fetchAll($this->table,$this->result,$this->options);
	}
}
