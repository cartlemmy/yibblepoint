<?php

require_once(SL_INCLUDE_PATH."/class.slCommunicator.php");
require_once(SL_INCLUDE_PATH."/class.slValue.php");

class formSubmission {
	public $name;
	private $com;
	private $fields = array();
	public $customEmail = false;
	public $creationType = false;
	
	function __construct($name) {
		$this->name = $name;
		$this->com = new slCommunicator();
		$this->com->setSubject($name);
	}
	
	function setFields($fields) {
		$this->fields = array_merge($this->fields,$fields);
	}
	
	function setField($n,$o) {
		$this->fields[$n] = $o;
	}

	function addRecipient($to,$name = false) {
		return $this->com->addRecipient($to,$name);
	}

	function addAttachment($path,$name = '',$encoding = 'base64',$type = 'application/octet-stream') {
		if (!(strpos($path,"\n") === false && is_file($path))) {
			$file = SL_DATA_PATH."/tmp/".md5($path);
			file_put_contents($file,$path);
			$path = $file;
		}
		return $this->com->addAttachment($path,$name,$encoding,$type) ;
	}
	
	function getReadable($data) {
		$rv = array();
		foreach ($data as $n=>$v) {
			$label = isset($this->fields[$n]) && isset($this->fields[$n]["label"]) ? $this->fields[$n]["label"] : $n;
			switch (isset($this->fields[$n]) && isset($this->fields[$n]["type"]) ? $this->fields[$n]["type"] : "text") {
				case "date":
					$rv[$label] = valueToString($v,$this->fields[$n]);
					break;
					
				default:
					$rv[$label] = $v;
					break;
			}
		
		}
		return $rv;		
	}
	
	function submit($data = false) {
		if (!$data) {
			$data = array();
			foreach ($this->fields as $n=>$o) {
				$data[$n] = $o["value"];
			}
		}
		$md5 = md5(strtolower(json_encode($data)));
		
		//Prevent duplicate submissions
		if (isset($_SESSION["LAST_FORM_SUBMISSION"]) && $_SESSION["LAST_FORM_SUBMISSION"] == $md5) return true;
		$_SESSION["LAST_FORM_SUBMISSION"] = $md5;
		
		$readable = $this->getReadable($data);
		
		$message = array();
		foreach ($readable as $n=>$v) {
			if (is_string($v) || is_numeric($v)) $message[] = $n.": ".$v;
		}
		
		if (setAndTrue($_POST,"email")) {
			$contact = new slContact($_POST["email"],true,$this->creationType?$this->creationType:"form","super");
			$contact->update($data);
			$data["contact"] = $contact->data["id"];
			$this->fields["contact"] = array(
				"label"=>"en-us|Contact",
				"type"=>"object",
				"ref"=>"db/contacts",
				"useID"=>1
			);
		}
	
		$submitted = array();
		foreach ($data as $n=>$v) {
			$field = isset($this->fields[$n]) ? $this->fields[$n] : array();
			$submitted[$n] = array_merge($field,array("value"=>$v));
		}
		
		if ($this->customEmail && is_file($file = SL_WEB_PATH."/inc/form-emails/".safeFile($this->customEmail))) {
			ob_start();
			require($file);
			$this->com->setMessage(ob_get_clean());
		} else {
			$this->com->setMessage(implode("\n",$message));
		}
		$this->com->send();
		
		$this->storeSubmission(array(
			"timestamp"=>time(),
			"contact"=>isset($data["contact"]) ? $data["contact"] : 0,
			"name"=>$this->name,
			"submitted"=>$submitted,
			"_SERVER"=>$_SERVER
		));
		
		return true;		
	}
	
	function validate($n, $v = false) {
		if (isset($this->fields[$n])) {
			$rv = array();
			$field = $this->fields[$n];
			if (!$v) $v = $field["value"];
			
			$validate = explode(",",$field["validate"]);
			foreach ($validate as $val) {
				switch ($val) {
					case "not-empty":
						if (trim($v) == "") $rv[] = "is required";
						break;
				}
			}
			if (count($rv)) return $rv;
		}
		return true;
	}
	
	function storeSubmission($data) {
		$o = array("name"=>$data["name"]);
		$data["form"] = $GLOBALS["slCore"]->db->upsert("db/forms",$o,$o);
		unset($data["name"]);
		$GLOBALS["slCore"]->db->insert("db/formSubmissions",$data);
	}
}

class simpleForm {
	public static function select($o) {
		if (isset($o["id"]) && !isset($o["name"])) $o["name"] = $o["id"];
		
		?><select<?php
			$f = array("id","name","class");
			foreach ($f as $n) {
				if (isset($o[$n]) && (is_string($o[$n]) || is_numeric($o[$n]))) echo ' '.$n.'="'.htmlspecialchars($o[$n]).'"';
			}
		?>><?php
			if (isset($o["valueFrom"])) {
				$value = isset($GLOBALS[$o["valueFrom"]][$o["name"]]) ? $GLOBALS[$o["valueFrom"]][$o["name"]] : false;
			} else $value = isset($o["value"]) ? $o["value"] : false;
			
			foreach ($o["options"] as $n=>$v) {
				?><option value="<?=htmlspecialchars($n);?>"><?=htmlspecialchars($v);?></option><?php
			}
		?>
		</select><?php
	}
}
