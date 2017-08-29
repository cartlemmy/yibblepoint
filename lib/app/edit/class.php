<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");
require_once(SL_INCLUDE_PATH."/class.slDbIndexer.php");

$GLOBALS["SLIE_NUM"] = 0;

class slItemEdit extends slAppClass {
	private $dbi;
	private $setup = false;
	private $subSetup = null;
	private $subSetups = array();
	private $updated = array();
	private $subDBI = null;
	private $subDBIs = array();
	private $error = false;
	
	function __construct($app) {
		$this->NUM = $GLOBALS["SLIE_NUM"]++;
		$this->app = $app;
		
		if (isset($this->app->args[0])) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup = $this->app->translateArray($this->setup);
				
				if (isset($this->setup["permissions"])) {
					if (!$this->app->checkPermissions($this->setup["permissions"])) return;
				}
				
				$this->setup["args"] = $this->app->args;
				
				$this->setup["dir"] = $GLOBALS["slSession"]->user->dir;
				if ($orderby = $this->app->get("orderby")) $this->setup["orderby"] = $orderby;
				if ($orderdir = $this->app->get("orderdir")) $this->setup["orderdir"] = $orderdir;			
				if ($search = $this->app->get("search")) $this->setup["search"] = $search;	
				$this->dbi = getSlDbIndexer($this->setup);
				} else {
				$this->error = "'".$this->app->args[0]."' not found.";
			}
		} else {
			$this->error = "Item not specified.";
		}
		parent::__construct($app);
	}

	function getAll() {
		$data = null;
		if (isset($this->app->args[1])) {
			if ($this->app->args[1] == "NEW") {
				$data = array();
				foreach ($this->setup["fields"] as $n=>$v) {
					$data[$n] = "";
				}
			} else {
				if ($res = $GLOBALS["slCore"]->db->selectOne($this->app->args[0],array("_KEY"=>$this->app->args[1]))) {
					$data = $res->fetch();
				}
			}
		}
		return array(
			"setup"=>$this->setup,
			"data"=>$data,
			"error"=>$this->error
		);
	}

	function create($data) {
		$GLOBALS["slCore"]->nonUserSpecificDispatch("refresh-".$this->app->args[0],"inserted");
		$id = $GLOBALS["slCore"]->db->insert($this->app->args[0],$data);
		$this->app->setArg(1,$id);
		return $id;
	}
	
	function set($n,$v) {
		$this->updated[$n] = $v;
		return $this->NUM;
	}
	
	function apply() {
		if (count($this->updated)) {
			$GLOBALS["slCore"]->db->update($this->app->args[0],$this->updated,array("_KEY"=>$this->app->args[1]));
			$this->updated = array();
		}
		return $this->NUM;
	}
	
	//Sub items
	function getSubItemInfo($table,$filter) {
		if ($info = $GLOBALS["slCore"]->db->getTableInfo($table)) {
			$info = $this->app->translateArray($info);

			if (isset($info["permissions"])) {
				if (!$this->app->checkPermissions($info["permissions"])) return false;
			}
			return $info;
		}
		return false;
	}
	
	function subObjectCount($table,$filter) {
		$this->loadSubObject($table,$filter);
		if ($this->subDBI) return $this->subDBI->count();
		return 0;
	}
	
	function sections($table,$filter) {
		$this->loadSubObject($table,$filter);
		if ($this->subDBI)	return $this->subDBI->getSections();
		return null;
	}
	
	function reindex($table,$filter) {
		$GLOBALS["slSession"]->tableUpdate($table);
		$this->loadSubObject($table,$filter);
		if ($this->subDBI) return $this->subDBI->count();
		return 0;
	}
	
	function item($table,$filter,$i) {
		$this->loadSubObject($table,$filter);
		$item = $this->subDBI->get($i);
		if (isset($this->subSetup["sourceMap"])) {
			foreach ($this->subSetup["sourceMap"] as $from=>$toList) {
				if (isset($item[$from])) {
					foreach ($toList as $to) {
						$item[$to] = $item[$from];
					}
				}
			}
		}
		if ($this->subDBI) return $item;
		return false;
	}
	
	function delete($table,$filter,$id) {
		$this->loadSubObject($table,$filter);
		$GLOBALS["slCore"]->db->delete($table,array($this->subSetup["key"]=>$id));
		return true;
	}
	
	function loadSubObject($table,$filter,$setSetup = array()) {
		$this->subSetup = $this->subDBI = null;
		if (!isset($this->subSetups[$table])) {
			if ($setup = $GLOBALS["slCore"]->db->getTableInfo($table)) {
				
				$setup["dir"] = $GLOBALS["slSession"]->user->dir;
				
				foreach ($setSetup as $n=>$v) {
					$setup[$n] = $v;
				}
				
				if ($filter) $setup["where"] = $filter;	
				
				$setup = $this->app->translateArray($setup);
				
				if ($orderby = $this->app->get($table."-orderby")) $setup["orderby"] = $orderby;
				if ($orderdir = $this->app->get($table."-orderdir")) $setup["orderdir"] = $orderdir;			
				if ($search = $this->app->get($table."-search")) $setup["search"] = $search;	
				
				$this->subSetups[$table] = $setup;
				$this->subDBIs[$table] = getSlDbIndexer($setup);
			} else return false;
		}
		
		$this->subSetup = $this->subSetups[$table];
		$this->subDBI = $this->subDBIs[$table];
	}
	
	function totalRow($table,$filter) {
		$this->loadSubObject($table,$filter);
		$rate = null;
		$total = array();
		$select = array();
		foreach ($this->subSetup["fields"] as $n=>$field) {
			if (isset($field["total"])) {
				if (isset($field["type"]) && $field["type"] == "currency") {
					if (!$rate) {
						$rate = json_decode(file_get_contents(SL_DATA_PATH."/exchange-rate.json"),true);
					}
					$base = 0; $cnt = 0;
					if ($res = $GLOBALS["slCore"]->db->select(
						$table,
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
			if ($res = $GLOBALS["slCore"]->db->select($table,$filter?$filter:"1",array("select"=>$select))) {
				$total = array_merge($total,$res->fetch_assoc());
			}
		}
		return $total;
	}
	
	function orderby($table,$filter,$field,$order) {		
		$order = $order == "asc" ? "asc" : "desc";
		
		$this->app->set($table."-orderby",$field);
		$this->app->set($table."-orderdir",$order);
		
		$this->loadSubObject($table,$filter,array("orderby"=>$field,"orderdir"=>$order));
		
		if (!isset($this->subSetup["fields"][$field])) return false;
		
		return $this->subDBI->count();
	}
}
