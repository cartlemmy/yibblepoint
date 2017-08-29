<?php

class db_supportTicket extends slDBDefinition {
	public function update_startTs($v,&$items,$tableInfo) {
		$this->updateDuration($items);
	}	
	public function getDefinition() {
		return array(
			"name"=>"en-us|Support Tickets",
			"singleName"=>"en-us|Support Ticket",
			"table"=>"db/supportTicket",
			"key"=>"id",
			"displayName"=>"item.name",
			"orderby"=>"dueTs",
			"userField"=>"userId",
			"required"=>"name",
			"fields"=>array(
				"status"=>array(
					"label"=>"en-us|Status",
					"type"=>"select",
					"indexIsValue"=>true,
					"options"=>array(
						"en-us|New",
						"en-us|Open",
						"en-us|Complete",
						"en-us|Postponed",
						"en-us|Urgent",
						"en-us|Pending Approval",
						"en-us|Cancelled",
						"en-us|Suspended"
					)
				),	
				"assignedTo"=>array(
					"label"=>"en-us|Assigned To",
					"type"=>"objectDropDown",
					"ref"=>"db/user",
					"default"=>"=sl.config.userID",
					"useID"=>1
				),
				"name"=>array(
					"label"=>"en-us|Name",
					"type"=>"text",
					"searchable"=>true
				),
				"dueTs"=>array(
					"label"=>"en-us|Due",
					"type"=>"date"
				),
				"completeTs"=>array(
					"label"=>"en-us|Completed",
					"type"=>"date",
				),
				"client"=>array(
					"label"=>"en-us|Client",
					"type"=>"object",
					"ref"=>"db/organizations",
					"useID"=>1
				),
				"project"=>array(
					"label"=>"en-us|Project",
					"type"=>"object",
					"ref"=>"db/projects",
					"useID"=>1
				)
			)
		);
	}
}
