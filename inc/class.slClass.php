<?php

class slClass {
	private $errors = array();
	private $requestInfo = false;
	public $silentError = false;
	public $showError = false;
	
	function log($text) {
		$GLOBALS["slCore"]->log($text);
	}
	
	function criticalError($text) {
		$this->error($text, true);
		exit();
	}
	
	function clearErrors() {
		$this->errors = array();
	}
	
	function error($text, $critical = false, $returnAsArray = false, $sendEmail = false) {
		$bt = debug_backtrace();
		array_shift($bt);
		
		for ($i = 0, $len = count($bt); $i < $len; $i++) {
			if (isset($bt[$i]["file"])) $bt[$i]["file"] = str_replace($GLOBALS["slConfig"]["root"],"~",$bt[$i]["file"]);
		}
		
		$error = array(
			"plainText"=>$text,
			"text"=>(isset($bt[0]["file"]) ? $bt[0]["file"]." ".$bt[0]["line"]." " : "").(isset($bt[0]["class"])?$bt[0]["class"]."::".$bt[0]["function"]:"")."()\n".$text,
			"backtrace"=>$bt
		);
		
		if (isset($GLOBALS["slCore"])) $GLOBALS["slCore"]->log($error["text"]);
		
		if ($this->showError) {
			trigger_error ("<pre>".$error["text"]."</pre><br />", $critical ? E_USER_ERROR : E_USER_WARNING);
		} else {
			if (!$this->silentError) {
				if ($GLOBALS["slConfig"]["dev"]["debug"]) {
					if (isset($GLOBALS["slCore"])) $GLOBALS["slCore"]->appendError($text,$critical,$bt);
				} else {
					trigger_error ("<pre>".$error["text"]."</pre><br />", $critical ? E_USER_ERROR : E_USER_WARNING);
				}
			}
		}
		
		if ($sendEmail) {
			$this->sendDebugEmail($sendEmail,"Error - ".$error["text"],print_r($bt,true)."\n\n\$_GET:\n".print_r($_GET,true)."\n\n\$_SERVER:\n".print_r($_SERVER,true));
		}
		
		$this->errors[] = $error;
		
		return $returnAsArray ? array("error"=>$this->getLastErrorText()) : false;
	}
	
	function sendDebugEmail($to,$subject,$message) {
		if (!function_exists("mail") || !mail($to,$subject,$message)) {
			echo "Failed to send E-mail.\n\tTO: $to\n$message\n\n";
		}
	}
	
	function hasErrors() {
		return count($this->errors) > 0;
	}
	
	function getLastError() {
		return count($this->errors) ? $this->errors[count($this->errors) - 1] : false;
	}
	
	function getAllErrorText($asHTML = true) {
		$text = array();
		foreach ($this->errors as $error) {
			$text[] = $asHTML ? "<li>".$error["plainText"]."</li>" : $error["plainText"];
		}
		return implode("\n",$text);
	}
	
	function getLastErrorText() {
		$error = $this->getLastError();
		return $error["plainText"];
	}
	
	function getRequestInfo() {
		if ($this->requestInfo) return $this->requestInfo;
		$ri = new slRequestInfo();
		return $this->requestInfo = $ri->get();
	}
}
