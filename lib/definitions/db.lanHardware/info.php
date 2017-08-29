<?php

function db_lanHardware_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"name"=>"en-us|Lan Hardware",
	"singleName"=>"en-us|Lan Hardware",
	"table"=>"db/lanHardware",
	"key"=>"id",
	"displayName"=>array("item.name+' ('+item.ip+')'"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"lastSeen",
	"orderdir"=>"desc",
	"fields"=>array(
		"ip"=>array(
			"label"=>"en-us|IP Address",
			"searchable"=>true
		),
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim,name",
			"updateFunction"=>"db_lanHardware_update"
		),
		"type"=>array(
			"label"=>"en-us|Type",
			"type"=>"select",
			"searchable"=>true
		),
		"user"=>array(
			"label"=>"en-us|User",
			"type"=>"objectDropDown",
			"ref"=>"db/user",
			"useID"=>1
		),
		"uid"=>array(
			"label"=>"en-us|UID / UUID",
			"searchable"=>true,
			"cleaners"=>"trim"
		),
		"lastSeen"=>array(
			"label"=>"en-us|Last Seen",
			"type"=>"date"
		)
	)
);
