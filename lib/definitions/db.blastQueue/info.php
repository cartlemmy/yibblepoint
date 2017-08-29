<?php

return array(
	"coreTable"=>true,
	"name"=>"en-us|Blast Queue",
	"singleName"=>"en-us|Blast Queue Item",
	"table"=>"db/blastQueue",
	"key"=>"id",
	"displayName"=>array("item.contactId+' '+sl.formatValue('date',item.ts)"),
	"nameField"=>"contactId",
	"orderby"=>"ts",
	"userField"=>"userId",
	"fields"=>array(
		"contactId"=>array(
			"label"=>"en-us|Contact",
			"type"=>"object",
			"ref"=>"db/contacts"
		),
		"blastId"=>array(
			"label"=>"en-us|Blast",
			"type"=>"object",
			"ref"=>"db/campaignComponents"
		),
		"state"=>array(
			"label"=>"en-us|State",
			"type"=>"select",
			"options"=>array(
				"en-us|Queued",
				"en-us|Sent",
				"en-us|Send Failed",
				"en-us|Fail: Bounce Back"
			)
		),
		"flags"=>array(
			"label"=>"en-us|Flags",
			"type"=>"bitMask",
			"default"=>1,
			"viewable"=>false,
			"options"=>array(
				"en-us|Sent",					// 1
				"en-us|Viewed",				// 2
				"en-us|Clicked",			// 4
				"en-us|Failed",				// 8
				"en-us|Unsubscribed"	// 16
			)
		),
	)
);
