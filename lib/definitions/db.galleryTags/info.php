<?php

function db_galleryTag_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Gallery Tags",
	"singleName"=>"en-us|Tag",
	"table"=>"db/galleryTags",
	"key"=>"id",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"unique"=>"nameSafe",
	"orderby"=>"name",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"type"=>"text",
			"updateFunction"=>"db_gallery_update"
		)
	)
);
