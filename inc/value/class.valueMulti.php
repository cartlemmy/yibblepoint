<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueMulti extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	private $valueObs = array();
	
	function __construct($value) {
		$this->value = $value;
		$this->setValue($this->value->value);
	}
	
	function setValue($value) {
		$value = explode("\n",$value);
		foreach ($value as $n=>$v) {
			$v = str_replace("%OA","\n",$v);
			if (!isset($this->valueObs[$n])) {
				$this->valueObs[$n] = new slValue($this->def,$this->value->name,$v);
			} else {
				$this->valueObs[$n]->setValue($v);
			}			
		}
		while (count($value) > count($this->valueObs)) {
			array_pop($this->valueObs);
		}
	}
	
	function toString() {
		$rv = array();
		foreach ($this->valueObs as $ob) {
			$rv[] = $ob->toString(); 
		}
		return implode("\n",$rv);
	}
	
	function fromString($string) {
		/*$this->value->value = strtotime($string);*/
	}
}
