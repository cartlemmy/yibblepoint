<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueAddress extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	private $partNames = array("address","type","lat","lng","timezone");
	private $options = array(
			"home"=>"en-us|Home",
			"work"=>"en-us|Work",
			"shipping"=>"en-us|Shipping",
			"billing"=>"en-us|Billing",
			"vacation"=>"en-us|Vacation"
	);
	
	function toString() {
		$o = delimToObject($this->value->value,$this->partNames);

		$address = array();
		$o["address"] = explode(",",$o["address"]);
		foreach ($o["address"] as $a) {
			if (trim($a) != "") $address[] = trim($a);
		}
		$address = implode(", ",$address);
		
		return isset($this->options[$o["type"]]) ? $this->options[$o["type"]].": ".$address : $address;
		
	}
	
	function fromString($string) {	
		
	}
}
