<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class loginApp extends slAppClass {		
	function mobilePass() {
		if (!requireThirdParty('phpqrcode',true)) return false;
		
		$token = new secuToken(array("expires"=>"+15 minutes","page"=>"sl/mobile-pass.qr.png"));

		$publicKey = $token->generateKey(32);

		$token->create(array(
			"type"=>"login",
			"publicKey"=>$publicKey,
			"session"=>session_id()
		));
	
		return $token->getUrl();
	}
}
