<?php

require_once(SL_INCLUDE_PATH."/handlers/api/class.API.php");

class ypSyncer {
	private $info;
	private $table;
	private $tableInfo;
	private $dontUse = array("broadSearch","history","_KEY","_UNIQUE","_INSERTED","id","userId");
	private $syncStack = array();
	
	public function __construct($info, $table) {
		$this->info = $info;
		$this->table = $table;
		$this->tableInfo = $GLOBALS["slCore"]->db->getTableInfo($table);
	}
	
	public function update($old,$new) {		
		if (setAndTrue($new,"syncId")) {
			$syncId = $new["syncId"];
		} elseif (setAndTrue($old,"syncId")) {
			$syncId = $old["syncId"];
		} else $syncId = 0;
		
		if (!$syncId && !isset($this->tableInfo["unique"])) return false;
		
		$uniqueField = $this->tableInfo["unique"];
		if (setAndTrue($new,$uniqueField)) {
			$uniqueId = $new[$uniqueField];
		} elseif (setAndTrue($old,$uniqueField)) {
			$uniqueId = $old[$uniqueField];
		} else return false;
		
		if (setAndTrue($new,"_KEY")) {
			$localId = $new["_KEY"];
		} elseif (setAndTrue($old,"_KEY")) {
			$localId = $old["_KEY"];
		} else $localId = 0;
		
		if (isset($new["DELETE"])) {
			$changed = array("DELETE"=>1,"DELETED_".preg_replace('/[^A-Za-z0-9]+/','_',$this->info["local"])=>time());
		} elseif (!$syncId) {
			$changed = array_merge($old,$new);
			$this->prepData($changed);
		} else {
			$this->prepData($old);
			$this->prepData($new);
			
			$changed = array();
			foreach ($new as $n=>$v) {
				if (!isset($old[$n]) || $v != $old[$n]) $changed[$n] = $v;
			}
		}
			
		if (count($changed)) {
			$this->syncStack[] = array($syncId, $uniqueId, $changed, $localId);
			return true;
		}
		return false;
	}
	
	private function prepData(&$data) {
		foreach ($this->dontUse as $n) {
			if (isset($data[$n])) unset($data[$n]);
		}
	}
	
	public function commit() {		
		if (!$this->syncStack) return array("success"=>true,"res"=>"Nothing to commit");
		$req = new APIRequest($this->info["host"], $this->info["key"]);
		
		$res = $req->UPDATE('data',$this->table,$this->syncStack,$this->info["group"]);
		
		if ($res && $res["success"]) {
			foreach ($res["res"] as $record) {
				if (setAndTrue($record,"updateSyncId")) {
					if (isset($record["back"])) {
						$record["back"]["syncId"] = $record["syncId"];
						$record["back"]["_NO_SYNC"] = true;
						$GLOBALS["slCore"]->db->update($this->table,$record["back"],array("_KEY"=>$record["_KEY"]));
					} else {
						$GLOBALS["slCore"]->db->update($this->table,array("syncId"=>$record["syncId"],array("_NO_SYNC"=>true)),array("_KEY"=>$record["_KEY"]));
					}
				}
			}
		}
		return $res;
	}
}
