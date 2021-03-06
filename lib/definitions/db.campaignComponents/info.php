<?php

return array(
	"coreTable"=>true,
	"name"=>"en-us|Campaign Components",
	"singleName"=>"en-us|Campaign Component",
	"table"=>"db/campaignComponents",
	"key"=>"id",
	"displayName"=>array("item.name+' ('+item.type+')'"),
	"orderby"=>"startDate",
	"orderdir"=>"desc",
	"userField"=>"userId",
	"customEdit"=>"marketing/campaignComponent",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim",
		),
		"type"=>array(
			"label"=>"en-us|Type",
			"type"=>"select",
			"default"=>"",
			"options"=>array(
				""=>"en-us|None",
				"email-blast"=>"en-us|E-mail Blast"
			),
			"tabRefresh"=>true
		),
		"status"=>array(
			"label"=>"en-us|Status",
			"type"=>"select",
			"default"=>"new",
			"editable"=>false,
			"options"=>array(
				""=>"en-us|New",
				"queued"=>"en-us|Queued",
				"sending"=>"en-us|Sending",
				"sent"=>"en-us|Sent"
			)
		),
		"cost"=>array(
			"label"=>"en-us|Cost",
			"type"=>"credits",
			"default"=>"",
			"editable"=>false,
			"total"=>"sum"
		),
		"revenue"=>array(
			"label"=>"en-us|Revenue",
			"type"=>"credits",
			"default"=>"",
			"editable"=>false,
			"total"=>"sum"
		),
		"views"=>array(
			"label"=>"en-us|Views",
			"type"=>"number",
			"default"=>"",
			"editable"=>false,
			"total"=>"sum"
		),
		"clicks"=>array(
			"label"=>"en-us|Clicks",
			"type"=>"number",
			"default"=>"",
			"editable"=>false,
			"total"=>"sum"
		),
		"rejections"=>array(
			"label"=>"en-us|Rejections",
			"type"=>"number",
			"default"=>"",
			"editable"=>false,
			"total"=>"sum"
		)
	),
	"tabs"=>array(
		"en-us|Basic Info"=>array(
			"name","organization"
		),
		"en-us|Edit"=>array(
			array("custom",'="/marketing/campaignComponent/"+item.type+"-edit"')
		)
	),
	"editView"=>array(
		"maximize"=>1
	)
);
