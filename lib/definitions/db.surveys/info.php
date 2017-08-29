<?php

return array(
	"coreTable"=>true,
	"name"=>"en-us|Surveys",
	"singleName"=>"en-us|Survey",
	"table"=>"db/surveys",
	"key"=>"id",
	"displayName"=>array("item.title+' ('+item.name+')'"),
	"nameField"=>"title",
	"orderby"=>"created",
	"customEdit"=>"contact-management/survey",
	"fields"=>array(
		"title"=>array(
			"label"=>"en-us|Title",
			"searchable"=>true,
			"cleaners"=>"trim",
			"readOnlyField"=>true
		),
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim",
			"readOnlyField"=>true
		),
		"created"=>array(
			"label"=>"en-us|Created",
			"type"=>"date",
			"readOnlyField"=>true
		)
	)
);
