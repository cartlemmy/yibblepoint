<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

ob_start();

$token = new secuToken();

$mobilePassNotSet = false;

if ($res = $token->get()) {
	if (isset($_COOKIE["slMobilePass"])) {
		list($user,$privateKey) = explode(";",$_COOKIE["slMobilePass"],2);
		if (!isset($res["user"])) {
			$res["user"] = $user;
		}
				
	} elseif (!isset($res["user"]))  {
		$mobilePassNotSet = true;
		echo translate("en-us|Mobile pass has not been set up on this device yet.");
	}
	
	if (isset($res["user"])) {
		$userDir = LGPHP_ROOT_DIR."/data/users/".$res["user"];
		
		require_once(SL_INCLUDE_PATH."/class.fileData.php");
		$mobilePassFD = new fileData($userDir);
		
		if (isset($privateKey) && !$mobilePassFD->get("mobile-pass",$privateKey) && isset($_COOKIE["slMobilePass"])) {
			setcookie ("slMobilePass", "", time() - 3600);
		}
		
		switch ($res["type"]) {
			case "login":
					if ($mobilePassFD->get("mobile-pass",$privateKey)) {
						echo translate("en-us|Logging in...");
						$GLOBALS["slCore"]->dispatch("!".$res["session"].":mobile-pass-login",array("key"=>sha1($privateKey."-".$res["session"]),"user"=>$res["user"]));
					}
					break;
					
			case "new-device":
				if (isset($_COOKIE["slMobilePass"])) {
					?><h3>Mobile Pass</h3>
					<?= translate("en-us|Silverleaves Mobile Pass is already set up for this device.");?>
					<?php
				} elseif (isset($_POST["name"])) {
					?><h3>Mobile Pass</h3>
					<?php echo translate("en-us|Silverleaves Mobile Pass has been successfully enabled for this device.");
		
					$expires = time() + 86400 * 365;
					$privateKey = $token->generateKey(32);
					
					$o = array(
						"name"=>preg_replace("/[^\w\d\-\_\!\']+/"," ",$_POST["name"]),
						"expires"=>$expires
					);
					
					$mobilePassFD->set("mobile-pass",$privateKey,$o);
					
					$o["_KEY"] = $privateKey;
					$GLOBALS["slCore"]->dispatch($res["user"].":mobile-pass-added",$o);
					
					setcookie("slMobilePass",$res["user"].";".$privateKey,$expires);
				} else {
					?><h3>Mobile Pass Setup</h3>
					<form method="post">
						<fieldset>
							<label for="name"><?= translate("en-us|Name For This Device");?></label>
							<input name="name" id="name" type="text" value="">
						</fieldset>
						<input type="submit" value="<?=translate("en-us|ENABLE");?>">					
					</form>
					<?php
				}
				break;
		}
	} else if (!$mobilePassNotSet) echo translate("en-us|Token does not define a user.");
} else echo translate("en-us|".$token->getLastError());

require_once(SL_INCLUDE_PATH."/class.slWeb.php");
//It's a web page, render it
$web = new slWeb(null, $GLOBALS["slConfig"]["web"]);

$web->setContent(ob_get_clean());

$web->prepareWebPage();
$web->render();
			

