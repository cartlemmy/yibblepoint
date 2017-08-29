<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueDate extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	public $format = "date";
	
	function toString() {
		if (isset($this->value->fieldDesc["format"])) $this->format = $this->value->fieldDesc["format"];
		return $this->value->value == 0 ? "N/A" : date($GLOBALS["slConfig"]["international"][$this->format],$this->value->value);
	}
	
	function fromString($string) {	
		$this->value->value = strtotime($string);
	}
	
	function add($value) {
		//TODO
	}

}
