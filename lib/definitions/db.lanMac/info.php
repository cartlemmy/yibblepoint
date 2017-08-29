<?php

return array(
	"name"=>"en-us|Lan MAC Addresses",
	"singleName"=>"en-us|Lan MAC Address",
	"table"=>"db/lanMac",
	"key"=>"id",
	"unique"=>"mac",
	"displayName"=>array("item.mac"),
	"nameField"=>"mac",
	"orderby"=>"mac",
	"fields"=>array(
		"mac"=>array(
			"label"=>"en-us|MAC Address",
			"searchable"=>true
		),
		"ip"=>array(
			"label"=>"en-us|IP Address",
			"searchable"=>true
		),
		"lanHardware"=>array(
			"label"=>"en-us|Hardware",
			"type"=>"object",
			"ref"=>"db/lanHardware",
			"useID"=>1
		),
		"user"=>array(
			"label"=>"en-us|User",
			"type"=>"objectDropDown",
			"ref"=>"db/user",
			"useID"=>1
		),
		"host"=>array(
			"label"=>"en-us|Host",
			"searchable"=>true
		),
		"hardware"=>array(
			"label"=>"en-us|Hardware",
			"searchable"=>true
		),
		"dev"=>array(
			"label"=>"en-us|Device",
			"searchable"=>true
		),
		"created"=>array(
			"label"=>"en-us|Discovered",
			"type"=>"date"
		),
		"updated"=>array(
			"label"=>"en-us|Last Seen",
			"type"=>"date"
		)
	)
);
