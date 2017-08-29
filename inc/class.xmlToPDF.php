<?php

if (function_exists('requireThirdParty') && requireThirdParty('fpdf')) { 
	//Yut yay!
} elseif (is_file(dirname(__FILE__).'/fpdf/fpdf.php')) {
	require_once(dirname(__FILE__).'/fpdf/fpdf.php');
}

class xmlToPDF {
	private $xml;
	public $pdf;
	
	private $stack = array();
	
	private $currentStyle = array();
	private $defaultOrientation;
	private $defaultSize;
	public $unit;
	
	private $conversion = array(
		"pt"=>1,
		"in"=>72,
		"mm"=>2.83465,
		"cm"=>28.3465
	);
	
	public function fromFile($file) {
		$this->xml = simplexml_load_file($file);
				
		$this->reset();
		
		$this->parse();
	}
	
	public function fromXML($xml) {
		$this->xml = simplexml_load_string($xml);
				
		$this->reset();
		
		$this->parse();
	}
	
	private function reset() {
		$this->styleStack = array();
		$this->pdf = null;
	}	
	
	public function setStyle($style, $dontApply = false) {
		$changed = array();
		if (!is_array($style)) return;
		foreach ($style as $n=>$v) {
			switch ($n) {
				case "topmargin":
					$this->pdf->SetTopMargin((float)$v);
					break;
				
				case "leftmargin":
					$this->pdf->SetLeftMargin((float)$v);
					break;
				
				case "rightmargin":
					$this->pdf->SetRightMargin((float)$v);
					break;
				
				case "bottommargin":
					break;
						
				case "fontfamily": case "fontstyle": case "fontsize":
					$changed["font"] = 1;
					break;
				
				case "bgcolor":
					$changed["bgcolor"] = 1;
					break;
				
				case "drawcolor":
					$changed["drawcolor"] = 1;
					break;
				
				case "textcolor":
					$changed["textcolor"] = 1;
					break;
			
				case "linewidth":
					$changed["linewidth"] = 1;
					break;
				
				case "lineheight":
					$v = (float)$v;
					break;
							
				case "contwidth": case "contheight":
					break;
					
				default:
					continue 2;
			}

			$this->currentStyle[$n] = $v;
		}
		
		if ($dontApply || !$this->pdf) return;
		
		if (isset($changed["font"])) $this->pdf->SetFont( $this->currentStyle["fontfamily"], $this->currentStyle["fontstyle"], $this->currentStyle["fontsize"]);
		
		if (isset($changed["linewidth"])) $this->pdf->SetLineWidth($this->currentStyle["linewidth"]);
		
		if (isset($changed["drawcolor"])) {
			$rgb = $this->COLOR($this->currentStyle["drawcolor"]);
			$this->pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
		}

		if (isset($changed["textcolor"])) {
			$rgb = $this->COLOR($this->currentStyle["textcolor"]);
			$this->pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
		}
		
		if (isset($changed["bgcolor"])) {
			$rgb = $this->COLOR($this->currentStyle["bgcolor"]);
			$this->pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
		}
	}
	
	private function parse($xml = false) {
		if ($xml === false) $xml = $this->xml;
		$tag = $xml->getName();
		$method = self::toCamelCase('tag-'.$tag);
		
		$this->stack[] = array("tag"=>$tag);
		
		$oldStyle = $this->currentStyle;
		
		$this->setStyle($this->PARAMS($xml));
		
		if ($this->pdf && $this->pdf->GetX()) {			
			$this->setStyle(array(
				'contwidth'=>($this->pdf->GetPageWidth() - $this->currentStyle["rightmargin"]) - $this->pdf->GetX(),
				'contheight'=>($this->pdf->GetPageHeight() - $this->currentStyle["bottommargin"]) - $this->pdf->GetY()
			));
		}
		
		if (method_exists($this,$method)) {
			call_user_func(array($this,$method), $xml);
		} else $this->fail('<'.$tag.'> ('.$method.') is not a valid tag');
				
		foreach ($xml->children() as $c) {
			$this->parse($c);
		}
		
		$i = count($this->stack) - 1;
		if (isset($this->stack[$i]["gen"])) $this->stack[$i]["gen"]->finish();
		
		$this->setStyle($oldStyle);
		
		array_pop($this->stack);
	}
	
	private static function toCamelCase($string,$firstUpper = false,$allowFirstCharNum = false) {
		$v = explode("-",preg_replace("/[^\w\d]+/","-",$string));
		$rv = array();
		for ($i = 0; $i < count($v); $i ++) {
			if (!$firstUpper && $i == 0) {
				$rv[] = strtolower($v[$i]);
			} else {
				$rv[] = ucfirst(strtolower($v[$i]));
			}
		}
		$rv = implode("",$rv);
		if (!$allowFirstCharNum && preg_replace("/[\d]/","",substr($rv,0,1)) == '') {
			$rv = "_"+$rv;
		}
		return $rv;
	}
	
	private function fail($txt) {
		echo htmlspecialchars($txt); exit();
	}
	
	private function tagDocument($xml) {
		$params = $this->PARAMS($xml, array(
			"orientation"=>"P",
			"unit"=>"mm",
			"size"=>"A4"
		));
		
		$this->defaultOrientation = $params["orientation"];
		$this->defaultSize = $params["size"];
		$this->unit = $params["unit"];
			
		$this->pdf = new FPDF($params["orientation"],$params["unit"],$params["size"]);
		
		$this->setStyle(array(
			"topmargin"=>0.25,
			"leftmargin"=>0.25,
			"rightmargin"=>0.25,
			"bottommargin"=>0.25,
			"fontfamily"=>"Arial",
			"fontstyle"=>"",
			"fontsize"=>"12",
			"lineheight"=>"1.2",
			"linewidth"=>$this->CONV(0.2, "mm"),
			"drawcolor"=>"#000",
			"textcolor"=>"#000",
			"bgcolor"=>"transparent" 	
		), true);
	}
	
	private function tagPage($xml) {
		$params = $this->PARAMS($xml, array(
			"orientation"=>$this->defaultOrientation,
			"size"=>$this->defaultSize,
			"rotation"=>"0"
		));
		$this->pdf->AddPage($params["orientation"], $params["size"], (int)$params["rotation"]);		
	}
	
	private function tagBlock($xml) {
		return $this->tagCell($xml, "1");
	}
	
	private function tagCell($xml, $ln = "0") {
		$params = $this->PARAMS($xml, array(
			"width"=>null,
			"height"=>$this->CONV($this->currentStyle["fontsize"],"pt",$this->unit) * $this->currentStyle["lineheight"],
			"border"=>"0",
			"ln"=>$ln,
			"align"=>"L",
			"fill"=>"0"
		));
		
		if ((string)$xml === "") return;
		
		if ($params["width"] === null) $params["width"] = $this->pdf->GetStringWidth((string)$xml);
		
		$this->pdf->Cell(
			(float)$params["width"],
			(float)$params["height"],
			(string)$xml,
			$params["border"],
			(int)$params["ln"],
			$params["align"],
			!!$params["fill"]
		);
	}
	
	private function tagBr($xml) {
		$params = $this->PARAMS($xml, array(
			"h"=>null
		));
		
		$this->pdf->Ln($params["h"] !== null ? (float)$params["h"] : null);
	}
	
	private function tagTable($xml) {
		$params = $this->PARAMS($xml);
		
		if (!$params["width"]) $params["width"] = $this->currentStyle["contwidth"];
		 
		$this->setStack(true, "gen", new xmlToPDFTable($this, $params, $this->CONV($this->currentStyle["fontsize"],"pt",$this->unit) * $this->currentStyle["lineheight"]));
	}
	
	private function tagTr($xml) {
		$table = $this->getStack("table","gen");
		$table->addTr($xml);
	}
	
	private function tagTd($xml) {
		$table = $this->getStack("table","gen");
		$table->addTd($xml, $this->currentStyle);
	}
	
	private function tagB($xml) {
		$this->addFontStyle("B");
		$this->tagCell($xml);
	}
	
	private function tagI($xml) {
		$this->addFontStyle("I");
		$this->tagCell($xml);
	}
	
	private function tagU($xml) {
		$this->addFontStyle("U");
		$this->tagCell($xml);
	}
	
	private function addFontStyle($s) {
		$s = strtoupper(substr($s,0,1));
		if (strpos("BIU") === false) return;
		$oldStyle = str_split($this->currentStyle['fontstyle']);
		if (!in_array($s, $oldStyle)) $oldStyle[] = $s;
		$this->setStyle(array("fontstyle"=>implode("",$oldStyle)));
	}
	
	private function setStack($level, $n, $v) {
		if (($i = $this->getStackOb($level)) === null) return null;
		$this->stack[$i][$n] = $v;
	}
	
	private function getStack($level, $n) {
		if (($i = $this->getStackOb($level)) === null) return null;
		return $this->stack[$i][$n];
	}
	
	private function getStackOb($level) {
		if ($level === true) return count($this->stack) - 1;
		$i = count($this->stack) - 1;
		while ($i >= 0) {
			if ($this->stack[$i]["tag"] == $level) return $i;
			$i --;
		}
		return null;
	}
	
	public function out() {
		$this->pdf->Output();
	}
	
	public function PARAMS($xml, $possible = false, $noDef = false) {
		$rv = array();
		if ($possible === false) {
			$noDef = true;
			$possible = array(
				"border","fontfamily","fontsize","fontstyle","width",
				"height","padding","colspan","rowspan","bgcolor",
				"drawcolor","textcolor","topmargin","leftmargin",
				"rightmargin","bottommargin","linewidth"
			);
		}
		if ($noDef) {
			foreach ($possible as $n) {
				if (isset($xml[$n])) $rv[$n] = $this->CONVSTRING((string)$xml[$n]);
			}
		} else {
			foreach ($possible as $n=>$v) {
				$rv[$n] = isset($xml[$n]) ? $this->CONVSTRING((string)$xml[$n]) : $v;
			}
		}
		return $rv;
	}
	
	private function TOSTACK($xml) {
		return $this->PARAMS($xml);
	}
	
	public function CONVSTRING($s) {
		if (preg_match('/([\d\.]+)(pt|in|mm|cm)/', $s, $match)) {
			return $this->CONV($match[1], $match[2]);	
		}
		return $s;
	}
	
	public function CONV($v, $from, $to = false) {
		if ($to === false) $to = $this->unit;
		return $v * $this->conversion[$from] / $this->conversion[$to];
	}
	
	private function COLOR($c) {
		$rgb = array(255, 255, 255);
		if (substr($c,0,1) == "#") {
			$c = substr($c,1);
			if (strlen($c) == 3) $c = substr($c,0,1).substr($c,0,1).substr($c,1,1).substr($c,1,1).substr($c,2,1).substr($c,2,1);
			if (strlen($c) == 6) {
				$rgb[0] = hexdec(substr($c,0,2));
				$rgb[1] = hexdec(substr($c,2,2));
				$rgb[2] = hexdec(substr($c,4,2));
			}
		}
		return $rgb;
	}
	
	public static function createValidPHPFromXML($fromFile, $toFile = false) {
		if ($toFile === false) $toFile = str_replace('.php', '.p.php', $fromFile);
		if (!is_file($toFile) || filemtime($toFile) < filemtime($fromFile)) {
			$c = file_get_contents($fromFile);
			if (($pos = strpos($c, '?>')) !== false) {
				$pos += 2;
				file_put_contents($toFile,
					"<?php\n\n/* THIS FILE IS DYNAMICALLY GENERATED".
					"\n * Please edit $fromFile instead\n */\n\n".
					'echo '.var_export(substr($c, 0, $pos), true).'; ?>'.
					substr($c, $pos)
				);				
			} else return $fromFile;
		}
		return $toFile;
	}			

}

class xmlToPDFTable {
	private $rowHeights = array();
	private $fixedHeight = array();
	private $colWidths = array();
	private $cells = array();
	private $cols = 0;
	private $x = -1;
	private $y = -1;
	public $posX = 0;
	public $posY = 0;
	public $params = array();
	public $x2p;
	public $defHeight = 0;
	
	public function __construct($x2p, $params, $defHeight) {
		if (!isset($params["width"])) $params["width"] = 6; //TODO: set from parent width
		$this->x2p = $x2p;
		$this->params = $params;
		$this->defHeight = $defHeight;
		
		$x2p->pdf->Ln();
		
		$this->posX = $x2p->pdf->GetX();
		$this->posY = $x2p->pdf->GetY();
	}
	
	public function addTr($xml) {
		$params = $this->x2p->PARAMS($xml);
		
		$this->y ++;
		$this->x = 0;
		
		$this->rowHeights[] = isset($params["height"]) ? (float)$params["height"] : $this->defHeight + (isset($this->params["padding"]) ? $this->params["padding"] * 2 : 0);
		$this->fixedHeight[] = isset($params["height"]);
		$this->cells[] = array();	
	}
	
	public function addTd($xml, $style) {
		$lineHeight = $this->x2p->CONV($style["fontsize"],"pt",$this->x2p->unit) * $style["lineheight"];
		
		$params = $this->x2p->PARAMS($xml);
	
		
		$this->x = 0;
		while ($this->cells[$this->y][$this->x]) {
			$this->x++;
		}
		
		$cell = new xmlToPDFTableCell($this, $xml);;
		$cell->set("lineHeight", $lineHeight);
		$cell->set("style", $style);
		if ($this->fixedHeight[$this->y]) $cell->set("fixedHeight", $this->rowHeights[$this->y]);
		
		$this->cells[$this->y][$this->x] = $cell;
		
		$this->cols = max($this->x + 1, $this->cols);
		while (count($this->colWidths) < $this->cols) {
			$this->colWidths[] = null;
		}
		
		if (isset($params["colspan"])) {
			for ($i = 1; $i < (int)$params["colspan"]; $i++) {
				$this->cells[$this->y][$this->x + $i] = true;
			}
		} elseif (isset($params["width"])) $this->colWidths[$this->x] = $params["width"];
		
		if (isset($params["rowspan"])) {
			for ($i = 1; $i < (int)$params["rowspan"]; $i++) {
				if (!isset($this->cells[$this->y])) $this->cells[$this->y + $i] = array();
				for ($j = 0; $j < $this->x; $j++) {
					if (!isset($this->cells[$this->y + $i][$j])) $this->cells[$this->y + $i][$j] = null;
				}
				$this->cells[$this->y + $i][$this->x] = true;
			}
		}		
	}
	
	public function finish() {
		$remWidth = $this->params["width"];
		$remCols = $this->cols;
		for ($i = 0; $i < $this->cols; $i++) {
			if (substr($this->colWidths[$i], -1) == "%") {
				$this->colWidths[$i] = $this->params["width"] * (substr($this->colWidths[$i], 0, -1) / 100);
			}
			
			if (is_numeric($this->colWidths[$i])) {
				$remWidth -= $this->colWidths[$i];
				$remCols --;
			}			
		}
		
		for ($i = 0; $i < $this->cols; $i++) {
			if ($this->colWidths[$i] === null) {
				$this->colWidths[$i] = $remWidth / $remCols;
			}
		}
		
		foreach ($this->cells as &$row) {
			while (count($row) < $this->x) {
				$row[] = new xmlToPDFTableCell($this, false);
			}
		}
		unset($row);
		
		$mutiRowed = array();
		foreach ($this->cells as $ri=>$row) {
			$h = $this->rowHeights[$ri];
			foreach ($row as $ci=>$cell) {
				if (!is_object($cell)) continue;
				$w = 0; $cs = max(1,isset($cell->params["colspan"]) ? (int)$cell->params["colspan"] : 1);
				for ($i = 0; $i < $cs; $i++) {
					$w += $this->colWidths[$ci + $i];
				}				
				$cell->set("width",$w);
				$ch = $cell->getHeight();

				if (isset($cell->params["rowspan"]) && (int)$cell->params["rowspan"] > 1) {
					$mutiRowed[] = array($ri, $ci, (int)$cell->params["rowspan"], $ch);
				} else {
					$h = max($h, $ch);
				}
			}
			$this->rowHeights[$ri] = $h;
		}
		
		foreach ($mutiRowed as $row) {
			list($ri, $ci, $rs, $ch) = $row;
			$oldH = 0;
			for ($i = 0; $i < $rs; $i++) {
				$oldH += $this->rowHeights[$ri + $i];
			}

			$add = ($ch - $oldH) / $rs;
			if ($add > 0) {
				for ($i = 0; $i < $rs; $i++) {
					$this->rowHeights[$ri + $i] += $add;
				}
			}
		}

		$y = $this->posY;
		foreach ($this->cells as $ri=>$row) {
			$h = $this->rowHeights[$ri];
			$x = $this->posX;
			for ($ci = 0; $ci < count($row); ) {
				$cell = $row[$ci];
				if (is_object($cell)) {
					$cell->set("x",$x);
					$cell->set("y",$y);
					$th = $h;
					if (isset($cell->params["rowspan"])) {
						for ($i = 1; $i < $cell->params["rowspan"]; $i++) {
							$th += $this->rowHeights[$ri + $i];
						}
					}
					$cell->set("height", $th);
					$cell->render();
				}
				$cs = max(1,isset($cell->params["colspan"]) ? (int)$cell->params["colspan"] : 1);
				for ($i = 0; $i < $cs; $i++) {
					$x += $this->colWidths[$ci];
					$ci++;
				}

			}
			$y += $h;
		}
	}
}

class xmlToPDFTableCell {
	public $table;
	public $params = array(
		"width"=>0,
		"height"=>0,
		"border"=>""
	);
	public $content;
	
	public function __construct($table, $xml) {
		$this->table = $table;
		$this->content = str_replace('\\n',"\n",(string)$xml);
		$params = $this->table->x2p->PARAMS($xml);
		
		foreach ($table->params as $n=>$v) {
			$this->params[$n] = $v;
		}
		
		foreach ($params as $n=>$v) {
			$this->params[$n] = $v;;
		}
	}
	
	public function set($n,$v) {
		$this->params[$n] = $v;
	}
	
	public function getHeight($txt = false) {
		if ($txt === false) $txt = $this->content;
		
		if (isset($this->params["fixedHeight"])) return $this->params["fixedHeight"];
		
		$height = 0;
		
		if (strpos($txt,"\n") !== false) {
			$txt = explode("\n",$txt);
			foreach ($txt as $t) {
				$height += $this->getHeight($t);
			}
			return $height;
		}
		
		$txt = preg_split('/(\s+)/',$txt, NULL, PREG_SPLIT_DELIM_CAPTURE);

		$pad = isset($this->params["padding"]) ? $this->params["padding"] : 0;
		$rv = array();
		$cur = array();
		
		$height += $this->params["lineHeight"];
		
		for ($i = 0; $i < count($txt); $i++) {
			$cur[] = $txt[$i];
			$l = rtrim(implode("", $cur));
			if ($this->table->x2p->pdf->GetStringWidth($l) >= $this->params["width"]) {
				$cur = array();
				$height += $this->params["lineHeight"];
			}
		}
		return $height + $pad * 2;
	}
	
	public function render() {
		$pad = isset($this->params["padding"]) ? $this->params["padding"] : 0; //TODO: Add border width
		$this->table->x2p->setStyle($this->params["style"]);
		$this->table->x2p->pdf->SetXY($this->params["x"], $this->params["y"]);
		$this->table->x2p->pdf->MultiCell($this->params["width"], $this->params["height"], "", $this->params["border"], "", self::SAT($this->params["style"],"bgcolor"));
		if ($this->params["width"] - $pad * 2 <= 0) return;
		$this->table->x2p->pdf->SetXY($this->params["x"] + $pad, $this->params["y"] + $pad);
		$this->table->x2p->pdf->MultiCell($this->params["width"] - $pad * 2, $this->params["lineHeight"], $this->content, "", "L");
	}	
	
	private static function SAT($o,$n) {
		return isset($o[$n]) && $o[$n] && $o[$n] !== "transparent";
	}
}

	
