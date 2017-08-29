<?php

require_once(SL_INCLUDE_PATH."/class.slGeocoder.php");
require_once(SL_INCLUDE_PATH."/class.slImage.php");

function db_galleryImage_key_update($v,&$items,$tableInfo) {
	if (!$v) $items["key"] = substr(base64_encode(sha1(microtime(true)."-".rand(0,0x7FFFFFFF)."-Gulp",true)),0,16);
}

function db_galleryImage_image_update($v,&$items,$tableInfo) {
	$file = slImage::getSrc($v);
	
	$convert = array("Height"=>"height","Width"=>"width");
	if (function_exists("exif_read_data") && $exif = exif_read_data($file, 0, true)) {
		foreach ($exif as $key => $section) {
			foreach ($section as $name => $val) {
				if (isset($convert[$name])) {
					$items[$convert[$name]] = $val;
				} else {
					$items["exif_".$name] = $val;
				}
			}
		}
		
		if (isset($items["exif_GPSLongitude"])) {
			$items["lat"] = slGeocoder::exifToNum($items["exif_GPSLatitude"], $items['exif_GPSLatitudeRef']);
			$items["lng"] = slGeocoder::exifToNum($items["exif_GPSLongitude"], $items['exif_GPSLongitudeRef']);				
		}
		if (isset($items["exif_DateTime"])) $items["taken"] = strtotime($items["exif_DateTime"]);
	} else {
		if ($im = slImage::getImageFromAny($file)) {
			$items["width"] = imagesx($im);
			$items["height"] = imagesy($im);
		}
	}
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Gallery Images",
	"singleName"=>"en-us|Image",
	"table"=>"db/galleryImages",
	"key"=>"id",
	"displayName"=>array("item.image"),
	"nameField"=>"caption",
	"orderby"=>"sortOrder",
	"useViewMode"=>"icon",
	"fields"=>array(
		"galleryId"=>array(
			"label"=>"en-us|Gallery",
			"type"=>"object",
			"ref"=>"db/gallery",
			"viewable"=>false,
			"useID"=>1
		),
		"image"=>array(
			"label"=>"en-us|Image",
			"updateFunction"=>"db_galleryImage_image_update",
			"type"=>"image",
			"useAsIcon"=>true
		),
		"caption"=>array(
			"label"=>"en-us|Caption",
			"type"=>"textarea"
		),
		"taken"=>array(
			"label"=>"en-us|Taken",
			"type"=>"date"
		),
		"created"=>array(
			"label"=>"en-us|Added",
			"type"=>"date",
			"readOnlyField"=>true
		),
		"tags"=>array(
			"label"=>"en-us|Tags",
			"type"=>"object",
			"multi"=>true,
			"ref"=>"db/galleryTags"
		),
		"protected"=>array(
			"label"=>"en-us|Protected",
			"type"=>"checkbox"
		),
		"key"=>array(
			"label"=>"en-us|Key",
			"updateFunction"=>"db_galleryImage_key_update",
			"viewable"=>false,
			"editable"=>false
		)
	)
);
