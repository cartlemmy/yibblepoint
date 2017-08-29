<?php

class slImage {
	public $im = false;
	public $vid = false;
	private $file = false;
	private $bgColor = false;
	private $imSourceType = false;
	private $imSourceFile = false;
	private $imModified = false;
	
	function __construct($w = false, $h = false, $bgColor = false) {
		if ($bgColor) $this->bgColor = $bgColor;
		if ($w && $h) {
			$this->im = imagecreatetruecolor($w, $h);
			$t = imagecolorallocatealpha($this->im, 0, 0, 0, 127);
			imagecolortransparent($this->im, $t);
			imagealphablending($this->im, false);
			imagefilledrectangle($this->im, 0, 0, imagesx($this->im), imagesy($this->im), $t);  
		}
	}
	
	function __destruct() {
		$this->destroy();
	}
	
	public function fillBGColor($color) {
		$this->bgColor = $color;
		if ($this->im) imagefilledrectangle($this->im, 0, 0, imagesx($this->im), imagesy($this->im), imagecolorallocate($this->im, ($color >> 16) & 255, ($color >> 8) & 255, $color & 255));  
	}
	
	public function destroy() {
		if ($this->im) {
			@imagedestroy($this->im);
			$this->im = false;
		}		
	}
	
	public function fromFile($file, $checkeredBG = false) {
		$this->imModified = false;
		$this->imSourceFile = $this->file = $file;
		$this->imSourceType = isset($GLOBALS["_SL_IMAGE_SOURCE_TYPE"]) ? $GLOBALS["_SL_IMAGE_SOURCE_TYPE"] : false;
		$im = self::getImageFromAny($file, $checkeredBG);
		if (is_a($im,'slVideo')) {
			$this->file = $im->previewFile;
			$this->vid = $im;
			$this->im = $im->prevIm->im;
		} else {
			$this->file = $file;
			$this->im = $im;
		}
		return $this->im;
	}
	
	public function toString($x,$y,$limit = false) { // Converts encoded data at the end of the image into a string
		$rv = "";
		$i = 0;
		
		while ($y < imagesy($this->im)) {
			while ($x < imagesx($this->im)) {
				$c = imagecolorat($this->im, $x, $y);
				$ord = 
					(round((($c >> 16) & 255) / 1.328125)) |
					(round((($c >> 8) & 255) / 4.55357)) |
					(round(($c & 255) / 36.42857));
				$i ++;
				$x ++;
				if (($limit && $i > $limit) || $ord == 0) return $rv;
				$rv .= chr($ord);
			}
			$x = 0;
			$y ++;
		}
		return false;
	}
	
	public function fromString($x,$y,$str) { // Encodes a string appends it to the image, the resulting image should be saved as a PNG if the data is to be recovered later
		if (!$this->im) return false;
		
		$this->imModified = false;
		
		$colors = array();
		for ($i = 0; $i < 256; $i++) {
			$colors[$i] = imagecolorallocate($this->im, floor(($i & 192) * 1.328125), floor(($i & 56) * 4.55357), floor(($i & 7) * 36.42857));
		}
		
		$i = 0;
		$len = strlen($str);
		
		while ($y < imagesy($this->im)) {
			while ($x < imagesx($this->im)) {
				imagesetpixel($this->im, $x, $y, $colors[ord(substr($str,$i,1)) & 255]);
				$i ++;
				$x ++;
				if ($i >= $len) {
					imagesetpixel($this->im, $x, $y, $colors[0]);
					return true;
				}				
			}
			$x = 0;
			$y ++;
		}
		return false;
	}
	
	public static function getMime($file) {
		$header = bin2hex(file_get_contents($file, false, null, 0, 48));
		$types = array(
			"jpeg"=>array(0,'ffd8'),
			"png"=>array(0,'89504e470d0a1a0a'),
			"gif"=>array(0,'474946383961')
		);
		
		foreach ($types as $mime=>$o) {
			if (substr($header,$o[0] * 2,strlen($o[1])) == $o[1]) return $mime;
		}
		
		return strtolower(array_pop(explode(".",$file)));
	}
	
	public static function getImageFromAny($file, $checkeredBG = false) {
		$GLOBALS["_SL_IMAGE_SOURCE_TYPE"] = false;
		if (strpos($file,"//") === false && !@is_readable($file) || !@filesize($file)) return false;		
				
		$convTo = "jpg";
		
		$ext = self::getMime($file);

		switch ($ext) {
			case "jpg": case "jpeg":
				$GLOBALS["_SL_IMAGE_SOURCE_TYPE"] = "jpeg";
				return @imagecreatefromjpeg($file);
			
			case "png":
				$GLOBALS["_SL_IMAGE_SOURCE_TYPE"] = "png";
				$size = getimagesize($file);
				$im = @imagecreatefrompng($file);
				if (!$im) return false;
				
				if ($checkeredBG) {
					//TODO Add the checkered BG
				}
				
				if (!isset($size["channels"])) {
					$newIm = imagecreatetruecolor($size[0],$size[1]);
					imagealphablending($newIm, false);
					imagecopyresampled($newIm, $im, 0, 0, 0, 0, $size[0], $size[1], $size[0], $size[1]);
					imagedestroy($im);
					return $newIm;
				}
				return $im;
			
			case "gif":
				$GLOBALS["_SL_IMAGE_SOURCE_TYPE"] = "gif";
				return @imagecreatefromgif($file);
			
			case "mp3": case "ogg": case "wav": case "flac":
				$tmpFile = "/tmp/".md5($file);
				$command = 'sox '.escapeshellarg($file).' '.escapeshellarg($tmpFile.'.wav').' channels 1';
				system($command);
				
				$command = 'sox '.escapeshellarg($tmpFile.'.wav').' -n spectrogram -o '.escapeshellarg($tmpFile.'.png');
				system($command);
				
				$command = 'convert '.escapeshellarg($tmpFile.'.png').' -crop 800x372+58+171 '.escapeshellarg($tmpFile.'.png');
				system($command);
				
				unlink($tmpFile.'.wav');
				return self::getImageFromAny($tmpFile.'.png', $checkeredBG);
				
			case "mpg": case "mpeg": case "mp4": case "mov":
			case "qt":	case "ogv": case "webm": case "wmv":
			case "avi":	case "mts": case "avchd": case "asf":
			case "flv":	case "divx": case "m4a":
				require_once(SL_INCLUDE_PATH."/class.slVideo.php");
				return new slVideo($file);
			
			case "ai": case "svg": case "eps":
				$convTo = "png";
			case "cr2": case "pdf": case "psd": case "tif": case "tiff":
				$tmpFile = "/tmp/".md5($file);
				if (!is_file($tmpFile."-0.".$convTo)) {
					$command = 'convert -limit memory 128MB -limit map 128MB -resize 3300x3300\> '.escapeshellarg(':'.$file).'[0] '.escapeshellarg($tmpFile.".".$convTo);
					//$command = 'convert '.escapeshellarg(':'.$file).'[0] -scale 600 '.escapeshellarg($tmpFile.".".$convTo);
					echo $command."\n"; 
					system($command);				
				}
				return self::getImageFromAny(self::findConverted($tmpFile,$convTo), $checkeredBG);
		}
		return false;
	}
	
	public static function findConverted($file, $ext) {
		if (is_file($file.".".$ext)) return $file.".".$ext;
		
		$i = 1;
		while (is_file($file."-".$i.".".$ext)) {
			unlink($file."-".$i.".".$ext);
			$i++;
		}
		
		return $file."-0.".$ext;
	}
	
	public function averageColor($alphaThreshold = 0) {
		if (!$this->im) return false;

		$newIm = imagecreatetruecolor(1,1);
		imagecolortransparent($newIm, imagecolorallocatealpha($newIm, 0, 0, 0, 127));
		imagealphablending($newIm, false);

		imagecopyresampled($newIm,$this->im,0,0,0,0,1,1,imagesx($this->im),imagesy($this->im));
		$ci = imagecolorat($newIm, 0, 0);
		$c = sprintf('%08X',$ci);		
		imagedestroy($newIm);
		
		if (substr($c,0,2) != "00") {
			$a = round((127 - (($ci & 0x7F000000) >> 24)) * 2.0078740157);
			if ($a < $alphaThreshold) return "transparent";
			return 'rgba('.(($ci & 0xFF0000) >> 16).', '.(($ci & 0xFF00) >> 8).', '.($ci & 0xFF).', '.$a.')';
		}
		return "#".substr($c,2);
	}
	
	public function getFingerprint($size = 8) {
		if (!$this->im) return false;
		$im = $this->im;
		
		$newIm = imagecreatetruecolor($size,$size);
		imagecopyresampled($newIm,$im,0,0,0,0,$size,$size,imagesx($im),imagesy($im));
		
		//Normalise
		$norm = array(array(255,255,255),array(0,0,0));
		for ($y = 0; $y < $size; $y++) {
			for ($x = 0; $x < $size; $x++) {
				$rgb = imagecolorat($newIm, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				
				$norm[0][0] = min($norm[0][0],$r);
				$norm[0][1] = min($norm[0][1],$g);
				$norm[0][2] = min($norm[0][2],$b);
				
				$norm[1][0] = max($norm[1][0],$r);
				$norm[1][1] = max($norm[1][1],$g);
				$norm[1][2] = max($norm[1][2],$b);
			}
		}
		
		$norm[1][0] = 255 / ($norm[1][0] - $norm[0][0]);
		$norm[1][1] = 255 / ($norm[1][1] - $norm[0][1]);
		$norm[1][2] = 255 / ($norm[1][2] - $norm[0][2]);
						
		for ($y = 0; $y < $size; $y++) {
			for ($x = 0; $x < $size; $x++) {
				$rgb = imagecolorat($newIm, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				
				imagesetpixel($newIm, $x, $y, imagecolorallocate(
					$newIm,
					min(255,round(($r - $norm[0][0]) * $norm[1][0])),
					min(255,round(($g - $norm[0][1]) * $norm[1][1])),
					min(255,round(($b - $norm[0][2]) * $norm[1][2]))
				));
			}
		}
		
		//Should we rotate?
		$av = array(0,0);
		$center = $size / 2;
		for ($y = 0; $y < $size; $y++) {
			for ($x = 0; $x < $size; $x++) {
				$rgb = imagecolorat($newIm, $x, $y);
				$v = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 765;
				$av[0] += $v * ($x < $center ? -1 : 1);
				$av[1] += $v * ($y < $center ? -1 : 1);
			}
		}
		
		if (abs($av[0]) > abs($av[1])) {
			$angle = $av[0] > 0 ? 90 : 270;
		} else {
			$angle = $av[1] > 0 ? 180 : 0;
		}
		
		if ($angle) $newIm = imagerotate ( $newIm , $angle, 0);
		
		$quad = array(0,0,0,0);
		$rv = "";

		for ($y = 0; $y < $size; $y++) {
			$yOff = floor(($y / $size) * 2) * 2;
			for ($x = 0; $x < $size; $x++) {
				$v = $this->getValueAt($x, $y, $newIm);

				$quad[$yOff+floor(($x / $size) * 2)] += $v;
				$v = dechex(min(15,floor($v / 48)));
				$rv .= $v;
			}
		}
		
		for ($i = 0; $i < 4; $i++) {
			$quad[$i] = round($quad[$i] / (($x * $y) / (4 / 3)));
		}
		imagedestroy($newIm);
		return $rv.".".implode(".",$quad);
	}
	
	function out($file) {
		if (!$this->im) return;
		imagejpeg($this->im,$file);
	}
	
	public function getValueAt($x, $y, $im = false) {
		if (!$im) $im = $this->im;
		$rgb = imagecolorat($im, $x, $y);
		return ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF));
	}
	
	public function width() {
		return $this->im ? imagesx($this->im) : -1;
	}
	
	public function height() {
		return $this->im ? imagesy($this->im) : -1;
	}
	
	public function canvasSize($width, $height) {
		$this->imModified = true;
		$src = $this->im;
		
		$this->im = imagecreatetruecolor($width, $height);
		$t = imagecolorallocatealpha($this->im, 0, 0, 0, 127);
		imagecolortransparent($this->im, $t);
		imagealphablending($this->im, false);
		imagefilledrectangle($this->im, 0, 0, imagesx($this->im), imagesy($this->im), $this->bgColor ? imagecolorallocate($this->im, ($this->bgColor >> 16) & 255, ($this->bgColor >> 8) & 255, $this->bgColor & 255) : $t);  
		
		imagecopyresampled($this->im, $src, 0, 0, 0, 0, imagesx($src), imagesy($src), imagesx($src), imagesy($src));
	}
	
	public function copy($src, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $ignoreAlpha = false) {
		if ($ignoreAlpha) {
			imagealphablending(self::toGD($src), false);
			imagealphablending($this->im, false);
			imagecopy($this->im, self::toGD($src), $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
		} else {
			imagecopyresampled($this->im, self::toGD($src), $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $src_w, $src_h);
		}
	}
	
	public function validImage() {
		return is_resource($this->im);
	}
	
	public function resize($width, $height = false) {
		if (!$this->validImage()) return false;
		
		$this->imModified = true;
		if (is_array($width)) {
			$height = isset($width["height"]) ? $width["height"] : false;
			$width = isset($width["width"]) ? $width["width"] : false;			
		}
		
		$src = $this->im;
		
		if (!$width) $width = round(imagesx($src) * ($height / imagesy($src)));
		if (!$height) $height = round(imagesy($src) * ($width / imagesx($src)));

		$this->im = imagecreatetruecolor($width,$height);
		
		imagealphablending($this->im, false);
		imagealphablending($src, true);
		
		imagecopyresampled(
			$this->im, $src,
			0, 0, 0, 0,
			$width, $height, imagesx($src), imagesy($src)
		);
		imagedestroy($src);
	}
	
	public function resizeAndCrop($width, $height) {
		if (!$this->validImage()) return false;
		
		$this->imModified = true;
		$srcR = $this->width() / $this->height();
		$destR = $width / $height;
		
		if (!$src = $this->im) return false;		
		
		$this->im = imagecreatetruecolor($width,$height);
		
		imagealphablending($this->im, false);
		imagealphablending($src, true);
		
		if ($srcR > $destR) {
			$srcW = round(imagesy($src) * $destR);			
			imagecopyresampled(
				$this->im, $src,
				0, 0, round((imagesx($src) - $srcW) / 2), 0,
				$width, $height, $srcW, imagesy($src)
			);
		} else {
			$srcH = round(imagesy($src) / $destR);
			imagecopyresampled(
				$this->im, $src,
				0, 0, 0, round((imagesy($src) - $srcH) / 2),
				$width, $height, imagesx($src), $srcH
			);
		}
		imagedestroy($src);
		return true;
	}

	public function saveAsSourceType($filename = NULL, $quality = 75, $filters = PNG_NO_FILTER) {
		if (!$this->validImage()) return false;
		switch ($this->imSourceType) {
				case "png":
					if ($this->imModified) {
						$rv = $this->png($filename, $quality === true ? 75 : $quality, $filters);
					} else {
						copy($this->file, $filename);
					}
					if ($quality === true) exec('optipng '.escapeshellarg($filename));
					return $rv;
				
				case "gif":
					return $this->gif($filename);
				
				case "jpeg": default:
					if ($this->imModified) {
						$rv = $this->jpeg($filename, $quality === true ? 85 : $quality);
					} else {
						copy($this->file, $filename);
					}
					if ($quality === true) exec('jpegoptim --size=40% '.escapeshellarg($filename));
					return $rv;	
		}
	}

	public function jpeg($filename = NULL, $quality = 75) {
		if (!$this->validImage()) return false;
		return imagejpeg($this->im, $filename, $quality);
	}
	
	public function png($filename = NULL, $quality = 9, $filters = PNG_NO_FILTER) {
		if (!$this->validImage()) return false;
		imagesavealpha($this->im, true);
		return imagepng($this->im, $filename, $quality, $filters);
	}
	
	public function gif($filename = NULL) {
		if (!$this->validImage()) return false;
		return imagegif($this->im, $filename);
	}
	
	public function rotateFromExif() {
		$exif = @exif_read_data($this->file);
		if (!empty($exif['Orientation'])) {
			switch($exif['Orientation']) {
				case 8:
					$this->im = imagerotate($this->im,90,0);
					break;
					
				case 3:
					$this->im = imagerotate($this->im,180,0);
					break;
					
				case 6:
					$this->im = imagerotate($this->im,-90,0);
					break;
			}
		}
	}
	
	public static function getSrc($image) {
		if (!is_array($image)) $image = delimToObject($image,array("name","type","size","md5","dimensions","thumbHead","thumb","user"));
		if (!isset($image["md5"])) return str_replace('[SL_WEB_PATH]',SL_WEB_PATH,$image["name"]);

		$p = "/file/image/".$image["md5"].".".array_pop(explode("/",$image["type"]));
		if (setAndTrue($image,"user")) return SL_DATA_PATH."/users/".$image["user"].$p;
		
		if ($GLOBALS["slSession"]->isLoggedIn()) return $GLOBALS["slSession"]->getUserParentDir().$p;
		return SL_DATA_PATH."/users/".$GLOBALS["slConfig"]["package"]["primaryUser"].$p;
	}
	
	public static function toGD($im) {
		if (is_a($im, 'slImage')) return $im->im;
		return $im;
	}
}
