<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueSelect extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	public $format = "date";
	
	function toString() {
		return isset($this->value->fieldDesc["options"][$this->value->value]) ? $this->value->fieldDesc["options"][$this->value->value] : $this->value->value;
	}
	
	function fromString($string) {	
		foreach ($this->value->fieldDesc["options"] as $n=>$v) {
			if (safeName($string) == safeName($n) || safeName($string) == safeName($v)) {
				$this->value->value = $n;
			}
		}
		$this->value->value = "";
	}
}
