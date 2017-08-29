<?php

class slPermissions extends slClass {
	private $core;
	private $file = false;
	private $lastResponse = false;
	private $row = null;	
	
	function __construct($ref, $core) {	
		$ref = preg_replace("/[^\w\d]+/",".",$ref);
		
		$this->core = $core;
			
		$this->file = $GLOBALS["slConfig"]["root"]."/lib/definitions/".$ref."/permissions.php";
		
		if (!is_file($this->file)) {
			$lastFile = $this->file;
			list($ref,) = explode(".",$ref);
			
			$this->file = $GLOBALS["slConfig"]["root"]."/lib/definitions/".$ref."/permissions.php";
			if (!is_file($this->file)) {
				$this->setResponse("No permissions file ".$this->file." or ".$lastFile);
				$this->file = false;
			}
		}
	}
	
	function processRow($row) {
		$this->row = $row;
		$denied = array();
		foreach ($row as $n=>$v) {
			if (!$this->can("read",$n)) {
				$row[$n] = null;
				$denied[$n] = $this->getLastResponse();
			}
		}
		if ($denied) $row["denied"] = $denied;
		return $row;
	}
		
	function can($type, $field = false) {
		if ($this->file === false) return false;
		
		if (!is_array($type)) $type = array($type);
		
		$this->lastResponse = "";
		
		$row = $this->row;
		
		$can = require($this->file);
		
		if (is_array($can)) {
			foreach ($type as $t) {
				if (!in_array($t,$can)) return false;
			}
			return true;
		} else return !!$can;
	}
	
	function setResponse($response) {
		$this->lastResponse = $response;
		return false;
	}
	
	function getLastResponse() {
		return $this->lastResponse;
	}
}
