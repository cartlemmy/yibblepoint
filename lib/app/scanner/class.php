<?php

class slScanner extends slAppClass {
	private $setup = false;
	
	function __construct($app) {
		$this->app = $app;
	}
	
	function info($type, $v) {
		$rv = array("type"=>$type,"scanValue"=>$v,"tinyID"=>0);
		switch ($type) {
			case "QR":
				$eid = false;
				if (strpos($v,"tiny?") !== false) {
					$eid = array_pop(explode("tiny?",$v));
				} elseif (strpos($v,"/") === false) {
					$eid = $v;
				} else $rv["url"] = $v;
				
				if ($eid === false) {
					$rv["error"] = "No tiny ID";
				} else {
					$ri = new slRequestInfo("?".$eid);
					$g = $ri->get();
					$rv["tinyID"] = (int)$g["params"];
				}
				break;
			
			default:
				$rv["tinyID"] = (int)ltrim(substr($v,0,-1),"0");
				break;
		}
				
		if ($rv["tinyID"]) {
			require_once(SL_INCLUDE_PATH."/class.slURLShortener.php");
			$url = new slURLShortener();
	
			if ($url->fromID($rv["tinyID"])) {
				$rv["url"] = $url->getLink();
				$rv["name"] = $url->getName();
				$match = array();
				if (preg_match("/db\/([\w\d]+)\&([^&]+)/",$rv["url"],$match)) {
					$rv["ref"] = "db/".$match[1];
					$rv["id"] = $match[2];
					if ($res = $GLOBALS["slCore"]->db->select($rv["ref"],array("_KEY"=>$rv["id"]),array("select"=>array("*","_NAME"),"limit"=>1))) {
						$rv["data"] = $GLOBALS["slCore"]->db->fetch($rv["ref"],$res);
					}
				}
			} else {
				$rv["error"] = "Bad tiny ID";
			}
		}
		return $rv;
	}
}
