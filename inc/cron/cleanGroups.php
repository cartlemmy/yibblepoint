<?php

//Run:every month

$GLOBALS["slCore"]->db->update("db/groups",array("links"=>0),"1");
if ($res = $GLOBALS["slCore"]->db->select("db/groups","1")) {
	while ($group = $res->fetch()) {
		echo "Checking group '".$group["name"]."'\n";
		$info = $GLOBALS["slCore"]->db->getTableInfo($group["ref"]);
		$removed = $deleted = $links = 0;
		if ($r2 = $GLOBALS["slCore"]->db->select("db/groupLink",array("groupId"=>$group["id"]))) {
			while ($groupLink = $r2->fetch()) {
				if ($r3 = $GLOBALS["slCore"]->db->select($group["ref"],array("_KEY"=>$groupLink["linkedId"]),array("limit"=>"1"))) {
					$row = $r3->fetch();
					$groups = explode(",",$row[$info["groupField"]]);
					if (in_array($group["name"],$groups)) {
						$links++;
					} else {
						$GLOBALS["slCore"]->db->delete("db/groupLink",array("id"=>$groupLink["id"]));
						$removed++;
					}
				} else {
					$GLOBALS["slCore"]->db->delete("db/groupLink",array("id"=>$groupLink["id"]));
					$deleted++;
				}
			}
		}
		if ($deleted) echo "$deleted rows deleted.\n";
		if ($removed) echo "$removed group links removed.\n";
		echo "$links group links remain.\n";
		$GLOBALS["slCore"]->db->update("db/groups",array("links"=>$links),array("id"=>$group["id"]));
		echo "\n";
	}
}
