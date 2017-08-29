<?php

return array(
	"coreTable"=>true,
	"name"=>"en-us|Tiny Links",
	"singleName"=>"en-us|Tiny Link",
	"table"=>"db/tiny",
	"key"=>"id",
	"displayName"=>array("item.link"),
	"nameSafeField"=>"name",
	"orderby"=>"link",
	"required"=>"link",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim,name"
		),
		"link"=>array(
			"label"=>"en-us|Link",
		)
	)
);
