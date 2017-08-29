<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueDateRange extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	public $format = "date";
	
	function toString() {
		$range = explode("-",$this->value->value);
		if (setAndTrue($range,0) && setAndTrue($range,1)) {
			return date($GLOBALS["slConfig"]["international"][$this->format],$range[0])." to ".date($GLOBALS["slConfig"]["international"][$this->format],$range[1]);
		} elseif (setAndTrue($range,0)) {
			return "After ".date($GLOBALS["slConfig"]["international"][$this->format],$range[0]);
		} elseif (setAndTrue($range,1)) {
			return "Before ".date($GLOBALS["slConfig"]["international"][$this->format],$range[1]);
		}
		return "Any";
	}
	
	function fromString($string) {	
		//TODO:
		//$this->value->value = ???;
	}
	
	function add($value) {
		//TODO
	}
	
	function getQuery() {
		$where = array();
		$range = explode("-",$this->value->value);
		if (setAndTrue($range,0)) $where[] = "`".$this->value->name."`>=".(int)$this->value->value;
		if (setAndTrue($range,1)) $where[] = "`".$this->value->name."`<".ceil($this->value->value);
		
		if ($where) return "(".implode(" AND ", $where).")";
		
		return false;
	}
	
	function sqlValue() {
		if (method_exists($this->def,'sqlValue')) return $this->def->sqlValue();
		return "'".$GLOBALS["slCore"]->db->safe("db/",$this->value)."'";
	}

}
