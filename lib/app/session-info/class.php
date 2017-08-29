<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class sessionInfo extends slAppClass {		
	function info() {
		$token = new secuToken(array("expires"=>"+1 hour","page"=>"sl/mobile-pass.qr.png"));

		$token->create(array(
			"type"=>"new-device",
			"user"=>$GLOBALS["slSession"]->user->get("user")
		));
		
		$ud = $GLOBALS["slSession"]->getUserFileData();

		return array(
			"useValidation"=>$GLOBALS["slConfig"]["user"]["validation"],
			"validation"=>$GLOBALS["slSession"]->getValidationLevel(),
			"email"=>$GLOBALS["slSession"]->user->get("email"),
			"QRAddress"=>$token->getUrl(),
			"mobilePass"=>$ud->getAllData("mobile-pass")
		);
	}
	
	function deleteMobilePass($id) {
		$ud = $GLOBALS["slSession"]->getUserFileData();
		$ud->remove("mobile-pass",$id);
		return $id;
	}
	
	function sendValidationEmail() {
		return $GLOBALS["slSession"]->user->sendEmailValidation();
	}
	
	function passwordReset($password) {
		if ($GLOBALS["slSession"]->user->setPassword($password)) {
			return true;
		}
		return false;
	}
}
