<?php

$ext = strtolower(array_pop(explode(".",$_SERVER["REQUEST_URI"])));

$devMode = substr($_SERVER["REQUEST_URI"],0,5) == "/dev/";

if (in_array($ext,array("jpg","jpeg","gif","png"))) {
	$root = realpath(dirname(__FILE__)."/..");
	
	$file = substr($_SERVER["DOCUMENT_ROOT"].substr($_SERVER["REQUEST_URI"],$devMode?5:1),strlen($root));
	if (substr($file,0,1) != "/") $file = "/".$file;
	$checkFiles = array($file,"/web".($devMode?"/dev":"").$file,"/web".($devMode?"/dev":"")."/templates/".$GLOBALS["slConfig"]["web"]["template"].$file);

	
	foreach ($checkFiles as $file) {
		$scaledFile = explode(".",$root.$file);
			
		$ext = array_pop($scaledFile);
		$twoXFile = implode(".",$scaledFile)."@2x.".$ext;
		$scaledFile = implode(".",$scaledFile).".".$ext;
				
		if (is_file($twoXFile) && !is_file($scaledFile)) {			
			$dir = "data/tmp/2x-images";
			if (!is_dir($dir)) mkdir($dir);
			$cacheFile = $dir."/".md5($scaledFile);
			
			switch ($ext) {
				case "jpg": case "jpeg":
					header('Content-Type: image/jpeg');
					break;
					
				case "png": case "gif":
					header('Content-Type: image/png');
					break;
			}
				
			if (!(is_file($cacheFile) && filemtime($cacheFile) > filemtime($twoXFile))) {
				switch ($ext) {
					case "jpg": case "jpeg":
						$im = imagecreatefromjpeg($twoXFile);
						break;
						
					case "png":
						$im = imagecreatefrompng($twoXFile);
						break;
						
					case "gif":
						$im = imagecreatefromgif($twoXFile);
						break;
				}
				if ($im) {
					$scaledIm = imagecreatetruecolor(round(imagesx($im)/2),round(imagesy($im)/2));
					imagealphablending( $scaledIm, false );
					imagesavealpha( $scaledIm, true );
					imagecopyresampled (
						$scaledIm, $im,
						0, 0, 0, 0,
						imagesx($scaledIm), imagesy($scaledIm), imagesx($im), imagesy($im)
					);
					
					switch ($ext) {
						case "jpg": case "jpeg":
							imagejpeg($scaledIm,$cacheFile);
							break;
							
						case "png": case "gif":
							imagepng($scaledIm,$cacheFile);
							break;
					}
					
				}
			}
			cacheHeaders(864000, $cacheFile);
			readfile($cacheFile);
			exit();
		}
	}			
}

function cacheHeaders($secondsToCache = 3600, $file = false) {
	$ts = gmdate("D, d M Y H:i:s", time() + $secondsToCache) . " GMT";
	header("Expires: $ts");
	header("Pragma: cache");
	header("Cache-Control: max-age=$secondsToCache");
	if ($file && is_file($file)) {
		$etag = md5_file($file);
		$modified = filemtime($file);
		header("Etag: ".$etag); 			
		if ($file) header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modified).' GMT');
		if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $modified) || 
			(isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)) { 
			header("HTTP/1.1 304 Not Modified"); 
			exit; 
		}
	}
}
