<?php

return array(
	"coreTable"=>true,
	"name"=>"en-us|Transactions",
	"singleName"=>"en-us|Transaction",
	"table"=>"db/transactions",
	"key"=>"id",
	"displayName"=>array("item.title"),
	"nameField"=>"title",
	"orderby"=>"ts",
	"orderdir"=>"desc",
	"userField"=>"userId",
	"required"=>"title",
	"fields"=>array(
		"ts"=>array(
			"label"=>"en-us|Timestamp",
			"type"=>"date",
			"default"=>"=sl.unixTS()"
		),
		"title"=>array(
			"label"=>"en-us|Title",
			"searchable"=>true,
		),
		"type"=>array(
			"label"=>"en-us|Type",
			"type"=>"select",
			"options"=>array(
				""=>"en-us|Other",
				"expense"=>"en-us|Expense",
				"sale"=>"en-us|Sale"
			)
		),
		"category"=>array(
			"label"=>"en-us|Category",
			"multi"=>true,
			"type"=>"object",
			"ref"=>"db/transactionCategories"
		),
		"amount"=>array(
			"label"=>"en-us|Amount",
			"type"=>"currency",
			"updateJS"=>file_get_contents(realpath(dirname(__FILE__))."/amount.js"),
			"total"=>"sum"
		)
	)
);
