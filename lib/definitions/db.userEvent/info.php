<?php

class db_userEvent extends slDBDefinition {
	public function update_startTs($v,&$items,$tableInfo) {
		$this->updateDuration($items);
	}
	
	public function update_endTs($v,&$items,$tableInfo) {
		$this->updateDuration($items);
	}
	
	private function updateDuration(&$items) {
		if ($items["startTs"] && $items["endTs"] > $items["startTs"]) {
			$items["duration"] = $items["endTs"] - $items["startTs"];
		}
	}
	
	public function getDefinition() {
		$fields = array(
				"type"=>array(
					"label"=>"en-us|Type",
					"type"=>"select",
					"options"=>array(
						""=>"en-us|Select One...",
						"task"=>"en-us|Task",
						"todo"=>"en-us|TODO",
						"timeoff"=>"en-us|Time off"
					)
				),
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
				"description"=>array(
					"label"=>"en-us|Description",
					"type"=>"textarea",
					"searchable"=>true
				),
				"dueTs"=>array(
					"label"=>"en-us|Due",
					"type"=>"date"
				),
				"startTs"=>array(
					"label"=>"en-us|Start Time",
					"type"=>"date",
					"dependency"=>"endTs"
				),
				"endTs"=>array(
					"label"=>"en-us|End Time",
					"type"=>"date",
					"dependency"=>"startTs"
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
				),
				"categories"=>array(
					"label"=>"en-us|Categories",
					"singleLabel"=>"en-us|Category",
					"ref"=>"db/userEvent",
					"type"=>"group"
				),
				"estDuration"=>array(
					"label"=>"en-us|Est. Duration",
					"type"=>"duration"
				),
				"duration"=>array(
					"label"=>"en-us|Duration",
					"type"=>"duration",
					"readOnlyField"=>1
				)
			);
		
		if (defined('KANBOARD_ENABLE') && KANBOARD_ENABLE) {
			$fields["kanboardTaskID"] = array(
				"label"=>"en-us|Kanboard Task",
				"type"=>"object",
				"ref"=>"kb/task",
				"useID"=>1
			);
			//$fields["kanboardSubTaskID"] = array();
		}
		
		return array(
			"coreTable"=>true,
			"name"=>"en-us|User Event",
			"singleName"=>"en-us|Event",
			"table"=>"db/userEvent",
			"key"=>"id",
			"displayName"=>array("sl.truncate(item.description,30)"),
			"orderby"=>"startTs",
			"userField"=>"userId",
			"required"=>"description",
			"fields"=>$fields
		);
	}
}
