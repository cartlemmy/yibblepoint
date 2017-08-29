<?php

function db_manufacturers_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Manufacturers",
	"singleName"=>"en-us|Manufacturer",
	"table"=>"db/manufacturers",
	"key"=>"id",
	"unique"=>"nameSafe",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"required"=>"name",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim",
			"updateFunction"=>"db_manufacturers_update"
		)
	)
);
