<?php

function db_organizations_name_upd($conn, $name, $postalCode) {
	$nameSafe = safeName($name.($postalCode ? '-'.$postalCode : ''));
	return "`name`='".$conn->escape_string($name)."',".
		"`nameSafe`='".$nameSafe."',".
		"`nameSafeHash`=0x".db_organizations_name_hash($nameSafe);
}

function db_organizations_name_hash($v) {
	$size = 2;
	$rv = substr(md5($v),0,$size * 2);
	return $rv == str_repeat("00",$size) ? str_repeat("0",$size * 2 - 1)."1" : $rv;
}

function db_organizations_update($v,&$data,$tableInfo) {
	$postalCode = false;
	if (setAndTrue($data,"postalCode")) {
		$postalCode = array_shift(preg_split('/[^\d]+/',trim($data["postalCode"])));
	}
	
	$conn = $GLOBALS["slCore"]->db->connection['db']->conn;
	
	//Is this a parent org?
	$query = "SELECT * FROM `organizations` WHERE `parent`=".$data["id"];
	if (($res = $conn->query($query)) && $res->num_rows) {
		$postalCode = false;
		$data["postalCode"] = "";
		while ($childOrg = $res->fetch_assoc()) {
			if ($childOrg["name"] != $data["name"]) {
				$query = "UPDATE `organizations` SET ".db_organizations_name_upd($conn,$data["name"],$childOrg["postalCode"])." WHERE `id`=".$childOrg["id"];
				$conn->query($query);
			}			
		}
	}
	
	if ($postalCode) {	
		$query = "SELECT * FROM `organizations` WHERE ".(
				setAndTrue($data,"parent") ?
					"`id`=".(int)$data["parent"] :
					"`name`='".$conn->escape_string($data["name"])."' AND `postalCode`=''".(setAndTrue($data,"id") ? " AND `id`!='".(int)$data["id"]."'" : "")
		)." LIMIT 1";
		
		if (($res = $conn->query($query)) && $res->num_rows) {
			$parentOrg = $res->fetch_assoc();
			if ($parentOrg["name"] != $data["name"]) {
				//Update parent name
				$query = "UPDATE `organizations` SET ".db_organizations_name_upd($conn,$data["name"],'')." WHERE `id`=".$parentOrg["id"];
				$conn->query($query);
				
				//Update child names
				$query = "SELECT * FROM `organizations` WHERE `parent`=".$parentOrg["id"]." AND `id`!=".(int)$data["id"];
				if (($res = $conn->query($query)) && $res->num_rows) {
					while ($childOrg = $res->fetch_assoc()) {
						$query = "UPDATE `organizations` SET ".db_organizations_name_upd($conn,$data["name"],$childOrg["postalCode"])." WHERE `id`=".$childOrg["id"];
						$conn->query($query);
					}
				}				
			}
			$data["parent"] = $parentOrg["id"];			
		} else {
			$query = "INSERT INTO `organizations` SET ".
				"`parent`=0,".
				"`userId`=".$data["userId"].",".
				db_organizations_name_upd($conn,$data["name"],'').",".
				"`created`=".time().", `updated`=".time().",".
				"`postalCode`='',".
				"`type`='".$conn->escape_string($data["type"])."'";

			$conn->query($query);
			$data["parent"] = $conn->insert_id;
		}
	}
	
	$data["nameSafe"] = safeName($data["name"].($postalCode ? '-'.$postalCode : ''));
	//$data["nameSafeHash"] = array("_hex", db_organizations_name_hash($data["nameSafe"]));
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Organizations",
	"singleName"=>"en-us|Organization",
	"table"=>"db/organizations",
	"key"=>"id",
	"unique"=>"nameSafe",
	"displayName"=>array("item.name"),
	"nameField"=>"name",
	"nameSafeField"=>"nameSafe",
	"orderby"=>"name",
	"userField"=>"userId",
	"historyField"=>"history",
	"required"=>"name",
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"searchable"=>true,
			"cleaners"=>"trim,name",
			"dependency"=>"userId,id,parent,postalCode",
			"updateFunction"=>"db_organizations_update"
		),
		"postalCode"=>array(
			"label"=>"en-us|Postal Code",
			"searchable"=>true,
			"cleaners"=>"trim",
			"dependency"=>"userId,id,parent,name",
			"updateFunction"=>"db_organizations_update"
		),
		"type"=>array(
			"label"=>"en-us|Type",
			"type"=>"select",
			"default"=>"",
			"options"=>array(
				""=>"en-us|Unknown",
				"business"=>"en-us|Business",
				"charity"=>"en-us|Charity",
				"non-profit"=>"en-us|Non Profit",
				"sole-prop"=>"en-us|Sole Proprietorship",
				"school"=>"en-us|School",
				"political"=>"en-us|Political",
				"club"=>"en-us|Club",
				"church"=>"en-us|Church",
				"band"=>"en-us|Band/Musician",
				"perform-art"=>"en-us|Performing Arts",
				"government"=>"en-us|Government Agency"
			)
		)
	)
);
