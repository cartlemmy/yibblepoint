<?php

function db_campaigns_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}


return array(
	"coreTable"=>true,
	"name"=>"en-us|Campaigns",
	"singleName"=>"en-us|Campaign",
	"table"=>"db/campaigns",
	"key"=>"id",
	"unique"=>"nameSafe",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"userField"=>"userId",
	"required"=>"name",
	"customEdit"=>"marketing/campaign",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim",
			"updateFunction"=>"db_campaigns_update"
		),
		"organization"=>array(
			"label"=>"en-us|Organization(s)",
			"searchable"=>true,
			"singleLabel"=>"en-us|Organization",
			"multi"=>true,
			"type"=>"object",
			"ref"=>"db/organizations"
		),
	)
);
