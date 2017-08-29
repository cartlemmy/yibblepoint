<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valuePhone extends slValueDefinition {
	private $type;
	private $rawValue;
	private $def;
	public $value;
	private $options = array(
		"main"=>"en-us|Main",
		"mobile"=>"en-us|Mobile",
		"work"=>"en-us|Work",
		"home"=>"en-us|Home",
		"home-fax"=>"en-us|Home Fax",
		"work-fax"=>"en-us|Work Fax",
		"pager"=>"en-us|Pager",
		"emergency"=>"en-us|Emergency"
	);
	
	function toString() {
		$p = explode(";",$this->value->value);
		if (count($p) > 1) {
			return isset($this->options[$p[1]]) ? $this->options[$p[1]].": ".$p[0] : $p[0];
		} else return $this->value->value;
	}
	
	function fromString($string) {	
		
	}
}
