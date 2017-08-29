<?php

class barcode {
	private $dpi = 600;
	private $height = 1.4;
	public $pad = 0.2;
	private $xDimPad = 14;
	private $fontDir;
	public $noText = false;

	function __construct() {
		$this->fontDir = SL_INCLUDE_PATH."/font";
	}
	
	function setDPI($dpi) {
		$this->dpi = $dpi;
	}
	
	function setPixelWidth($w) {
		$xCols = 95 + ($this->xDimPad * 2);

		$this->dpi = round(($w / $xCols) / 0.013);
	}
	
	function setPixelHeight($h) {
		$this->height = $h / $this->dpi;
	}
	
	function generateUPCA($num) {
		$printHeight = $this->height - ($this->pad * 2);

		if (strlen($num) > 11) return false; //code cannot be longer than 11 digits
		$num = str_repeat("0", 11 - strlen($num)).$num; //zero pad
		$codeL = substr($num, 0, 6);
		$codeR = substr($num, 6, 5);
		
		$checkDigit = (int)$num{0} + (int)$num{2} + (int)$num{4} + (int)$num{6} + (int)$num{8} + (int)$num{10};
		$checkDigit = $checkDigit + ((int)$num{1} + (int)$num{3} + (int)$num{5} + (int)$num{7} + (int)$num{9}) * 3;
		$checkDigit = $checkDigit % 10;
		if ($checkDigit != 0) $checkDigit = 10 - $checkDigit;

		$barcode = "101".$this->converUPCADigits($codeL, 6, 0)."01010".$this->converUPCADigits($codeR, 5, 1).$this->converUPCADigits((string)$checkDigit, 1, 1)."101";

		$xDimension = round(0.013 * $this->dpi);

		$imW = $xDimension * (95 + ($this->xDimPad * 2));
		$imH = round($this->dpi * ($printHeight + ($this->pad * 2)));

		$barcodeW = $xDimension * 95;
		$barcodeH = round($this->dpi * $printHeight);
		$barcodeX = $xDimension * $this->xDimPad;
		$barcodeY = round($this->dpi * $this->pad);

		$outerTextOff = round($barcodeX / 2);

		$im = imagecreate($imW, $imH);
		$white = imagecolorallocate($im, 255, 255, 255);
		$black = imagecolorallocate($im, 0, 0, 0);

		for ($i = 0; $i < 95; $i++) {
			if ($barcode{$i} == 1) {
				if ($this->noText || ($i < 10 || $i >= 85 || ($i >= 45 && $i <= 49))) {
					imagefilledrectangle ($im, $barcodeX + $xDimension * $i, $barcodeY, $barcodeX + $xDimension * $i + ($xDimension - 1), $barcodeY + $barcodeH, $black);
				} else {
					imagefilledrectangle ($im, $barcodeX + $xDimension * $i, $barcodeY, $barcodeX + $xDimension * $i + ($xDimension - 1), $barcodeY + $barcodeH - $xDimension * 6, $black);
				}
			}
		}

		$pt = (1 / 72) * $this->dpi;
		
		if (!$this->noText) {
			//First Digit:
			$this->textCenterBottom ( $im, floor($pt * 8), 0, $outerTextOff, $imH - $outerTextOff, $black, $this->fontDir."/cour.ttf", $num{0});

			//Before Middle
			$x = $barcodeX + ($xDimension * 10);
			$w = $xDimension * 35;
			for ($i = 0; $i < 5; $i++) {
				$this->textCenterTop ( $im, floor($pt * 10), 0, $x + round($w * ($i / 5)) + round($w / 10), ($barcodeY + $barcodeH) - $xDimension * 5, $black, $this->fontDir."/cour.ttf", $codeL{$i + 1});
			}

			//After Middle
			$x = $barcodeX + ($xDimension * 49);
			for ($i = 0; $i < 5; $i++) {
				$this->textCenterTop ( $im, floor($pt * 10), 0, $x + round($w * ($i / 5)) + round($w / 10), ($barcodeY + $barcodeH) - $xDimension * 5, $black, $this->fontDir."/cour.ttf", $codeR{$i});
			}

			//Last Digit:
			$this->textCenterBottom ( $im, floor($pt * 8), 0, $imW - $outerTextOff, $imH - $outerTextOff, $black, $this->fontDir."/cour.ttf", $checkDigit);
		}
		return $im;
	}

	function generate($num, $file = false, $type = "UPC-A") {
		switch (strtoupper($type)) {
			case "UPC-A":
				$im = $this->generateUPCA($num);
				break;
		}
		if ($file == "GD") return $im;
		imagepng($im, $file);
		imagedestroy($im);
	}	

	function textCenterBottom ( $im, $size, $angle, $x, $y, $color, $fontfile, $text) {
		$b = imageftbbox ($size, $angle, $fontfile, $text);
		$w = $b[4] - $b[6];	
		imagefttext ($im, $size, $angle, $x - round($w / 2), $y, $color, $fontfile, $text);
	}

	function textCenterTop ( $im, $size, $angle, $x, $y, $color, $fontfile, $text) {
		$b = imageftbbox ($size, $angle, $fontfile, $text);
		$w = $b[4] - $b[6];	
		$h = $b[1] - $b[7];
		imagefttext ($im, $size, $angle, $x - round($w / 2), $y + $h, $color, $fontfile, $text);
	}

	function converUPCADigits($code, $numDigits, $invert = 0) {
		$digitPattern = array(
			0=>array("0001101", "0011001", "0010011", "0111101", "0100011", "0110001", "0101111", "0111011", "0110111", "0001011"),
			1=>array("1110010", "1100110", "1101100", "1000010", "1011100", "1001110", "1010000", "1000100", "1001000", "1110100")
		);

		$rv = "";
		for ($i = 0; $i < $numDigits; $i ++) {
			$rv .= $digitPattern[$invert][(int)$code{$i}];
		}
		return $rv;
	}
}

?>
