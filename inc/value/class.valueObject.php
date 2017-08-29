<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class valueObject extends slValueDefinition {
	function toString() {
		$id = $this->value->value;
		if (preg_replace('/[^\d]+/','',$id) != '' && (int)$id) {
			if ($res = $GLOBALS["slCore"]->db->select($this->value->fieldDesc["ref"],array("_KEY"=>(int)$id),array("limit"=>1,"select"=>array("_NAME")))) {
				$row = $res->fetch();
				return $row["_NAME"];
			}
		}
		return is_string($this->value->value) && $this->value->value !== "0" ? array_pop(explode(";",$this->value->value,2)) : "";
	}
	
	function fromString($string) {
		if ($res = $GLOBALS["slCore"]->db->select($this->value->fieldDesc["ref"],array("_NAME"=>$string),array("limit"=>1))) {
			$row = $res->fetch();
			$this->value->value = $row["_UNIQUE"].";".$string;
		} else $this->value->value = $string;
	}
}
