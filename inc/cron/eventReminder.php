<?php

//Run:every minute

if ($res = $GLOBALS["slCore"]->db->select("db/userEvent","`alerted`=0 AND `status`<2 AND `dueTs`!=0 AND `dueTs`<=".(time()+5*60+5))) {
	while ($task = $res->fetch()) {
		if ($r2 = $GLOBALS["slCore"]->db->select("db/user",array("id"=>$task["assignedTo"]))) {
			$u = $r2->fetch();
			$GLOBALS["slCore"]->dispatch($u["user"].":alert",array(
				"title"=>"Upcoming task",
				"message"=>$task["description"]." @ ".date($GLOBALS["slConfig"]["international"]["time"],$task["dueTs"])
			));
			$GLOBALS["slCore"]->db->update("db/userEvent",array("alerted"=>1),array("id"=>$task["id"]));
		}
	}
}


