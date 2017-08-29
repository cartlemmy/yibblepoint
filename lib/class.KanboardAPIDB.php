<?php

require_once(dirname(__FILE__).'/class.KanboardAPI.php');

class KanboardAPIDB extends slClass {
	private $kb;
	private $settings;
	private $tables = array();
	private $noUpdateEvents = false;
	private $inTransaction = false;
	private $info;
	
	public function __construct() {
		
	}
	
	public function connect($settings) {
		
		$this->kb = new KanboardAPI();
		
		$this->settings = $settings;
		return true;
	}
	
	public function getTable($table) {
		if (is_object($table)) return $table;
		if (!isset($this->tables[$table])) {
			
			$this->info = $GLOBALS["slCore"]->db->getTableInfo("api/".$table);			
			
			//$this->tables[$table] = $tableOb;
		}
		return $this->tables[$table];
	}
	
	public function select($table, $find = false, $options = false) {
		// TODO
		
		switch ($table) {
			case "task":
				$project_id = isset($find["project_id"]) ? $find["project_id"] : false;
				$status_id = isset($find["status_id"]) ? $find["status_id"] : 0;
				if ($find === false) return $this->kb->getAllTasks();
				return $this->kb->searchTasks($find);
		}
		
		return $res->query($find, $options) ? $res : false;
	}
	
	public function delete($table, $find, $options = array()) {
		// TODO
	}		
		
	public function update($table, $data, $find, $options = array()) {		
		$table = $this->getTable($table);
		$delete = $data === "DELETE";
		
		/*if ($res = $this->select($table, $find, $options)) {
			while ($file = $res->fetchFile()) {
								
				if (!$delete) {
					$changed = array();
					foreach ($data as $n=>$v) {
						if (!is_array($v) && $n == $table->info["nameField"]) $safeNew = safeName($v);
						if (!isset($oldData[$n]) || $oldData[$n] != $v) $changed[$n] = $v;
					}
					
					if (!count($changed)) return;
					
					self::dataToXml($xml,$data);
				
					if ($base != $safeNew) {
						$cnt = 0;
						while (is_file($check = str_replace($base.'.xml',$safeNew.($cnt?"-".$cnt:"").'.xml',$file))) {
							$cnt++;
						}
						if ($cnt) $safeNew .= "-".$cnt;
						
						$table->rename($base, $safeNew);

						if ($table->historyDir) {
							rename($table->historyDir."/".$base, $table->historyDir."/".$safeNew);
						}
						rename($file, $file = str_replace($base.'.xml',$safeNew.'.xml',$file));
						$base = $safeNew;
					}
				}
				
				//$_KEY = ???;			
				
				
				
				if ($delete) {
					$GLOBALS["slCore"]->nonUserSpecificDispatch("change-".$table->info["table"]."/".$_KEY,array("DELETE"=>true),false);
					unlink($file);
				} else {
					if ($modField = $this->getFieldByType('modified')) {
						$changed[$modField[0]] = time();
					}
					$GLOBALS["slCore"]->nonUserSpecificDispatch("change-".$table->info["table"]."/".$_KEY,$changed,false);
					file_put_contents($file,$xml->asXML());
				}
				
				$GLOBALS["slCore"]->db->updated($table->info["table"]);
			//}
		}*/
	}
	
	function processCustomFieldUpdate($info,$n,&$data) {
		$v = $data[$n];
		
		$field = $info["fields"][$n];
		$ar = array($v,&$data,$info);
		
		if (isset($info["_O"])) {
			$method = "update_".$n;
			if (method_exists($info["_O"],$method)) call_user_func_array(array($info["_O"],$method),$ar);
		}
	}
	
	function insert($table,$data,$find = false, $options = array("select"=>"id")) {
		$table = $this->getTable($table);
				
		$p = array_pop(explode("/",$table->info["table"]));

		if ($cField = $this->getFieldByType('created')) {
			$data[$cField[0]] = time();
		}
		
		uasort($table->info["fields"], array('slDB', "fieldSort"));  
		
		foreach ($table->info["fields"] as $fn=>$field) {
			if (!isset($data[$fn]) && $field["depLevel"] > 0) {
				if (isset($field["type"]) && $field["type"] == "placeholder") {
					$data[$fn] = $_KEY;
				} else {
					$data[$fn] = "";
				}
			}
			if (isset($data[$fn])) $this->processCustomFieldUpdate($table->info,$fn,$data);
			
		}
	
		if (isset($data["_KEY"])) unset($data["_KEY"]);

		//TODO: send to server
		
		$GLOBALS["slCore"]->nonUserSpecificDispatch("change-".$table->info["table"]."/".$_KEY,$data);
		
		$GLOBALS["slCore"]->db->updated($table->info["table"]);

		return $_KEY;
	}
	
	public function safe($v, $noQuotes = false, $conn = false) {
		return $v; //TODO ???
	}
					
	function getTableKey($table) {
		return $this->info["key"];
	}
	
	function getFieldByType($type) {
		$rv = array();
		foreach ($this->info["fields"] as $n=>$field) {
			if (isset($field["type"]) && $field["type"] === $type) {
				$rv[] = $n;
			}
		}
		return count($rv) ? $rv : null;
	}
	
	function preventUpdateEvents($table) {
		$this->noUpdateEvents = true;
	}
	
	function begin($table) {
		$this->inTransaction = true;
	}
	
	function commit($table) {				
		$this->inTransaction = false;
	}
}
