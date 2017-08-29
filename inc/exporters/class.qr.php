<?php

class exporterQr {
	private $ref;
	private $uid;
	private $file;
	private $pdf;
	private $outputted = false;
	private $x = 0;
	private $y = 0;
	private $imNum = 0;
	private $fp = false;
	private $labelFile;
	private $labelConfig;
	private $blockStack;
	private $blocks;
	private $curPage = 0;
	private $curLabel = 0;
	
	function __construct($uid = false, $label = '5159') {
		$ts = microtime(true);
		if ($uid === false) $uid = "qr-export-".date('YmdHi',(int)$ts).str_pad("".(($ts * 100) % 6000), 4, '0', STR_PAD_LEFT);
		$this->uid = safeFile($uid);
		$this->file = $GLOBALS["slSession"]->userFilePath($this->uid,"application/pdf");
		if (!($this->labelFile = $this->getLabelFile($label))) $this->labelFile = $this->getLabelFile('5159');
	}
	
	function __destruct() {
		$this->close();
	}
	
	private function getLabelFile($label) {
		$file = SL_INCLUDE_PATH.'/exporters/labels/qr-'.$label.'.xml.php';
		return is_file($file) ? $file : false;		
	}
	
	function init($ref) {
		file_put_contents(SL_DATA_PATH.'/debug.txt', '');
		$this->ref = $ref;
		$this->imNum = 0;
	
		require_once(SL_INCLUDE_PATH."/class.slURLShortener.php");
			
		requireThirdParty("phpqrcode");
		
		require_once(SL_INCLUDE_PATH.'/class.xmlToPDF.php');
		
		if ($labelXml = simplexml_load_file($this->labelFile)) {
			if ($configEl = $labelXml->xpath('//config')) {
				if ($this->labelConfig = json_decode((string)$configEl[0], true)) {
					$this->curPage = $this->curLabel = 0;
					$this->blocks = array();
					$this->blockStack = array();
			
					$this->block('document');
					require(xmlToPDF::createValidPHPFromXML($this->labelFile));
					$this->blockEnd();
					
					foreach ($this->blocks as $n=>$block) {
						foreach ($block["children"] as $child) {
							if (($end = strpos($block["content"], "[block:".$child."]")) !== false) {
								$check = strrev(substr($block["content"], 0, $end));
								if (preg_match('/[ \t]+/', $check, $matches)) {
									$this->blocks[$child]["indentation"] = $matches[0];
								}
							}
						}
					}
					
						
					// TODO: check that all required blocks are present
					
					$this->blockOut('document');
					
				} else exit();
			} else exit();
		} else exit();
		
		//$this->fp = fopen($this->file.".tmpfiles","a");
		
			
		/*$this->pdf = new FPDF("P", "in", "Letter");
		$this->pdf->AddPage();
		$this->pdf->SetAutoPageBreak(false);*/
		
		//$this->x = $this->y = 0;
	}
	
	private function block($name, $callback = null) {
		ob_start();
		$this->blockStack[] = array("name"=>$name,"level"=>count($this->blockStack),"content"=>false,"callback"=>null,"out"=>array(),"children"=>array(),"indentation"=>"");
		if ($callback) $this->blockEnd($callback);
	}
	
	private function blockEnd($callback = null) {
		$this->setBlock('callback', $callback);	
		if (trim($c = ob_get_clean()) !== '') {
			$this->setBlock('content', $c);
		}
		$block = array_pop($this->blockStack);
		if (count($this->blockStack)) {
			$this->setBlock('children', $block["name"]);
			echo '[block:'.$block["name"].']';
		}
		$this->blocks[$block["name"]] = $block;
	}
		
	private function setBlock($n, $v, $stackPos = false) {
		if ($stackPos === false) $stackPos = count($this->blockStack) - 1;
		if (is_string($stackPos)) {
			foreach ($this->blocks as $bn=>&$bv) {
				if ($bn == $stackPos) $bv[$n] = $v;
			}
			return;
		}
		if (is_array($this->blockStack[$stackPos][$n])) {
			$this->blockStack[$stackPos][$n][] = $v;
		} else {
			$this->blockStack[$stackPos][$n] = $v;
		}
	}
	
	private function blockOut($name) {
		$this->dbg('+'.$name);
		ob_start();
		$this->blockStack[] = $name;
	}
	
	private function blockOutEnd($params = false) {
		$name = array_pop($this->blockStack);
		
		$block = &$this->blocks[$name];
		
		$parentBlock = null;
		if (count($this->blockStack)) $parentBlock = &$this->blocks[$this->blockStack[count($this->blockStack) - 1]];
			
		if ($params && $block["callback"]) {
			call_user_func_array($block["callback"], $params);
		}
		
		$c = ob_get_clean();
		
		$this->dbg(json_encode($block["out"]));
		
		if ($block["content"] !== false) {
			$c = $block["content"];
			
			foreach ($block["out"] as $n=>$content) {
				$c = str_replace('[block:'.$n.']', $content, $c);
			}
		}		
		
		if ($parentBlock) {
			if (!isset($parentBlock["out"][$name])) {
				$parentBlock["out"][$name] = "";
			}
			
			$parentBlock["out"][$name] .= $block["indentation"].ltrim($c)."\n";
			
			$block["out"] = array();
		} else {
			file_put_contents(SL_DATA_PATH.'/out.xml', $c);
		}
		$this->dbg('-'.$name);
			
	}
		
	function getInfo() {
		return array(
			"name"=>"QR Codes"
		);
	}
	
	function addText($text,$size = 10) {
		$this->pdf->SetFont('Helvetica', '', $size);
		$this->pdf->Cell(2.5, 0.2, utf8_decode($text), 0, 2);
	}
	
	function add($row) {
		//$x = 0.2 + $this->x * 4.13;
		//$y = 0.25 + $this->y * 1.5;
		if ($this->curLabel == 0) $this->blockOut('page');
		
		$this->blockOut('label');
			
		$short = new slURLShortener();
		
		$short->create(WWW_ROOT."/item/".$this->ref."/".$row["_KEY"]);
		
		$url = $short->getShortenedURL();

		$qrFile = $this->file."-".($this->imNum++).".png";
		
		$this->blockOutEnd(array($page, $label, $row));
		
		$this->curLabel ++;
		if ($this->curLabel > $this->labelConfig["labels-per-page"]) {
			$this->blockOutEnd();
			$this->curLabel = 0;
			$this->curPage ++;
		}
		
		//QRcode::png($short->getTinyID(), $qrFile, 'L', 6, 2);
		
		//fputs($this->fp,$qrFile."\n");
		
		//$this->pdf->Image($qrFile, $x, $y, 1.5, 1.5, "PNG", $url);
				
		//$this->pdf->SetXY(1.5 + $x, $y + 0.1);
		
		/*$this->addText($row["_NAME"]);
		if (isset($row["_UNIQUE"]) && $row["_UNIQUE"] && safeName($row["_UNIQUE"]) != safeName($row["_NAME"])) $this->addText($row["_UNIQUE"]);
		$this->addText("REF: ".$GLOBALS["slCore"]->db->refToNumber(str_replace("/",".",$this->ref)).".".$row["_KEY"]);
		$this->addText(WWW_ROOT."/sl/tiny?".$short->id,8);
		
		$this->x ++;
		if ($this->x == 2) {
			$this->x = 0;
			$this->y ++;
			if ($this->y == 7) {
				$this->y = 0;
				$this->pdf->AddPage();
			}
		}*/
	}
		
	function reset() {
		$this->close();
		//$this->fp = fopen($this->file.".tmpfiles","w");
	}
	
	function close() {
		//if ($this->fp) fclose($this->fp);
		//$this->fp = false;
	}
	

	public function out() {
		while (count($this->blockStack)) {
			$this->blockOutEnd();
		}
		
		readfile(SL_DATA_PATH.'/debug.txt');
		exit();
		$this->outputted = true;
	}
	
	function getFile() {
		if (!$this->outputted) $this->out();
		return $this->file;
	}
	
	function getFileUrl() {
		$this->getFile();
		return $GLOBALS["slSession"]->userFileURL($this->uid,"application/pdf");
	}
	
	private function dbg($txt) {
		file_put_contents(SL_DATA_PATH.'/debug.txt', str_repeat('    ', count($this->blockStack)).$txt."\n", FILE_APPEND);
	}
}
