<?php

function db_projects_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Projects",
	"singleName"=>"en-us|Project",
	"table"=>"db/projects",
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
			"updateFunction"=>"db_projects_update"
		),
		"type"=>array(
			"label"=>"en-us|Type",
			"type"=>"select",
			"default"=>"standard",
			"options"=>array(
				""=>"en-us|N/A",
				"standard"=>"en-us|Standard",
				"ongoing"=>"en-us|Ongoing"
			)
		)
	)
);
