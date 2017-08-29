<?php

require_once(SL_INCLUDE_PATH."/class.slImage.php");

class spriteSheet {
	private $ss = null;
	public $images = array();
	private $xGrid = array();
	private $yGrid = array();
	private $file = false;
	private $checkDir = true;
	public $useImageData = false;
	public $useAlpha = false;
	public $retina = false;
	
	public function __construct() {
	}
	
	public function load($file) {
		$this->useImageData = true;
		$this->useAlpha = true;
		
		if ($this->retina) $file .= "@2x";
		
		$this->file = $file;
		
		if (!is_file($file.".png")) return false;
		
		$this->ss = new slImage();
		$this->ss->fromFile($file.".png");
		
		$h = $this->ss->height();
		for ($y = 0; $y < $h; $y ++) {
			if ($this->ss->toString(0,$y,9) == "!YP-DATA:") {
				if ($info = json_decode($this->ss->toString(9,$y),true)) {
					$this->ss->canvasSize($this->ss->width(),$y);
					$this->images = $info["images"];
					$this->xGrid = $info["xGrid"];
					$this->yGrid = $info["yGrid"];
					$this->checkDir = $info["checkDir"];
				}
				return true;
			}
		}
		return false;
	}
	
	public function remove($i) {
		$image = $this->images[$i];
		imagefilledrectangle($this->ss->im, $image["x1"], $image["y1"], $image["x2"], $image["y2"], imagecolorallocatealpha($this->ss->im, 0, 0, 0, 127));  
		array_splice($this->images,$i,1);
	}
	
	public function add($file,$ratio = 1, $returnPlaceholder = false, $id = false) {
		$isRetina = strpos($file,"@2x") !== false;
		if ($this->retina && !$isRetina) $ratio = 2;
		
		$modified = filemtime($file);
		
		if (!$returnPlaceholder) {
			foreach ($this->images as $i=>$image) {
				if ($image["file"] == $file) {
					if ($modified > $image["mod"]) {
						$this->remove($i);
					} else return $image;
				}
			}
		}
		
		$size = getimagesize($file);
		$destW = round($size[0] * $ratio);
		$destH = round($size[1] * $ratio);
		
		if ($this->retina) {
			$destW = ceil($destW / 2) * 2;
			$destH = ceil($destH / 2) * 2;
		}
		
		$src = new slImage();
		
		if (!$this->useAlpha) $src->fillBGColor(0x808080);
		
		$src->fromFile($file);
				
		if ($destW != $size[0] || $destH != $size[1]) $src->resize($destW, $destH);
		
		$avgColor = $src->averageColor(240);
		$ext = strtolower(array_pop(explode(".",$file)));
		
		if ($returnPlaceholder !== "just-placeholder") {
			$spot = $this->findEmptySpot($destW, $destH);
		} else {
			$spot = array(-1,-1);
		}
		
		$imData = array(
			"srcW"=>$size[0],
			"srcH"=>$size[1],
			"ext"=>$ext,
			"file"=>$file,
			"mod"=>$modified,
			"src"=>webPath($file),
			"c"=>$avgColor,
			"x1"=>$spot[0],
			"x2"=>$spot[0] + $destW,
			"y1"=>$spot[1],
			"y2"=>$spot[1] + $destH
		);
		
		if ($id) $imData["id"] = $id;
		
		if ($returnPlaceholder === "just-placeholder") {
			$imData["justPlaceholder"] = 1;
		} else {
			if (!in_array($imData["x1"], $this->xGrid)) {
				$this->xGrid[] = $imData["x1"];
				sort($this->xGrid);
			}
			
			if (!in_array($imData["y1"], $this->yGrid)) {
				$this->yGrid[] = $imData["y1"];
				sort($this->yGrid);
			}
			
			if (!in_array($imData["x2"], $this->xGrid)) {
				$this->xGrid[] = $imData["x2"] + ($this->retina ? 2 : 0);
				sort($this->xGrid);
			}
			
			if (!in_array($imData["y2"], $this->yGrid)) {
				$this->yGrid[] = $imData["y2"] + ($this->retina ? 2 : 0);
				sort($this->yGrid);
			}
			
			$ssWidth = max($this->ss->width(), $imData["x2"] + ($this->retina ? 2 : 0));
			$ssHeight = max($this->ss->height(), $imData["y2"] + ($this->retina ? 2 : 0));
			
			if ($ssWidth != $this->ss->width() || $ssHeight != $this->ss->height()) {
				$this->ss->canvasSize($ssWidth, $ssHeight);
			}

			$this->ss->copy($src, $imData["x1"], $imData["y1"], 0, 0, $destW, $destH);
		}
	
		if ($returnPlaceholder) {
			$placeholder = imagecreatetruecolor(round($destW * 0.25),round($destH * 0.25));

			$bg = imagecolorallocate($placeholder, 128, 128, 128);
			imagefilledrectangle($placeholder,0,0,imagesx($placeholder),imagesy($placeholder),$bg);
			imagecolortransparent($placeholder, $bg);
			ob_start();
			imagegif($placeholder);
			$imData["placeholder"] = 'data:image/gif;base64,'.base64_encode(ob_get_clean());
			$imData["bgcolor"] = $avgColor;
			imagedestroy($placeholder);
		}
		
		$src->destroy();

		$this->images[] = $imData;
		return $imData;
	}
	
	public function width() {
		return $this->ss ? $this->ss->width() : 0;
	}
	
	public function height() {
		return $this->ss ? $this->ss->height() : 0;
	}
	
	public function I() {
		return count($this->images) - 1;
	}
	
	function save() {
		if ($this->file) {
			return $this->out($this->file);
		}
	}
	
	public function out($file = false, $quality = 70) {
		if (!$this->ss || !count($this->images)) return false;
		$data = array(
			"images"=>$this->images,
			"xGrid"=>$this->xGrid,
			"yGrid"=>$this->yGrid,
			"checkDir"=>$this->checkDir,
			"w"=>$this->width(),
			"h"=>$this->height()
		);
		
		if ($file) {
			if ($this->useImageData) {
				$json = "!YP-DATA:".json_encode($data);

				$dataIm = new slImage($this->ss->width(),ceil(strlen($json) / $this->ss->width()));

				$dataIm->fromString(0,0,$json);
				
				$h = $this->ss->height();
				$this->ss->canvasSize($this->ss->width(), $h + $dataIm->height());
				$this->ss->copy($dataIm, 0, $h, 0, 0, $dataIm->width(), $dataIm->height(),false);
				$data["h"] = $h + $dataIm->height(); // From YP
				$dataIm->destroy();
				
				$this->ss->png($file.".png");
			
			} else {
				$this->ss->jpeg($file.".jpg", $quality);
				file_put_contents($file.".js", 'window._FL_INFO='.json_encode($data));
			}
			return $data; //From YP
		} else {
			
			if ($this->useAlpha) {
				header('Content-Type: image/png');
				$this->ss->png(null);
			} else {
				header('Content-Type: image/jpeg');
				$this->ss->jpeg(null, $quality);
			}
			exit();
		}
	}
	
	private function findEmptySpot($w, $h) {
		if (!$this->ss) {
			$this->ss = new slImage($w, $h);
			if (!$this->useAlpha) $this->ss->fillBGColor(0x808080);
			return array(0,0);
		}
		
		$this->checkDir = !$this->checkDir;
		
		$bestMatch = 0x7FFFFFFF;
		
		if ($this->checkDir) {
			for ($xi = 0; $xi < count($this->xGrid); $xi ++) {
				for ($yi = 0; $yi < count($this->yGrid); $yi ++) {
					if ($this->isEmpty($this->xGrid[$xi],$this->yGrid[$yi], $w, $h)) {
						$match = max($this->xGrid[$xi]+$w,$this->yGrid[$yi]+$h);
						if ($match < $bestMatch) {
							$bestMatch = $match;
							$rv = array($this->xGrid[$xi],$this->yGrid[$yi]);
						}
					}
				}
			}
		} else {
			for ($yi = 0; $yi < count($this->yGrid); $yi ++) {
				for ($xi = 0; $xi < count($this->xGrid); $xi ++) {
					if ($this->isEmpty($this->xGrid[$xi],$this->yGrid[$yi], $w, $h)) {
						$match = max($this->xGrid[$xi]+$w,$this->yGrid[$yi]+$h);
						if ($match < $bestMatch) {
							$bestMatch = $match;
							$rv = array($this->xGrid[$xi],$this->yGrid[$yi]);
						}
					}
				}
			}
		}
		if (!$rv) {
			echo $h." x ".$h; exit();
		}
		return $rv;
	}
	
	private function isEmpty($x1, $y1, $w, $h) {
		$x2 = $x1 + $w;
		$y2 = $y1 + $h;
		foreach ($this->images as $im) {
			if (setAndTrue($im,"justPlaceholder")) continue;
			if (self::collision($x1, $y1, $x2, $y2, $im["x1"], $im["y1"], $im["x2"], $im["y2"])) return false;
		}
		return true;
	}
	
	private static function collision($ax1, $ay1, $ax2, $ay2, $bx1, $by1, $bx2, $by2) {
		return (
			$ax1 < $bx2 &&
			$ax2 > $bx1 &&
			$ay1 < $by2 &&
			$ay2 > $by1
		);
	}
}
