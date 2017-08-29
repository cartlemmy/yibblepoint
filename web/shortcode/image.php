<?php

$c = explode(";",$params[0],6);

$ext = strtolower(array_pop(explode("/",$c[1])));

$file = SL_DATA_PATH."/users/".(isset($params[5])?$params[5]:$GLOBALS["slConfig"]["package"]["primaryUser"])."/file/image/".$c[3].".".array_pop(explode("/",$c[1]));

$destFile = SL_WEB_PATH."/images/".$params[1];

$width = $params[2];
$height = $params[3];

makePath($destFile);
$c[0] = explode(".",$c[0]);
$cExt = array_pop($c[0]);
$destFile .= "/".implode(".",$c[0])."-".($width && $height ? $width."x".$height : ($width ? $width."w" : $height."h")).".".$cExt;

//TODO: Make sure file isn't duplicated
if (!(is_file($destFile) && filemtime($destFile) > filemtime($file))) {		
	switch ($ext) {
		case "gif":
			$im = imagecreatefromgif($file);
			break;
			 
		case "png":
			$im = imagecreatefrompng($file);
			break;
			
		case "jpeg": case "jpg":
			$im = imagecreatefromjpeg($file);
			break;
			
		default:
			show404();
	}
	
	if ($width && $height) {
		if (imagesx($im) / imagesy($im) > $width / $height) {
			$oldW = round(imagesy($im) * ($width / $height));
			$oldH = imagesy($im);
		} else {
			$oldH = round(imagesx($im) * ($height / $width));
			$oldW = imagesx($im);
		}
	} elseif ($width) {
		$height = round($width * (imagesy($im) / imagesx($im)));
	} else {
		$width = round($height * (imagesx($im) / imagesy($im)));
	}
	$newIm = imagecreatetruecolor($width,$height);
	
	if ($width && $height) {
		imagecopyresampled(
			$newIm, $im, 
			0, 0, 0, 0,
			$width, $height, imagesx($im), imagesy($im)
		);
	} else {
		imagecopyresampled( 
			$newIm, $im, 
			0, 0, round((imagesx($im) - $oldW) / 2), round((imagesy($im) - $oldH) / 2),
			$width, $height, $oldW, $oldH
		);
	}

	switch ($ext) {
		case "gif":
			imagegif($newIm,$destFile);
			break;
			 
		case "png":
			imagepng($newIm,$destFile);
			break;
			
		case "jpeg": case "jpg":
			imagejpeg($newIm,$destFile);
			break;
			
		default:
			return;
	}
	imagedestroy($im);
	imagedestroy($newIm);
}

echo str_replace("web/","",webPath($destFile,true));
