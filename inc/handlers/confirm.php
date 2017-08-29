<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

ob_start();

$token = new secuToken();

if ($res = $token->get()) {
	if (isset($res["type"])) {
		switch ($res["type"]) {
			case "email":
				if ($GLOBALS["slSession"]->setValidationLevel("email",true)) {
					echo translate("en-us|Your E-mail address is now confirmed."); 
				} else {
					echo translate("en-us|Your E-mail address has already been confirmed."); 
				}
				break;
		}
	}	
} else echo translate($token->getLastError());

require_once(SL_INCLUDE_PATH."/class.slWeb.php");
//It's a web page, render it
$web = new slWeb(null, $GLOBALS["slConfig"]["web"]);

$web->setContent(ob_get_clean());

$web->prepareWebPage();
$web->render();
			

