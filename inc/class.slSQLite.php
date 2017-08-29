<?php

class slSQLite extends slClass {
	public $conn = false;
	public $showQuery = false;
	private $file;
	private $nameFromDisplayName = false;
	private $fieldHash = array();
	private	$tables = array();
	private	$tableKeys = array();
	private $inserted = false;
	private $fieldSize = array("tinyint"=>1,"smallint"=>2,"mediumint"=>3,"int"=>4,"integer"=>4,"bigint"=>8);
	private $noUpdateEvents = false;
	private $oldData = null;
	
	function __construct() {
		
	}
	
	function connect($settings) {	
		if (!isset($settings["file"])) return $this->error('$settings["dbFile"] not defined.');
		try {
			$this->file = str_replace(".db","",array_pop(explode("/",$settings["file"])));
			$this->conn = new SQLite3(
				$settings["file"],
				isset($settings["flags"]) ? $settings["flags"] : null,
				isset($settings["encryptionKey"]) ? $settings["encryptionKey"] : null
			);
			$this->tableRefresh();
			return true;
		} catch (Exception $e) {
			return $this->error($e->getMessage());
		}
	}	

	function getSubType() {
		return $this->file;
	}
	
	function selectDb($settings) {
		return $this->connect($settings);
	}
	
	function query($query) {
		if ($this->showQuery) {
			echo $query."\n";
			$this->showQuery = false;
		}
		
		try {
			$res = $this->conn->query($query);
		} catch (Exception $e) {
			return $this->error($e->getMessage()."\n".$query);
		}
		
		if (!$res && $this->conn->lastErrorCode()) {
			$this->error($this->conn->lastErrorMsg()."\n".$query);
		}
		return $res;
	}
	
	
	function insert($table,$data,$find = false, $options = array("select"=>"id")) {
		$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
		
		if (isset($info["insertFunction"]) && !(is_array($options) && isset($options["direct"]))) {
			return $info["insertFunction"]($data, $find, $options);
		}

		$dataOrig = $data;
		
		foreach ($data as $n=>$v) {
			if (isset($info["fields"][$n]) && isset($info["fields"][$n]["dependency"]) && !isset($data[$info["fields"][$n]["dependency"]])) {
				$data[$info["fields"][$n]["dependency"]] = "";
			}
		}
		
		$insertQuery = $this->arrayToSql($table,$data,true,$options);
				
		if (isset($info["unique"]) && isset($data[$info["unique"]]) && trim($data[$info["unique"]]) != "") {
			if ($res = $this->select($table,array($info["unique"]=>$data[$info["unique"]]),array("select"=>($key = $this->getTableKey($table)),"limit"=>"1"))) {
				$o = $this->fetch($table,$res);
				if (isset($data[$key])) unset($data[$key]);
				$this->update($table,$data,array($key=>$o[$key]),$options);
				return isset($options["returnUnique"]) && $options["returnUnique"] ? $this->getUnique($table,$o[$key]) : (int)$o[$key];
			}
		}
		
		$this->inserted = false;
		if ($find) {
			if ($res = $this->select($table,$find,$options)) {
				$data = $this->fetch($table,$res);
				return (int)$data[$this->getTableKey($table)];
			}
		}
	
		$this->inserted = true;

		//$GLOBALS["slCore"]->beginMeasure();

		$this->query("INSERT INTO `$table` ".$insertQuery);
		
		//$GLOBALS["slCore"]->endMeasure("INSERT");
		
		foreach ($dataOrig as $n=>$v) {
			if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group") {
				$this->groupUpdate("",$v,$info["fields"][$n],$table,$this->conn->lastInsertRowID());
			}
		}
		
		$id = $this->conn->lastInsertRowID();
		
		if (isset($info["updateFunction"])) {
			$data["_KEY"] = $this->conn->lastInsertRowID();
			$data["_INSERTED"] = true;
			$info["updateFunction"]($data,$info);
		}
		
		//$GLOBALS["slCore"]->nonUserSpecificDispatch("change-db/".$table."/".$this->conn->lastInsertRowID(),$data);
		//TODO: if (!$this->noUpdateEvents && isset($GLOBALS["slSession"])) $GLOBALS["slSession"]->tableUpdate($this->file."/".$table);
		return isset($options["returnUnique"]) && $options["returnUnique"] ? $this->getUnique($table,$id) : $id;
		
	}
	
	function preventUpdateEvents($table) {
		$this->noUpdateEvents = true;
	}
	
	function wasInserted() {
		return !!$this->inserted;
	}
	
	function getUnique($table,$key) {
		if ($res = $this->selectByKey($table, $key, array("select"=>"_UNIQUE"))) {
			$o = $res->fetchArray(SQLITE3_ASSOC);
			return $o["_UNIQUE"];
		}
		return $key;
	}
	
	function upsert($table,$data,$find) {
		$id = $this->insert($table,$data,$find);
		if ($this->wasInserted()) {
			//TODO: if (!$this->noUpdateEvents) $GLOBALS["slSession"]->tableUpdate($this->file."/".$table);
			return $id;
		} else {
			$this->update($table,$data,$find);
			return true;
		} 
	}
	
	function updateByKey($table, $key, $data) {
		$keyField = $this->getTableKey($table);
		return $this->update($table,$data,array($keyField=>$key));
	}
	
	function update($table,$data,$find,$options = array()) {
		if (isset($options["showQuery"])) $this->showQuery = true;

		$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
		
		if (count($find) == 1) {
			$findKey = array_pop(array_keys($find));
			if (!($findKey == (isset($info["key"]) ? $info["key"] : "id") || (isset($info["unique"]) && $findKey == $info["unique"]))) {
				if ($res = $this->select($table,$find)) {
					$key = (isset($info["key"]) ? $info["key"] : "id");
					if ($this->rows($res) == 1) {
						$row = $res->fetchArray(SQLITE3_ASSOC);
						$find = array($key=>$row[$key]);
					} else {
						while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
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
					if (!in_array($info["fields"][$n]["dependency"],$dependencies)) $dependencies[] = $info["fields"][$n]["dependency"];
				}
				if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group") {
					$dependencies[] = $n;
				}
			}
		}
		
		foreach ($data as $n=>$v) {
			if (!isset($this->tables[$table][$n])) $getData = true;
		}
		
		if ($getData || isset($info["oldData"]) || count($dependencies)) {
			$select = $getData ? array("data") : array();
			
			if (isset($info["oldData"])) {
				$select = null;
			} else if ($dependencies) {
				$select = array_merge($select,$dependencies);
			}
			
			if ($res = $this->select($table,$find,array("select"=>$select))) {
				$this->oldData = $o = $res->fetchArray(SQLITE3_ASSOC);
				foreach ($dependencies as $n) {
					if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group") {
						$this->groupUpdate($o[$n],$data[$n],$info["fields"][$n],$table,$find);
						continue;
					}
					$data[$n] = $o[$n];
				}
				if (isset($o["data"])) {
					$data["data"] = json_decode($o["data"],true);
					if (!$data["data"]) $data["data"] = array(); 
				}
			}
		}
		
		$this->query("UPDATE ".$this->table($table,$options)." SET ".$this->arrayToSql($table,$data,false,$options)." WHERE ".$this->where($table,$find));
		
		$this->dispatchAffected($table,$data,$find,false,$options);
		
		$this->oldData = null;
	}
	
	function table($table,$options) {
		$tables[] = $table;
		if (isset($options["extraTables"])) {
			if (!is_array($options["extraTables"])) $options["extraTables"] = array($options["extraTables"]);
			$tables = array_merge($tables,$options["extraTables"]);
		}
		foreach ($tables as &$table) {
			if ($table{0} != "`") $table = "`".$table."`";
		}
		return implode(",",$tables);
	}
	
	function groupUpdate($oldValue,&$newValue,$fieldInfo,$table,$id) {
		$remove = $append = false;
		if ($newValue) {
			if ($newValue{0} == "+") {
				$newValue = substr($newValue,1);
				$append = true;
			} elseif ($newValue{0} == "-") {
				$removeValue = explode(",",substr($newValue,1));
				$newValue = "";
				$remove = true;
			}
		}
				
		if (is_array($id)) {
			$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
			
			$findKey = array_pop(array_keys($id));
			if ($findKey == (isset($info["key"]) ? $info["key"] : "id") || (isset($info["unique"]) && $findKey == $info["unique"])) {
				$id = array_pop(array_values($id));
			} else {
				if ($res = $this->select($table,$find)) {
					$key = (isset($info["key"]) ? $info["key"] : "id");
					$row = $res->fetchArray(SQLITE3_ASSOC);
					$id = $row[$key];
				}
			}
		}
		
		$oldValue = trim($oldValue) ? explode(",",$oldValue) : array();
		$newValue = trim($newValue) ? explode(",",$newValue) : array();
		if ($append) {
			foreach ($oldValue as $v) {
				if (!in_array($v,$newValue)) $newValue[] = $v;
			}
		}
		
		if ($remove) {
			foreach ($oldValue as $v) {
				if (!in_array($v,$removeValue)) $newValue[] = $v;
			}
		}
		$diff = array_merge(array_diff($newValue,$oldValue),array_diff($oldValue,$newValue));
		
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
		$newValue = implode(",",$newValue);
	}

	function groupDiff($id,$ref,$name,$change) {
		$nameSafe = safeName($name);
		if ($res = $this->select("groups",array("ref"=>$ref,"nameSafe"=>$nameSafe))) {
			$group = $res->fetchArray(SQLITE3_ASSOC);
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
		$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);

		$this->dispatchAffected($table,array("DELETE"=>true),$find,true,$options);
		
		if (isset($info["fields"])) {
			foreach ($info["fields"] as $n=>$field) {
				if (isset($field["type"]) && $field["type"] == "group") {
					$newFind = $find;
					$newFind["groups"] = array("!","");
					if ($res = $this->select($table,$newFind)) {
						while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
							$blank = "";
							$this->groupUpdate($row[$n],$blank,$field,$table,$row["id"]);
						}
					}
				}
			}
		}
		
		$res = $this->query("DELETE FROM `$table` WHERE ".$this->where($table,$find).(isset($options["limit"])?" LIMIT ".$options["limit"]:""));
		//TODO: if ($this->conn->changes() && !$this->noUpdateEvents) $GLOBALS["slSession"]->tableUpdate($this->file."/".$table);
	}

	function dispatchAffected($table,$data,$find,$force = false,$options = array()) {
		if ($this->noUpdateEvents || isset($options["noUpdateEvents"])) return;
		$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
		$options["select"] = array($this->tableKeys[$table]);
		if (isset($info["userField"])) $options["select"][] = $info["userField"];
		if ($force || $this->conn->changes()) {
			if ($res = $this->select($table,$find,$options)) {
				while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
					$row = array_merge($row,$data);
					$row["_KEY"] = $row[$this->tableKeys[$table]];
					unset($row[$this->tableKeys[$table]]);
					
					$GLOBALS["slCore"]->nonUserSpecificDispatch("change-db/".$table."/".$row["_KEY"],$row,isset($info["userField"])?$row[$info["userField"]]:false);
										
					if (isset($info["updateFunction"])) {
						$info["updateFunction"]($row,$this->oldData,$info);
					}
				}
			}
		}
	}
	
	function getTableKey($table) {
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
			echo "EXPLAIN SELECT ".$this->selectFields($table,$options)." FROM ".$this->table($table,$options)." WHERE ".$this->where($table,$find).(isset($options["groupby"])?" GROUP BY ".$options["groupby"]:"").(isset($options["orderby"])?" ORDER BY ".$options["orderby"]:"").(isset($options["limit"])?" LIMIT ".$options["limit"]:"");
			exit();
		}
		
		$res = $this->query("SELECT ".$this->selectFields($table,$options)." FROM ".$this->table($table,$options)." WHERE ".$this->where($table,$find).(isset($options["groupby"])?" GROUP BY ".$options["groupby"]:"").(isset($options["orderby"])?" ORDER BY ".$options["orderby"]:"").(isset($options["limit"])?" LIMIT ".$options["limit"]:""));

		$this->table = $table;
		$this->res = $res;
		if ($res && $this->rows($res)) return $res;
		return false;
	}
	
	function rows($res) {
		$rows = 0;
		while ($res->fetchArray()){ $rows ++; }
		$res->reset();
		return $rows;
	}
	
	function selectFields($table,$options) {
		$this->nameFromDisplayName = false;
		if (isset($options["select"])) {
			$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
			if (!is_array($options["select"])) $options["select"] = explode(",",$options["select"]);
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
							$this->nameFromDisplayName[$item] = '$sql["'.$field.'"]';
							if (!in_array($field,$options["select"])) $options["select"][] = $field;
						}
					}
					} else {
					unset($options["select"][$pos]);
				}
			}
			return implode(",",$options["select"]);
		}
		return "*";
	}	
	
	function fetch($table=null,$res=null) {
		if ($table === null) $table = $this->table;
		if ($res === null) $res = $this->res;
		return $this->sqlToArray($table,$res);
	}
	
	function fetchAll($table=null,$res=null) {
		$rv = array();
		while ($row = $this->fetch($table,$res)) {
			$rv[] = $row;
		}
		return $rv;
	}
			
	function where($table,$find,$delim = " AND ",$noExtra = false,$insert = false) {
		$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
		if (is_string($find)) return $noExtra ? $find : $this->extraWhere($table,$find,$info);
		if (!$find) return $noExtra ? "1" : $this->extraWhere($table,"1",$info);
		
		$noUser = false;
		if (isset($find["_NO_USER"])) {
			$noUser = true;
			unset($find["_NO_USER"]);
		}
		$rv = array();
		
		foreach ($find as $n=>$v) {
			switch ($n) {					
				case "_KEY":
					$n = is_numeric($v) || !isset($info["unique"]) ? $this->getTableKey($table) : $info["unique"];
					break;
				
				case "_UNIQUE":
					$n = $info["unique"];
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
						
			if (isset($info["unique"]) && $n == $info["unique"] && isset($this->fieldHash[$table][$n]) && !isset($find[$n."Hash"]) && !is_array($v)) {
				$v = array("_lit","(`$n`=".$this->safe($v)." AND `".$n."Hash`=0x".$this->hash($v,$this->fieldHash[$table][$n]).")");
			}
			
			if (is_array($v) && count($v)) {
				switch (strtolower($v[0])) {
					case "_lit":
						$rv[] = $v[1];
						break;
					
					case "_hex":
						$rv[] = array("`$n`","0x".$v[1]);
						break;
						
					case "!":
						$rv[] = "`$n`!=".(count($v) == 3 ? $v[1] : $this->safe($v[1]));
						break;
						
					case "+=":
						$rv[] = $insert ? array("`$n`",(float)$v[1]) : "`$n`=(`$n`+".(float)$v[1].")";
						break;
					
					case "-=":
						$rv[] = $insert ? array("`$n`","-".(float)$v[1]) : "`$n`=(`$n`-".(float)$v[1].")";
						break;
					
					case "*=":
						$rv[] = $insert ? array("`$n`","0") : "`$n`=(`$n`*".(float)$v[1].")";
						break;
						
					case "/=":
						$rv[] = $insert ? array("`$n`","0") : "`$n`=(`$n`/".(float)$v[1].")";
						break;		
					
					case "|=":
						$rv[] = $insert ? array("`$n`",(int)$v[1]) : "`$n`=(`$n`|".(int)$v[1].")";
						break;	
						
					case "&=":
						$rv[] = $insert ? array("`$n`","0") : "`$n`=(`$n`&".(int)$v[1].")";
						break;	
						
					case "=":
					case "<": case "<=":
					case ">": case ">=":
						$rv[] = "`$n`".$v[0].(count($v) == 3 ? $v[1] : $this->safe($v[1]));
						break;
						
					case "contains":
						$rv[] = "`$n` LIKE '%".(count($v) == 3 ? $v[1] : $this->safe($v[1],true))."%'";
						break;
						
					case "like":
						$rv[] = "`$n` LIKE ".(count($v) == 3 ? $v[1] : $this->safe($v[1]));
						break;
				}
			} else $rv[] = array("`$n`",$this->safe($v));
		}
		if ($insert) {
			$keys = array();
			$vals = array();
			foreach ($rv as $n=>$v) {
				if (is_array($v)) {
					$keys[] = $v[0];
					$vals[] = $v[1];
				}
			}
			return "(".implode(",",$keys).") VALUES (".implode(",",$vals).")";
		} else {
			foreach ($rv as $n=>&$v) {
				if (is_array($v)) {
					$v = $v[0]."=".$v[1];
				}
			}
		}
		return $noExtra ? implode($delim,$rv) : $this->extraWhere($table,implode($delim,$rv),$info,$noUser);
	}
	
	function extraWhere($table,$where,$info,$noUser = false) {
		if (!$noUser && $info && isset($info["userField"]) && !$GLOBALS["slCronSession"] && isset($GLOBALS["slSession"])) {
			$pid = (int)$GLOBALS["slSession"]->get("parentID");
			return "(`".$table."`.`".$info["userField"]."`=".$pid.($info["table"]=="db/user"?" OR `".$table."`.`id`=".$pid:"").") AND (".$where.")";
		}
		return $where;
	}
	
	function arrayToSql($table,&$array,$insert = false, $options = array()) {
		$data = array();
		if (isset($array["data"])) {
			$data = $array["data"];
			unset($array["data"]);
		}
		
		$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
		
		if ($info && isset($info["nameField"]) && isset($array["_NAME"])) {
			$array[$info["nameField"]] = $array["_NAME"];
			unset($array["_NAME"]);
		}
		

		if ($info && isset($info["fields"])) {
			foreach ($info["fields"] as $n=>$field) {
				if (isset($array[$n])) {
					if (isset($field["readOnly"]) && $field["readOnly"]) {
						unset($array[$n]);
					} elseif (isset($field["useID"]) && $field["useID"] && !(int)$array[$n]) { // Use ID
						if ($res = $GLOBALS["slCore"]->db->select($field["ref"],array("_NAME"=>array_pop(explode(";",$array[$n]))),array("select"=>"_KEY"))) {
							$row = $res->fetchArray(SQLITE3_ASSOC);
							$array[$n] = $row["_KEY"];
						}
					}
				}
			}
		}

		if ($insert && $info && isset($info["userField"]) && isset($GLOBALS["slSession"])) $array[$info["userField"]] = (int)$GLOBALS["slSession"]->get("parentID");
		
		$tData = $this->tables[$table];
		$rv = array();
		foreach ($array as $n=>$v) {
			if (isset($info["fields"][$n])) {
				if (isset($info["fields"][$n]["type"]) && $info["fields"][$n]["type"] == "group" && strlen($v) && ($v{0} == "+" || $v{0} == "-")) $array[$n] = substr($v,1);
			
				$field = $info["fields"][$n];

				if (!isset($options["noUpdateFunction"]) && isset($field["updateFunction"])) {
					$field["updateFunction"]($v,$array,$info);
				}				
			}
		}
		
		foreach ($array as $n=>$v) {
			if (isset($this->fieldHash[$table][$n])) {
				$rv[$n."Hash"] = array("_HEX",$this->hash($v,$this->fieldHash[$table][$n]));
			}
			if (isset($tData[$n])) {
				$rv[$n] = $v;
			} else {
				$data[$n] = $v;
			}
		}
		
		if ($insert) {
			if (isset($tData["created"])) $rv["created"] = time();
			if (isset($tData["createdBy"])) $rv["createdBy"] = (int)$GLOBALS["slSession"]->get("parentID");
		}
		if (isset($tData["updated"])) $rv["updated"] = time();
		
		if ($data && isset($tData["data"])) $rv["data"] = json_encode($data);
		return $this->where($table,$rv,",",true,$insert);
	}
	
	function hashAll() {
		foreach ($this->fieldHash as $table=>$fields) {
			$keyField = $this->getTableKey($table);
			foreach ($fields as $field=>$size) {
				if ($res = $this->select($table,array($field."Hash"=>0))) {
					while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
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
	
	function sqlToArray($table,$res) {
		if ($sql = $res->fetchArray(SQLITE3_ASSOC)) {
			$info = $GLOBALS["slCore"]->db->getTableInfo($this->file."/".$table);
			if (isset($sql["data"])) {
				$data = json_decode($sql["data"],true);
				unset($sql["data"]);
				if (!$data) $data = array();
				$sql = array_merge($data,$sql);
			}
			$key = $this->getTableKey($table);
			if (isset($sql[$key])) $sql["_KEY"] = $sql[$key];
			if (isset($info["unique"]) && isset($sql[$info["unique"]])) $sql["_UNIQUE"] = $sql[$info["unique"]];
			
			if (isset($info["fields"])) {
				foreach ($info["fields"] as $n=>$field) {
					if (isset($field["writeOnly"]) && $field["writeOnly"]) {
						unset($sql[$n]);
					}
				}
			}
			
			if ($this->nameFromDisplayName) {
				$this->nameFromDisplayName["sl."] = "";
				foreach ($info["displayName"] as $n) {
					$v = false;
					eval('$v = '.str_replace(array_keys($this->nameFromDisplayName),array_values($this->nameFromDisplayName),$n).";");
					if (trim($v)) {
						$sql["_NAME"] = $v;
						break;
					}
				}
				if (!isset($sql["_NAME"])) $sql["_NAME"] = "?";
			}
			
			return $sql;
		}
		return null;
	}
	
	function tableRefresh() {
		$this->tables = array();
		$this->tableKeys = array();
		$this->fieldHash = array();
		
		if ($schema = $this->getSchema()) {
			foreach ($schema as $table=>$d) {
				if (isset($d["fields"])) {
					$this->tables[$table] = $d["fields"];
					foreach ($d["fields"] as $n=>$v) {
						if (isset($v["KEY"])) $this->tableKeys[$table] = $n;
					}
				}
			}
		}
	}
	
	public function safe($v, $noQuotes = false) {
		$q = $noQuotes ? "" : "'";
		if (is_numeric($v)) {
			return $v;
		} else {
			if (is_array($v)) {
				return $q.json_encode($v).$q;
			} else {
				return $q.$this->conn->escapeString($v).$q;
			}
		}
	}
	
	function getSchema() {
		$tables = array();

		$results = $this->query("SELECT * FROM sqlite_master WHERE type='table'");
		
		$extras = array("KEY","NOT NULL","PRIMARY");
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			$o = getStringBetween("(",")",$row["sql"],true);

			if ($o2 = getStringBetween("(",")",$o)) {
				$o = str_replace($o2,urlencode($o2),$o);
			}
			$cols = explode(",",$o);
			$fields = array();
			foreach ($cols as $col) {
				$o = preg_split("/[\s]+/",trim($col),3);
				$field = array_shift($o);
				$type = "";
				$extra = false;
				
				if (count($o)) $type = array_shift($o);
				if (count($o)) $extra = array_shift($o);

				if (strpos($type,"(") !== false) {
					$t = explode(",",urldecode(getStringBetween("(",")",$type)));
					foreach ($t as &$v) {
						$v = trim($v);
					}
					$fields[$field] = $t;
				} else {		
					$fields[$field] = array("type"=>$type);
					if ($extra) {
						foreach ($extras as $e) {
							if (strpos($extra,$e)) {
								$fields[$field][$e] = true;
							}
						}
					}
				}
			}
			$row["fields"] = $fields;
			$tables[$row["name"]] = $row;  
		}
		return $tables;
	}
}
