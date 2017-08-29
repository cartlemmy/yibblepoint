<?php

function db_groups_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Groups",
	"singleName"=>"en-us|Group",
	"table"=>"db/groups",
	"key"=>"id",
	"unique"=>"nameSafe",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"userField"=>"userId",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim,name",
			"updateFunction"=>"db_groups_update"
		)
	)
);
