<?php

function gdkPixDataToGD($pixData, $returnAsInlineSrc = false) {
	if (substr($pixData,0,6) == "R2RrUA") {
		if (!($pixData = base64_decode($pixData))) return false;
	}
	if (substr($pixData,0,4) != "GdkP") return false;
	
	// Get Header
	$header = array();
	$headerStruct = array(
		"magic"=>array(4,"a*"),
		"length"=>array(4,"N",1),
		"pixdata_type"=>array(4,"N"),
		"rowstride"=>array(4,"N"),
		"width"=>array(4,"N"),
		"height"=>array(4,"N")
	);
	
	$pos = 0;
	foreach ($headerStruct as $n=>$o) {
		$v = unpack($o[1],substr($pixData,$pos,$o[0]));
		$pos += $o[0];
		$header[$n] = isset($o[2]) && $v[1] > 2147483647 ? $v[1] - 4294967296 : $v[1];
	}
	
	// GdkPixdataType
	$GdkPixdataTypeDef = array(
		"GDK_PIXDATA_COLOR_TYPE_RGB"=>0x01,
		"GDK_PIXDATA_COLOR_TYPE_RGBA"=>0x02,
		"GDK_PIXDATA_COLOR_TYPE_MASK"=>0xff,
		/* width, support 8bits only currently */
		"GDK_PIXDATA_SAMPLE_WIDTH_8"=>0x01 << 16,
		"GDK_PIXDATA_SAMPLE_WIDTH_MASK"=>0x0f << 16,
		/* encoding */
		"GDK_PIXDATA_ENCODING_RAW"=>0x01 << 24,
		"GDK_PIXDATA_ENCODING_RLE"=>0x02 << 24,
		"GDK_PIXDATA_ENCODING_MASK"=>0x0f << 24
	);
	
	$GdkPixdataType = array();
	
	foreach ($GdkPixdataTypeDef as $n=>$v) {
		if (substr($n,-5) == "_MASK") {	
			$GdkPixdataType[substr($n,0,-5)] = (int)$header["pixdata_type"] & $v;
			//$GdkPixdataType[substr($n,0,-5)] = array_search((int)$header["pixdata_type"] & $v, $GdkPixdataTypeDef);
		} else {
			if (!defined($n)) define($n,$v);
		}
	}
		
	//Create GD image
	$im = imagecreatetruecolor($header["width"],$header["height"]);
		
	switch ($GdkPixdataType["GDK_PIXDATA_ENCODING"]) {
		case GDK_PIXDATA_ENCODING_RAW:
			for ($y = 0; $y < $header["height"]; $y++) {
				for ($x = 0; $x < $header["width"]; $x++) {
					switch ($GdkPixdataType["GDK_PIXDATA_COLOR_TYPE"]) {
						case GDK_PIXDATA_COLOR_TYPE_RGB:
							imagesetpixel($im, $x, $y, imagecolorallocate($im,ord($pixData{$pos++}),ord($pixData{$pos++}),ord($pixData{$pos++})));
							break;
						
						case GDK_PIXDATA_COLOR_TYPE_RGBA:
							imagesetpixel($im, $x, $y, imagecolorallocatealpha($im,ord($pixData{$pos++}),ord($pixData{$pos++}),ord($pixData{$pos++}),ord($pixData{$pos++})));
							break;
					}
				}
			}
			break;
	}
	
	if ($returnAsInlineSrc) {
		ob_start();
		switch ($returnAsInlineSrc) {
			case "jpeg":
				imagejpeg($im);
				break;
				
			case "png":
				imagepng($im);
				break;
		}
		$data = ob_get_clean();
		return $data ? "data:image/".$returnAsInlineSrc.";base64,".base64_encode($data) : false;
	}
	return $im;
}
