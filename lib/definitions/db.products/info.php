<?php

function db_products_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Prodcuts",
	"singleName"=>"en-us|Product",
	"table"=>"db/products",
	"key"=>"id",
	"unique"=>"nameSafe",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"required"=>"name",
	"fields"=>array(
		"manufacturer"=>array(
			"label"=>"en-us|Manufacturer",
			"type"=>"object",
			"ref"=>"db/manufacturers",
			"searchable"=>true,
		),
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim",
			"updateFunction"=>"db_products_update"
		)
	)
);
