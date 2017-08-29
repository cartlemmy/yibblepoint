<?php

return array(
	"coreTable"=>true,
	"name"=>"en-us|Form Submissions",
	"singleName"=>"en-us|Submission",
	"table"=>"db/formSubmissions",
	"key"=>"id",
	"displayName"=>array("item.form+item.timestamp"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"required"=>"name",
	"fields"=>array(
		/*"form"=>array(
			"label"=>"en-us|Form",
			"type"=>"object",
			"ref"=>"db/forms",
			"useID"=>1
		),*/
		"contact"=>array(
			"label"=>"en-us|Contact",
			"type"=>"object",
			"ref"=>"db/contacts",
			"useID"=>1
		),
		"timestamp"=>array(
			"label"=>"en-us|Timestamp",
			"type"=>"date"
		)
	)
);
