<?php

class slValue extends slClass {	
	public $desc;
	public $fieldDesc;
	public $table;
	public $value;
	public $name;
	public $def;
	private $includeFile;
	private $multi = false;
	
	function __construct($table,$name = false,$value = false) {
		$this->table = is_array($table) ? false : $table;

		$this->name = $name;
		if ($this->table === false) {
			$this->desc = $table;
		} else {
			$this->desc = $GLOBALS["slCore"]->db->getTableInfo($table);
		}
		$this->value = $value;
		
		if (isset($this->desc["fields"])) {
			$this->fieldDesc = isset($this->desc["fields"][$name]) ? $this->desc["fields"][$name] : false;
		} else {
			$this->fieldDesc = $this->desc;
			$this->desc = null;
		}
		
		if (setAndTrue($this->fieldDesc,"multi")) {
			$this->multi = true;
			
			require_once(SL_INCLUDE_PATH."/value/class.valueMulti.php");
			$this->def = new valueMulti($this);
			
		} else {
			$convert = array("objectDropDown"=>"object");
			if (isset($convert[$this->fieldDesc["type"]])) $this->fieldDesc["type"] = $convert[$this->fieldDesc["type"]];
			
			$className = toCamelCase("value-".$this->fieldDesc["type"]);
			$this->includeFile = SL_INCLUDE_PATH."/value/class.".$className.".php";

			if (is_file($this->includeFile)) {
				require_once($this->includeFile);
				$this->def = new $className($this);
			} else {
				$this->def = new slValueDefinition($this);
			}
		}
	}
	
	function update() {
		$this->def->update();
	}
	
	function toString() {
		return $this->def->toString();
	}
	
	function fromString($string) {
		return $this->def->fromString($string);
	}
	
	function add($value) {
		$this->def->add($value);
	}
	
	function multiply($value) {
		$this->def->multiply($value);
	}
	
	function round() {
		$this->def->round();
	}
	
	function getQuery() {
		if (method_exists($this->def,'getQuery')) return $this->def->getQuery();
		if ($this->value) {
			return "`".$this->name."`=".$this->sqlValue();
		}
		return false;
	}
	
	function sqlValue() {
		if (method_exists($this->def,'sqlValue')) return $this->def->sqlValue();
		return "'".$GLOBALS["slCore"]->db->safe("db/",$this->value)."'";
	}
	
	function getFloat() {
		if (method_exists($this->def,'getFloat')) return $this->def->getFloat();
		return (float)$this->value;
	}
}

class slValueDefinition {
	public $value;
	function __construct($value) {
		$this->setValue($value);
	}
	
	function setValue($value) {
		$this->value = $value;
	}
	
	function add($value) {
		$this->value += is_object($value) ? $value->value->value : (float)$value;
	}
	
	function multiply($value) {
		$this->value *= is_object($value) ? $value->value->value : (float)$value;
	}
	
	function round() {
		$this->value = round($this->value);
	}
	
	function toString() {
		return $this->value->value;
	}
	
	function fromString($string) {
		$this->value->value = $string;
	}
}

function valueToString($v,$type,$name = false) {
	$name = false;
	if (!is_array($type) && strpos($type,"/") !== false) {
		$type = explode("/",$type);
		$name = array_pop($type);
		$desc = implode("/",$type);
	} else {
		$desc = is_array($type) ? $type : array("type"=>$type);
	}
	$val = new slValue($desc, $name, $v);
	return translate($val->toString());
}
