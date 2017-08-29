<?php

require_once(SL_INCLUDE_PATH."/class.slDbIndexer.php");

class slItemImport extends slAppClass {
	private $setup = false;
	public $safeRef;
	
	function __construct($app) {
		$this->app = $app;
		
		if (isset($this->app->args[0]) && $GLOBALS["slSession"]->isLoggedIn()) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup = $this->app->translateArray($this->setup);
				$this->setup["dir"] = $GLOBALS["slSession"]->user->dir;
				if ($orderby = $this->app->get("orderby")) $this->setup["orderby"] = $orderby;
				if ($orderdir = $this->app->get("orderdir")) $this->setup["orderdir"] = $orderdir;			
				if ($search = $this->app->get("search")) $this->setup["search"] = $search;	
			}
		}
		parent::__construct($app);
	}
	
	function imp($o,$defaults,$fieldDupCheck) {
		$accepted = 0;
		$inserted = array();
		$updated = array();
		
		$GLOBALS["slCore"]->db->preventUpdateEvents($this->app->args[0]);
		$GLOBALS["slCore"]->db->begin($this->app->args[0]);
		
		
		foreach ($o as $row) {
			if ($defaults) {
				foreach ($defaults as $n=>$v) {
					if (!isset($row[$n])) $row[$n] = $v;					
				}
			}
			
			foreach ($row as $n=>$v) {
				if (isset($this->setup["fields"][$n]) && $this->setup["fields"][$n]["import"] && $this->setup["fields"][$n]["import"] !== true) {
					$row[$this->setup["fields"][$n]["import"]] = $v;
					unset($row[$n]);
				}
			}
				
			$sRow = array();
			if ($fieldDupCheck) {
				foreach ($fieldDupCheck as $n) {
					if (isset($row[$n]) && $row[$n]) {
						$sRow[$n] = $row[$n];
						if (isset($this->setup["duplicateConnections"])) {
							foreach ($this->setup["duplicateConnections"] as $dc) {
								if (in_array($n,$dc)) {
									foreach ($dc as $n2) {
										if (isset($row[$n2])) $sRow[$n2] = $row[$n2];
									}
								}
							}
						}
					}
				}
				foreach ($sRow as $n=>$v) {
					$field = isset($this->setup["fields"][$n]) ? $this->setup["fields"][$n] : false;
					if ($field && isset($field["updateFunction"])) {
						$ar = array($sRow[$n],&$sRow,$this->setup);
						call_user_func_array($field["updateFunction"],$ar);
					}
				}
				$crit = array();
				foreach ($sRow as $n=>$v) {
					if (substr($v,-8) == ";unknown") $v = substr($v,0,-8);
						
					$crit[$n] = array("bmatch",$v);
				}
				
				if ($res = $GLOBALS["slCore"]->db->select($this->app->args[0],$crit)) {
					$old = $res->fetch();
						
					$GLOBALS["slCore"]->db->update($this->app->args[0],$row,array("_KEY"=>$old["_KEY"]));
					
					$updated[] = $old["_KEY"];
					$accepted++;
					continue;
				}
			}
			
			if (isset($this->setup["fields"]["creationType"])) {
				$row["creationType"] = "user-import";
			}

			if (
				isset($this->setup["unique"]) && isset($row[$this->setup["unique"]]) &&
				($res = $GLOBALS["slCore"]->db->select($this->app->args[0],array($this->setup["unique"]=>$row[$this->setup["unique"]])))
			) {
				$r2 = $res->fetch();
				$GLOBALS["slCore"]->db->update($this->app->args[0],$row,array("_KEY"=>$r2["_KEY"]),array("delayed"=>true));
				$updated[] = $r2["_KEY"];
			} else {
				$id = $GLOBALS["slCore"]->db->insert($this->app->args[0],$row,false,array("delayed"=>true));
				if ($GLOBALS["slCore"]->db->wasInserted($this->app->args[0])) {
					$inserted[] = $id;
				} else {
					$updated[] = $id;
				}
			}

			$accepted++;
		}
		$GLOBALS["slCore"]->db->commit($this->app->args[0]);
		return array("accepted"=>$accepted,"inserted"=>$inserted,"updated"=>$updated);
	}
	
	function importComplete($importFile) {
		$GLOBALS["slSession"]->tableUpdate($this->app->args[0],true);
		$GLOBALS["slCore"]->db->update("db/groups",array("hideAsFilter"=>1),array("ref"=>$this->app->args[0],"name"=>$importFile));
	}
	
	function setup() {
		return $this->setup;
	}
}
