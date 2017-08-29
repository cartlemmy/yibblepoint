<?php

return;

$categories = explode(",",$data["categories"]);

$departments = require(SL_LIB_PATH."/departments.php");

if ($res = $GLOBALS["slCore"]->db->select("db/userEvent",array("_KEY"=>$data["_KEY"]))) {
	$formatted = $res->fetchAsText();
} else $formatted = array();

foreach ($departments as $dept=>$to) {
	if (in_array($dept,$categories)) {
		$out = false;
		if (isset($changed["status"])) {
			switch ($changed["status"]) {
				case 1: // Open
					$out = array("type"=>"note","action"=>"Task Started","assignedTo"=>$formatted["assignedTo"],"ts"=>$data["startTs"]);
					break;
					
				case 2: // Complete
					$out = array("type"=>"note","action"=>"Task Completed","assignedTo"=>$formatted["assignedTo"],"ts"=>$data["endTs"]);
					break;
			}
		} elseif (isset($changed["assignedTo"])) {
			$out = array("type"=>"note","action"=>"Assigned To ".$formatted["assignedTo"],"assignedTo"=>$formatted["assignedTo"]);
			if ($data["assignedTo"] != $_SESSION["userID"]) {
				require_once(SL_INCLUDE_PATH."/class.slCommunicator.php");
				$com = new slCommunicator();

				$com->addRecipient("user/".$data["assignedTo"]);
				
				$name = array_shift(explode("\n",$data["description"]));
				if (strlen($name) > 32) $name = trim(substr($name,0,32))."...";
				
				$com->setSubject("New Task - ".$name);
				
				$link = WWW_BASE."/".SL_CORE_NAME."/edit?db/userEvent&".$data["_KEY"];
				
				?>
				<p>A new task has been assigned to you:</p>
				<p><?=str_replace("\n","<br />",$data["description"]);?></p>
				<p><a href="<?=$link;?>"><?=$link;?></a></p>
				<?php
				
				$com->setMessage(ob_get_clean(),true);

				$com->send();
			}
		}
		
		if (isset($oldData["_INSERTED"])) {
			$out = array("type"=>"added");
		}
		
		if ($out) {
			if (!isset($out["ts"])) $out["ts"] = time();
			$out["task"] = $data["_KEY"];
			file_put_contents(SL_DATA_PATH."/notify-queue/".$dept,json_encode($out)."\n",FILE_APPEND);
		}
	}
}


