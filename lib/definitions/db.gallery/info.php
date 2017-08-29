<?php

function db_gallery_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Galleries",
	"singleName"=>"en-us|Gallery",
	"table"=>"db/gallery",
	"userField"=>"userId",
	"key"=>"id",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"unique"=>"nameSafe",
	"orderby"=>"sortOrder",
	"parentField"=>"parentGallery",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"type"=>"text",
			"updateFunction"=>"db_gallery_update"
		),
		"parentGallery"=>array(
			"label"=>"en-us|Parent Gallery",
			"type"=>"object",
			"ref"=>"db/gallery",
			"useID"=>1
		),
		"key"=>array(
			"label"=>"en-us|Key",
			"updateFunction"=>"db_galleryImage_key_update",
			"viewable"=>false,
			"editable"=>false
		),
		"previewImage"=>array(
			"label"=>"en-us|Preview Image",
			"type"=>"image",
			"useAsIcon"=>true
		)
	)
);
