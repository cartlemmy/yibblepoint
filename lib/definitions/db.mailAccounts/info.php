<?php

function db_mailAccounts_update($v,&$items,$tableInfo) {
	$items["nameSafe"] = safeName($v);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Mail Accounts",
	"singleName"=>"en-us|Mail Account",
	"table"=>"db/mailAccounts",
	"key"=>"id",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"orderby"=>"name",
	"userField"=>"userId",
	"required"=>"name",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim",
			"updateFunction"=>"db_mailAccounts_update"
		)
	)
);
