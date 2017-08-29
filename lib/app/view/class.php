<?php

require_once(SL_INCLUDE_PATH."/class.slDbIndexer.php");

class slView extends slAppClass {
	protected $dbi;
	protected $setup = false;
	protected $app;
	
	function __construct($app) {
		$this->app = $app;

		if (isset($this->app->args[0]) && $GLOBALS["slSession"]->isLoggedIn()) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup = $this->app->translateArray($this->setup);

				if (isset($this->setup["permissions"])) {
					if (!$this->app->checkPermissions($this->setup["permissions"])) return;
				}
				
				$this->setup["exportFields"] = array();
				foreach ($this->setup["fields"] as $n=>$field) {
					if ($this->useForExport($n)) $this->setup["exportFields"][$n] = $field;
				}
				
				$this->setup["dir"] = $GLOBALS["slSession"]->user->dir;
				
				if ($orderby = $this->app->get("orderby")) $this->setup["orderby"] = $orderby;
				if ($orderdir = $this->app->get("orderdir")) $this->setup["orderdir"] = $orderdir;			
				if ($search = $this->app->get("search")) $this->setup["search"] = $search;	
				if ($searchFields = $this->app->get("searchFields")) $this->setup["searchFields"] = $searchFields;
				if ($filter = $this->app->get("filter")) $this->setup["filter"] = $filter;
				if ($where = $this->app->get("where")) $this->setup["where"] = $where;

				$this->dbi = getSlDbIndexer($this->setup);
			}
		}
		parent::__construct($app);
	}
	
	function getUnique($params = false) {
		if (!$params) {
			$params = array();
			if ($search = $this->app->get("search")) $params[] = "Search for ".$search;
			if ($searchFields = $this->app->get("searchFields")) {
				require_once(SL_INCLUDE_PATH."/class.slValue.php");
				foreach ($searchFields as $n=>$v) {
					if (isset($this->setup["fields"][$n])) {
						$field = $this->setup["fields"][$n];
						if (is_array($v)) {
							$a = array();
							foreach ($v as $v2) {
								$a[] = valueToString($v2,$field["type"]);
							}
							$params[] = $field["label"]." is ".implode(" or ",$a);
						} else {
							$params[] = $field["label"]." is ".valueToString($v,$field["type"]);
						}
					}
				}
			}
			if ($filter = $this->app->get("filter")) $params[] = "Filter-".$filter;	
			if ($orderby = $this->app->get("orderby")) $params[] = "Order By ".$orderby.$this->app->get("orderdir","desc");
		}
		$unique = preg_replace('/[^A-Za-z\d\_\-]+/','_',$this->setup["name"]."__".implode("__",$params));
		return strlen($unique) > 64 ? md5($unique) : $unique;
	}
	
	function reindex() {
		$GLOBALS["slSession"]->tableUpdate($this->app->args[0],true);
		$this->dbi = getSlDbIndexer($this->setup);
		if ($this->dbi)	return $this->dbi->count();
		return 0;
	}
	
	function export($type,$fields = true) {
		$exporterFile = SL_INCLUDE_PATH."/exporters/class.".safeFile($type).".php";
		if (is_file($exporterFile)) {
			require_once($exporterFile);
			$className = toCamelCase("exporter-".$type);
			$exporter = new $className($this->getUnique()."-export");
			
			$exporter->init($this->app->args[0]);
			$exporter->reset();
			
			$cnt = $this->dbi->count();
			for ($i = 0; $i < $cnt; $i++) {
				if ($res = $GLOBALS["slCore"]->db->select($this->app->args[0],array($this->setup["key"]=>$this->dbi->getKey($i)),array("select"=>array("*","_NAME"),"limit"=>"1"))) {
					$row = $this->labelAssoc($res->fetchAsText(),$fields);
					$exporter->add($row);
				}
			}
			$GLOBALS["slCore"]->db->commit($this->app->args[0]);
			return array("action"=>array("open-url",$exporter->getFileUrl()));
		}
		return false;
	}
	
	function getExtraFields() {
		$fields = array();
		$cnt = $this->dbi->count();
		$inc = max(1,round($cnt / 500));
		$breakAfter = microtime(true) + $inc;
		for ($i = 0; $i < $cnt; $i += 10) {
			if ($res = $GLOBALS["slCore"]->db->select($this->app->args[0],array($this->setup["key"]=>$this->dbi->getKey($i)),array("select"=>"_EXTRA","limit"=>"1"))) {
				$row = $res->fetch();
				foreach ($row as $n=>$v) {
					if (trim($n)) {
						if (!isset($fields[$n])) $fields[$n] = 0;
						$fields[$n] ++;
					}
				}
			}
			if (microtime(true) > $breakAfter) break;
		}
		return $fields;
	}
	
	function action($action,$params,$ids,$startPos,$extra = array()) {
		require_once(SL_INCLUDE_PATH."/class.slBitIterator.php");
		$continuePos = 0;
		$continueFile = $this->app->dataDir."/continue-".safeFile($action);
		
		if ($startPos) {
			$ids = file_get_contents($continueFile);
			if ($ids{0} == "[") $ids = explode(",",substr($ids,1,-1));
		} else {
			$this->app->set("idsHash",md5($ids));
		}
		
		$idList = new slBitIterator($ids);
		$idList->setPos($startPos);
		
		$endTs = microtime(true) + 1;
		
		$GLOBALS["slCore"]->db->preventUpdateEvents($this->app->args[0]);
		$GLOBALS["slCore"]->db->begin($this->app->args[0]);
		
		//TODO: permissions
		$addTo = false;
		switch ($action) {
			case "export":
				$exporterFile = SL_INCLUDE_PATH."/exporters/class.".safeFile($params[0]).".php";
				if (is_file($exporterFile)) {
					require_once($exporterFile);
					$className = toCamelCase("exporter-".$params[0]);
					$exporter = new $className($this->getUnique(array("Checked"=>$this->app->get("idsHash")))."-".safeFile($action));
					
					$exporter->init($this->app->args[0]);
					if ($startPos == 0) $exporter->reset();
					while (($i = $idList->getNext()) !== false) {
						if ($res = $GLOBALS["slCore"]->db->select($this->app->args[0],array($this->setup["key"]=>$this->dbi->getKey($i)),array("select"=>array("*","_NAME"),"limit"=>"1"))) {
							$row = $this->labelAssoc($res->fetchAsText());
							$exporter->add($row);
						}
					}
					$GLOBALS["slCore"]->db->commit($this->app->args[0]);
					return array("action"=>array("open-url",$exporter->getFileUrl()));
				}
				return false;
				
				
			case "delete":
				if (setAndTrue($this->setup,"disableDelete")) break;
				while (($i = $idList->getNext()) !== false) {
					$GLOBALS["slCore"]->db->delete($this->app->args[0],array($this->setup["key"]=>$this->dbi->getKey($i)));
					if (microtime(true) > $endTs) {
						$continuePos = $idList->getPos();
						break;
					}
				}
				break;
			
			case "set":
				while (($i = $idList->getNext()) !== false) {
					$GLOBALS["slCore"]->db->update($this->app->args[0],array($extra["field"]=>$extra["value"]),array($this->setup["key"]=>$this->dbi->getKey($i)),array("limit"=>"1"));
					if (microtime(true) > $endTs) {
						$continuePos = $idList->getPos();
						break;
					}
				}
				break;
				
			case "add-to":
			$addTo = true;
			case "remove-from":
				$vals = decodeMulti($extra["value"]);
				$safeVals = decodeMulti($extra["value"],true);
				$isGroup = $this->setup["fields"][$extra["field"]]["type"] == "group";
					
				while (($i = $idList->getNext()) !== false) {
					if ($isGroup) {
						$GLOBALS["slCore"]->db->update($this->app->args[0],array($extra["field"]=>($addTo?"+":"-").$extra["value"]),array($this->setup["key"]=>$this->dbi->getKey($i)),array("limit"=>"1"));
					} elseif ($res = $GLOBALS["slCore"]->db->select($this->app->args[0],array($this->setup["key"]=>$this->dbi->getKey($i)),array("limit"=>"1","select"=>$extra["field"]))) {
						$row = $res->fetch_assoc();
						$split = decodeMulti($row[$extra["field"]]);
						
						$changed = false;
						for ($j = 0; $j < count($vals); $j++) {
							if ($addTo) {
								if (!in_array($safeVals[$j],decodeMulti($row[$extra["field"]],true))) {
									$split[] = $vals[$j];
									$changed = true;
								}
							} else {
								if (($pos = array_search($safeVals[$j],decodeMulti($row[$extra["field"]],true))) !== false) {
									array_splice($split,$pos,1);
									$changed = true;
								}
							}
						}
						
						if ($changed) $GLOBALS["slCore"]->db->update($this->app->args[0],array($extra["field"]=>encodeMulti($split)),array($this->setup["key"]=>$this->dbi->getKey($i)),array("limit"=>"1"));
						
					}
					if (microtime(true) > $endTs) {
						$continuePos = $idList->getPos();
						break;
					}
				}
				break;
		}
		
		$GLOBALS["slCore"]->db->commit($this->app->args[0]);
		
		if (!$startPos && $continuePos) {
			file_put_contents($continueFile,is_array($ids)?"[".implode(",",$ids)."]":$ids);
		} elseif ($startPos && !$continuePos) {
			unlink($continueFile);
		}
		
		if (!$continuePos) $GLOBALS["slSession"]->tableUpdate($this->app->args[0],true);
		
		return array($continuePos,$idList->length);
	}

	function useForExport($n) {
		if (!isset($this->setup["fields"][$n])) return false;
		$field = $this->setup["fields"][$n];
		if (setAndTrue($field,"noExport")) return false;
		if (setAndTrue($field,"import")) return false;
		
		return true;
	}
	
	function labelAssoc($row,$fields = true) {
		$out = array("YP ".$this->setup["singleName"]." ID"=>$row["_KEY"]);
		if ($fields === true) {
			foreach ($this->setup["fields"] as $n=>$field) {
				if (isset($row[$n]) && $this->useForExport($n)) $out[$field["label"]] = $row[$n];
			}
		} else {
			foreach ($fields as $n=>$use) {
				if (isset($row[$n]) && $use) {
					if (isset($this->setup["fields"][$n])) {
						if ($this->useForExport($n)) $out[$this->setup["fields"][$n]["label"]] = $row[$n];
					} else {
						$out[$n] = $row[$n];						
					}
				}
			}
		}
		$copy = array("_KEY","_UNIQUE","_NAME","_IMAGE");
		foreach ($copy as $n) {
			if (isset($row[$n])) $out[$n] = $row[$n];
		}
		return $out;
	}
	
	function setup() {
		$hasGroups = false;
		
		if (isset($this->setup["fields"]) && is_array($this->setup["fields"])) {
			foreach ($this->setup["fields"] as $n=>$field) {
				if (isset($field["type"]) && $field["type"] == "group") {
					$hasGroups = true;
					break;
				}
			}
		}
		
		//Get top 5 groups
		if ($hasGroups) {
			$this->setup["topGroups"] = array();
			if ($res = $GLOBALS["slCore"]->db->select("db/groups",array("ref"=>$this->app->args[0],"hideAsFilter"=>0),array("orderby"=>"`links` DESC","limit"=>5))) {
				while ($row = $res->fetch_assoc()) {
					$this->setup["topGroups"][] = array((int)$row["id"],$row["nameSafe"],$row["name"]);
				}
			}
		}
		
		//Get exporters
		$this->setup["exporters"] = array();
		$dir = realpath(SL_INCLUDE_PATH."/exporters");
		if ($dp = opendir($dir)) {
			while ($file = readdir($dp)) {
				$path = $dir."/".$file;
				
				if (is_file($path)) {
					require_once($path);
					
					$uid = substr(substr($file,6),0,-4);
					
					$className = toCamelCase("exporter-".$uid);
					
					$ob = new $className();
					
					if (method_exists($ob,"getInfo")) {
						if ($info = $ob->getInfo($this->setup)) {								
							$this->setup["exporters"][] = array(
								"label"=>$info["name"],
								"options"=>isset($info["options"])?$info["options"]:array(),
								"action"=>"export",
								"uid"=>$uid
							);
						}
					}
						
					$ob->__destruct();	
				}
			}
			closedir($dp);
		}			
		
		return $this->setup;
	}
	
	function objectCount() {
		if ($this->dbi)	return $this->dbi->count();
		return 0;
	}
	
	function sections() {
		if ($this->dbi)	return $this->dbi->getSections();
		return null;
	}
	
	function item($i) {
		$item = $this->dbi->get($i);
		
		if (isset($this->setup["sourceMap"])) {
			foreach ($this->setup["sourceMap"] as $from=>$toList) {
				if (isset($item[$from])) {
					foreach ($toList as $to) {
						$item[$to] = $item[$from];
					}
				}
			}
		}
		if ($this->dbi)	return $item;
		return false;
	}
	
	function totalRow() {
		$rate = null;
		$total = array();
		$select = array();
		foreach ($this->setup["fields"] as $n=>$field) {
			if (isset($field["total"])) {
				if (isset($field["type"]) && $field["type"] == "currency") {
					if (!$rate) {
						$rate = json_decode(file_get_contents(SL_DATA_PATH."/exchange-rate.json"),true);
					}
					$base = 0; $cnt = 0;
					if ($res = $GLOBALS["slCore"]->db->select(
						$this->app->args[0],
						"1",
						array("select"=>array(
							strtoupper($field["total"])."(".$n.") AS 'value'",
							"SUBSTRING_INDEX(".$n.",' ',-1) AS 'type'"
						),"groupby"=>"type")
					)) {
						while ($row = $res->fetch_assoc()) {
							if (isset($rate["rates"][$row["type"]])) {
								$cnt ++;
								$base += $row["value"] / $rate["rates"][$row["type"]];
							}
						}
					}
					$base *= $rate["rates"][$GLOBALS["slConfig"]["international"]["currency"]];
					switch ($field["total"]) {
						case "avg":
							$base /= $cnt;
							break;
					}
					$total[$n] = $base." ".$GLOBALS["slConfig"]["international"]["currency"];
				} else {
					$select[] = strtoupper($field["total"])."(".$n.") AS '".$n."'";
				}
			}
		}
		if ($select) {
			if ($res = $GLOBALS["slCore"]->db->select($this->app->args[0],"1",array("select"=>$select))) {
				$total = array_merge($total,$res->fetch_assoc());
			}
		}
		return $total;
	}
	
	function search($q) {
		$this->app->set("search",$q);
		$this->setup["search"] = $q;
		
		$this->dbi = getSlDbIndexer($this->setup);
		return $this->dbi->count();
	}
	
	function searchField($q) {
		foreach ($q as $n=>$v) {
			if (is_array($v) && $v[0] == "_lit") unset($q[$n]);
		}
		
		$this->app->set("searchFields",$q);
		$this->setup["searchFields"] = $q;
		
		$this->dbi = getSlDbIndexer($this->setup);
		return $this->dbi->count();
	}
	
	function filter($n,$v = false) {
		
		$filter = $n == "ALL" ? null : array($n,$v);
		
		$this->app->set("filter",$filter);
		$this->setup["filter"] = $filter;
		
		$this->dbi = getSlDbIndexer($this->setup);
		return $this->dbi->count();
	}
	
	function orderby($field,$order) {
		if (!isset($this->setup["fields"][$field])) return false;
		
		$order = $order == "asc" ? "asc" : "desc";
		
		$this->app->set("orderby",$field);
		$this->app->set("orderdir",$order);
		
		$this->setup["orderby"] = $field;
		$this->setup["orderdir"] = $order;
		
		$this->dbi = getSlDbIndexer($this->setup);
		return $this->dbi->count();
	}
}
