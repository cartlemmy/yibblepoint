<?php

$GLOBALS["currencyDef"] = require(SL_INCLUDE_PATH."/data/currency/out.php");
require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueCurrency extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	
	function update() {		
		$value = explode(" ",$this->value->value,2);
		$this->type = array_pop($value);
		$this->rawValue = count($value) ? (float)$value[0] : 0;

		if (!isset($GLOBALS["currencyDef"][$this->type])) $this->type = $GLOBALS["slConfig"]["international"]["currency"];

		$this->def = $GLOBALS["currencyDef"][$this->type];
	}
	
	function toString() {
		if (!$this->value->value) return "";
				
		$this->update();
		
		if ($this->type == "_YC") return $this->rawValue+"en-us| Credit".($this->rawValue==1?"":"s");
		return $this->def[3].sprintf("%01.".$this->def[1]."f", $this->rawValue)." ".$this->type;
	}
	
	function fromString($string) {
		$match = array();
		
		if (preg_match("/[\w]{3}/",$string,$match)) {
			$this->type = strtoupper($match[0]);
		} else $this->type = $GLOBALS["slConfig"]["international"]["currency"];	

		if (preg_match("/[\d\.\-\(\)\,]+/",$string,$match)) {
			$this->rawValue = $match[0];
		} else return;
				
		$this->def = $GLOBALS["currencyDef"][$this->type];

		if (preg_match_all("/(\.|\,)/",$this->def[2],$match)) {
			$cs = $match[0][0] == ",";	
		} else $cs = false;
		
		$this->rawValue = implode("",explode($cs?",":".",$this->rawValue));

		if ($cs == ".") $this->rawValue = implode(".",explode(",",$this->rawValue));
		
		if (strpos($this->rawValue,"(") !== false) {
			$this->rawValue = "-".str_replace(array("(",")"),array("",""),$this->rawValue);
		}
				
		$this->value->value = $this->rawValue." ".$this->type;
	}
	
	function add($value) {
		$this->setFloat((is_object($value) ? $value->getFloat() : (float)$value) + $this->getFloat());
	}
	
	function multiply($value) {
		$this->setFloat($this->getFloat() * $value);
	}
	
	function round() {
		$amt = pow(10,$this->def[1]);
		$this->setFloat(round($this->getFloat() * $amt) / $amt);
	}
	
	function getFloat() {
		$this->update();
		return $this->rawValue * $this->def[4];
	}
	
	function setFloat($value) {
		$this->update();
		$this->rawValue = $value / $this->def[4];
		$this->value->value = $this->rawValue." ".$this->type;
	}
	
	public static function convert($from,$to) {
		$from = explode(" ",$from,2);
		if (isset($GLOBALS["currencyDef"][strtoupper($from[1])])) {
			$fromDef = $GLOBALS["currencyDef"][strtoupper($from[1])];
			if (isset($GLOBALS["currencyDef"][strtoupper($to)])) {
				$toDef = $GLOBALS["currencyDef"][strtoupper($to)];
				return ($from[0] * $fromDef[4]) / $toDef[4];
			}
		}
		return false;
	}
}
