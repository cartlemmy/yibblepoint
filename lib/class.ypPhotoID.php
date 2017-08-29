<?php

class ypPhotoID {
	private $colors = array(0xFF00CC,0xCCFF00,0x00CCFF);
	private $id = "";
	private $img;
	
	private $dpi = 50;
	
	private $text = array(false,false,false);
	
	public $barWidth = "0.25in";
	public $addParity = false;
	
	public function __construct($id = false) {
		if ($id) $this->setID($id);	
	}
	
	public function setName($name) {
		$this->text[0] = $name;
	}
	
	public function setUID($uid) {
		$this->text[1] = $uid;
	}
	
	public function setRange($start, $end) {
		$this->text[2] = "Valid ".date("n/j/Y",$start)." to ".date("n/j/Y",$end);
	}
	
	public function setID($id) {
		if (is_string($id)) {
			$this->id = bin2hex($id);
		} elseif (is_numeric($id)) {
			$this->id = dechex($id);
		}
	}
	
	public static function BIT($hex, $i) {
		$c = hexdec(substr($hex, $i >> 2, 1));
		return !!($c & pow(2,$i & 3));
	}
	
	private function imageCreate($x, $y) {
		$this->im = imagecreatetruecolor($x, $y);
	}
	
	private function colorallocate($c) {
		return imagecolorallocate($this->im, ($c >> 16) & 255, ($c >> 8) & 255, $c & 255); 
	}
	
	private function toPx($u) {
		if (substr($u,-2) == "in") return substr($u,0,-2) * $this->dpi;
		if (substr($u,-2) == "pt") return substr($u,0,-2) * $this->dpi / 72;
		return (int)$u;
	}
	
	public static function encodeParity($hex, $perBits = 3) {
		$b = base_convert($this->id,16,2);
		$p = "";
		for ($i = 0; $i < strlen($b); $i += $perBits) {
			$par = 0;
			for ($j = 0; $j < $perBits; $j++) {
				$c = substr($b,$i+$j,1);
				$p .= $c;
				$par = $par ^ (int)$c;
			}
			$p .= "".$par;
		}
		return base_convert($p.str_repeat("0",3-((strlen($p)-1)&3)),2,16);
	}
	
	public static function decodeParity($hex, $perBits = 3) {
		//TODO
	}
	
	public function out($filename = false, $width = "0.5in", $length = "6.5in") {
		$sx = $this->toPx($length);
		$sy = $this->toPx($width);
		$step = $this->toPx($this->barWidth);
		
		$enc = $this->addParity ? self::encodeParity($this->id) : $this->id;
		
		$this->imageCreate($sx, $sy);
		
		$colors = array();
		foreach ($this->colors as $c) {
			$colors[] = $this->colorallocate($c);
		}
		$colors[] = $this->colorallocate(0xFFFFFF);
		
		$pos = 0; $ci = 0;
		
		for ($x = 0; $x < $sx; $x += $step) {
			if ($pos >= 0) {
				if ($pos == 0) $ci = 0;
				$ci = ($ci + (self::BIT($enc,$pos) ? -1 : 1) + 3) % 3;
			} else {
				$ci = $pos == -2 ? ($this->addParity ? 2 : 1) : 3;
			}
			
			imagefilledrectangle($this->im, $x, 0, $x + $step - 1, $sy,  $colors[$ci]);
			
			$pos ++;
			if ($pos > strlen($enc) * 4) $pos = -2;
		}

		if (!$filename) $filename = SL_WEB_PATH."/img/photo-id/".$this->id.".png";

		imagepng($this->im, $filename);
		imagedestroy($this->im);
		
		echo '<div class="id-bars" style="width:'.$length.';height:'.$width.'">';
		echo '<img src="'.webPath($filename).'" style="width:'.$length.';height:'.$width.'">';
		
		$h = ($sy / (count($this->text) + 1) / $this->dpi);
		echo '<div style="height:'.($h/2).'in"></div>';
		for ($i = 0; $i < count($this->text); $i++) {
			echo '<label style="height:'.$h.'in;line-height:'.($h * 0.8).'in;font-size:'.($h * 0.8).'in">'.$this->text[$i].'</label>';
		}
		echo '</div>';
		
	}
}
