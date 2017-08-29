<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class slWebForm2 extends slClass {	
	private $fields = array();
	private $ri;
	private $web = false;
	public $jsRef = "sl.wf";
	
	function __construct($fields = false) {
		$this->ri = new slRequestInfo();
		
		if (!isset($GLOBALS["_SL_WF_UID"])) $GLOBALS["_SL_WF_UID"] = 1;
		$this->uid = $GLOBALS["_SL_WF_UID"]++;
		
		if ($fields) $this->setFields($fields);
	}
	
	function setFields($fields) {
		foreach ($fields as $n=>$field) {
			$this->addField($n,$field);
		}
	}
	
	function getField($n) {
		return isset($this->fields[$n]) ? $this->fields[$n] : false;
	}
	
	function addField($n,$field) {
		$this->fields[$n] = $field;
		$this->set($n,$this->get($n));
	}
	
	function submitted() {
		$wasSubmitted = false;
		foreach ($this->fields as $n=>$field) {
			if (isset($_POST[$n])) {
				$this->set($n,$_POST[$n]);
				$wasSubmitted = true;
			}
		}
		return $wasSubmitted;
	}
	
	function field($n) {
		return 'name="'.$n.'" data-slwfid="'.$n."-".$this->uid.'" value="'.htmlspecialchars($this->get($n)).'"';
	}
	
	function fieldMessage($n) {
		return 'data-slwfmid="'.$n."-".$this->uid.'" style="display:none"';
	}
	
	function submit() {
		return 'data-slwf-sumbit="1"';
	}
	
	function get($n) {
		if ($field = $this->getField($n)) {
			if (isset($field["getter"])) {
				return call_user_func_array($field["getter"],array($n));
			}
			return $GLOBALS["slSession"]->getUserData("webform-".$n);
		}
		return false;
	}
	
	function set($n,$v) {
		if ($field = $this->getField($n)) {
			$this->fields[$n]["value"] = $v;
			$this->fields[$n]["noValueInit"] = true;
			if (isset($field["setter"])) {
				return call_user_func_array($field["setter"],array($n, $v));
			}
			return $GLOBALS["slSession"]->setUserData("webform-".$n, $v);
		}
		return false;
	}
		
	function attachToWeb($web) {
		if ($this->web) return; // Already attached
		
		$web->addScript(WWW_RELATIVE_BASE."web.js?webform");
		
		?><script type="text/javascript">
		
			sl.addLoadListener(function(){
				<?=$this->jsRef;?> = new sl.webForm(<?=json_encode(array(
					"fields"=>$this->fields,
					"uid"=>$this->uid
				));?>);
			
			});
			
		</script><?php
		$this->web = $web;
	}
}
