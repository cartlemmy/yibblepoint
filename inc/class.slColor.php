<?php

class slColor {
	private $r = 0;
	private $g = 0;
	private $b = 0;
	private $a = 255;
	
	function __construct($color) {
		if ($color{0} == "#") {
			if (strlen($color) == 4) {
				$oc = substr($color,1);
				$color = "#";
				for ($i = 0; $i < 3; $i++) {
					$color .= $oc{$i}.$oc{$i};
				}
			}
			$this->r = hexdec(substr($color,1,2));
			$this->g = hexdec(substr($color,3,2));
			$this->b = hexdec(substr($color,5,2));
		}
	}
	
	function contrasting() {
		return $this->r + $this->g + $this->b > 384 ? "#000000" : "#ffffff"; 
	}
	
	function hex($v,$len) {
		$v = dechex($v);
		while (strlen($v) < $len) {
			$v = "0".$len;
		}
		return substr($v,0,$len);
	}
	
	function longhand() {
		return "#".$this->hex($this->r,2).$this->hex($this->g,2).$this->hex($this->b,2);
	}

	function shorthand() {
		return "#".$this->hex($this->r,1).$this->hex($this->g,1).$this->hex($this->b,1);
	}
}
