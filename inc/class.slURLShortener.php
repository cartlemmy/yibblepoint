<?php

require_once(SL_INCLUDE_PATH."/class.slRequestInfo.php");

class slURLShortener extends slClass {	
	private $name;
	private $link;
	public $id;
	private $reqInfo;
	
	function __construct() {
		$this->reqInfo = new slRequestInfo();
	}
	
	function create($link,$name = false) {
		$this->link = $link;
		if ($name === false) {
			if ($res = $GLOBALS["slCore"]->db->select("db/tiny",array("link"=>$link))) {
				$row = $res->fetch_assoc();
				$this->id = $row["id"];
				$this->name = $row["name"];
				return;
			}
		} else {
			$name = safeName($name);
			$num = 0;
			while ($res = $GLOBALS["slCore"]->db->select("db/tiny",array("name"=>$name.($num?"-".$num:"")))) {
				$row = $res->fetch_assoc();
				if ($row["link"] == $link) {
					$this->id = $row["id"];
					$this->name = $row["name"];
					return;
				}
				$num++;
			}
			$name = $name.($num?"-".$num:"");
		}
		$a = array("link"=>$link,"name"=>"");
		if ($name !== false) $this->name = $a["name"] = $name;
		$this->id = $GLOBALS["slCore"]->db->insert("db/tiny",$a);
	}
	
	function getShortenedURL($named = false) {
		if ($named) return WWW_BASE."?go=".$this->name;
		return WWW_BASE."?".$this->reqInfo->encodeTiny($this->id);
	}	
	
	function getTinyID() {
		return $this->reqInfo->encodeTiny($this->id);
	}
	
	function getLink() {
		return $this->link;
	}
	
	function getName() {
		return $this->name ? $this->name : "";
	}
	
	function fromName($name) {
		if ($res = $GLOBALS["slCore"]->db->select("db/tiny",array("name"=>safeName($name)))) {
			$row = $res->fetch_assoc();
			$this->id = $row["id"];
			$this->name = $row["name"];
			$this->link = $row["link"];
			return true;
		}
		return false;
	}

	function fromID($id) {
		if ($res = $GLOBALS["slCore"]->db->select("db/tiny",array("id"=>(int)$id))) {
			$row = $res->fetch_assoc();
			$this->id = $row["id"];
			$this->name = $row["name"];
			$this->link = $row["link"];
			return true;
		}
		return false;
	}
}
