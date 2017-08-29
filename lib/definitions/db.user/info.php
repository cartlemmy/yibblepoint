<?php

class db_user extends slDBDefinition {
	function insertTable($data, $find, $options) {
		if (!setAndTrue($data,"parentId")) $data["parentId"] = $GLOBALS["slSession"]->user->getParentId();
		$user = new slUser();
		$user->create($data);
		return $user->get("id");
	}
	
	function update_email($v,&$items,$tableInfo) {
		if (trim($items["validation"])) {
			$validation = explode(",",$items["validation"]);
			if (($pos = array_search("email",$validation)) !== false) {
				array_splice($validation, $pos, 1);
				$items["validation"] = implode(",",$validation);
			}
		}
	}

	function update_password($v,&$items,$tableInfo) {
		$user = new slUser(false,$items["id"]);
		$user->setPassword($v);
		$user->__destruct();
		unset($items["password"]);
	}

	public function getDefinition() {
		return array(
			"coreTable"=>true,
			"name"=>"en-us|Users",
			"singleName"=>"en-us|User",
			"table"=>"db/user",
			"key"=>"id",
			"unique"=>"user",
			"displayName"=>array("item.name","item.user"),
			"orderby"=>"user",
			"userField"=>"parentId",
			"permissions"=>array("admin OR useradmin"),
			"fields"=>array(
				"name"=>array(
					"label"=>"en-us|Name",
					"searchable"=>1,
					"cleaners"=>"trim,name"
				),
				"email"=>array(
					"dependency"=>"validation",
					"label"=>"en-us|E-mail",
					"searchable"=>1,
					"cleaners"=>"trim",
					"validate"=>"email,unique-email,not-empty",
					"type"=>"email"
				),
				"user"=>array(
					"label"=>"en-us|User",
					"searchable"=>1,
					"validate"=>"user,not-empty"
				),
				"password"=>array(
					"dependency"=>"id",
					"label"=>"en-us|Password",
					"viewable"=>false,
					"writeOnly"=>true,
					"validate"=>"password,not-empty",
					"type"=>"setPassword"
				),
				"image"=>array(
					"label"=>"en-us|Image",
					"type"=>"image",
					"viewable"=>false,
					"useAsIcon"=>true
				),
				"permissions"=>array(
					"label"=>"en-us|Permissions",
					"type"=>"multiselect",
					"options"=>array(
						"user"=>"!NOEDIT","session"=>"!NOEDIT",
						"admin"=>"en-us|Admin","useradmin"=>"en-us|User Admin",
						"crm"=>"en-us|CRM","office"=>"en-us|Office",
						"site"=>"en-us|Site Management",
						"seo"=>"en-us|SEO","cms"=>"en-us|CMS",
						"developer"=>"en-us|Developer","office"=>"en-us|Office",
						"store"=>"en-us|Store",
						"media"=>"en-us|Media"
					),
					"force"=>array((isset($GLOBALS["slSession"]) && $GLOBALS["slSession"]->user->isChild?"":"admin")=>array(true,"en-us|The parent account cannot deselect admin permission."))
				)
			)
		);
	}
}
