<?php

function db_transactionCategories_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Transaction Categories",
	"singleName"=>"en-us|Transaction Category",
	"table"=>"db/transactionCategories",
	"key"=>"id",
	"unique"=>"nameSafe",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"userField"=>"userId",
	"required"=>"name",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim,name",
			"updateFunction"=>"db_transactionCategories_update"
		)
	)
);
