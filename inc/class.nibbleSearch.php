<?php

class nibbleSearch {
	private $fromLetter = 'etaoinshrdlcumwfgypbvkjxqz';
	private $toNibble = 'abcde0123456789abcde012345';
	private $abbreviations = array();
	
	public function __construct() {
		$file = SL_INCLUDE_PATH."/data/abbreviations/".$slConfig["international"]["language"];
		if (is_file($file)) {
			$abbr = explode("\n",file_get_contents($file));
			$abbr[0] = explode(",",$abbr[0]);
			$abbr[1] = explode(",",$abbr[1]);
			$this->abbreviations = array_combine($abbr[0],$abbr[1]);
		}
	}
}
