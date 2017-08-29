<?php

class slManageGroups extends slAppClass {
	protected $setup = false;
	protected $app;
	
	function __construct($app) {
		$this->app = $app;

		if (isset($this->app->args[0]) && $GLOBALS["slSession"]->isLoggedIn()) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup = $this->app->translateArray($this->setup);

				if (isset($this->setup["permissions"])) {
					if (!$this->app->checkPermissions($this->setup["permissions"])) return;
				}
			}
		}
		parent::__construct($app);
	}
		
	function setup() {		
		return $this->setup;
	}
	
	function getGroupType($gtId) {
		if ($gtId == 0) {
			$rv = array("id"=>0,"name"=>translate("en-us|Unmanaged Categories"));
		} elseif ($res = $GLOBALS["slCore"]->db->select("db/groupTypes",array("id"=>$gtId))) {
			$rv = $res->fetch();
		} else return false;
		$rv["groups"] = array();
		
		if ($res = $GLOBALS["slCore"]->db->select("db/groups",array("ref"=>$this->setup["table"],"groupType"=>$gtId))) {
			while ($group = $res->fetch()) {
				$rv["groups"][] = $group;
			}
		}
		return $rv;
	}
}
