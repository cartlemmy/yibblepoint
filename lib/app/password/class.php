<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class passwordManager extends slAppClass {
	function __construct($app) {
		$this->app = $app;
	}
	
	function tokenCheck() {
		$token = new secuToken();
		
		if ($res = $token->get($this->app->args)) {
			return true;
		} else return translate("en-us|".$token->getLastError());
	}
	
	function restorePassword() {
		$token = new secuToken();
		
		$res = $token->get($this->app->args);
		
		if (isset($res) && $res["type"] == "password-change") {
			$GLOBALS["slSession"]->user->restorePassword($res["old"]);
			return true;
		}
		return false;
	}
	
	function passwordReset($password) {
		if ($GLOBALS["slSession"]->user->setPassword($password,true)) {
			return true;
		}
		return false;
	}
}
