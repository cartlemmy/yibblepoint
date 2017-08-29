<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class slWebForm extends slClass {	
	private $submitTo;
	public $desc;
	public $table;
	public $where;
	private $fields = array();
	private $method = "post";
	private $ri;
	public $submitLabel = "en-us|Submit";
	
	function __construct() {
		$this->ri = new slRequestInfo();
		$this->fieldTemplate = '<fieldset><label for="user">[label]</label>[field]</fieldset>';
	}
	
	function buildFromTable($table,$where) {
		$this->table = $table;
		$this->where = $where;
		if ($this->desc = $GLOBALS["slCore"]->db->getTableInfo($table)) {
			if ($res = $GLOBALS["slCore"]->db->select($table,$where,array("limit"=>1))) {
				$item = $GLOBALS["slCore"]->db->fetch($table,$res);
				
				foreach ($this->desc["fields"] as $n=>$field) {
					$this->addField($field["type"],$n,$item[$n],$field);
				}
			}
		}
		$this->processSubmission();
	}
	
	function addField($type, $n, $value = null, $desc = null) {
		$value = new slValue($this->table,$n,$value);
		$field = array(
			"type"=>$type,
			"n"=>$n,
			"value"=>$value
		);
		
		if ($desc) $field = array_merge($field,$desc);
		
		$this->fields[] = $field;
	}
	
	function show() {
		echo "<form action=\"".($this->method == "get" ? $this->ri->request["path"] : $this->ri->getLink())."\" method=\"".$this->method."\">\n";
		if ($this->method == "get") {
			foreach ($this->ri->request["params"] as $n=>$v) {
				echo "<input type=\"hidden\" name=\"".htmlspecialchars($n)."\" value=\"".htmlspecialchars($v)."\">\n";
			}
		}
		
		
		foreach ($this->fields as $field) {
			echo tagParse($this->fieldTemplate,array("label"=>$field["label"],"field"=>$this->getFieldAsHTML($field)));
		}
		
		echo "<input type=\"submit\" name=\"submit\" value=\"".$this->submitLabel."\">";
		echo "</form>\n";
	}
	
	function getFieldAsHTML($field) {
		switch ($field["type"]) {
			case "text":
			default:
				return "<input type=\"text\" name=\"".$field["n"]."\" id=\"".$field["n"]."\" value=\"".htmlspecialchars($field["value"]->toString())."\">";
		}
	}
	
	function processSubmission() {
		if (isset($_POST["submit"])) {
			$set = array();
			foreach ($this->fields as $field) {
				$field["value"]->fromString($_POST[$field["n"]]);
				$set[$field["n"]] = $field["value"]->value;
			}
			$GLOBALS["slCore"]->db->update($this->table,$set,$this->where);
		}
	}
	
}
