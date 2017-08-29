<?php


ob_start();

require_once(SL_LIB_PATH."/cron/blast/extras.php");
require_once(SL_LIB_PATH."/cron/blast/class.emailBlast.php");
	
$blast = new emailBlast($_SERVER["QUERY_STRING"]);

if (isset($_POST["unsubscribe"])) $blast->queueAction("unsubscribe");

if ($blast->isFlagSet("unsubscribe")) {
	?><h3><?=format(translate("en-us|Unsubscribed"));?></h3><?php
	echo format(translate("en-us|The address %% has already been unsubscribed."),$blast->contact["emailPrimary"]);
} else {
	?><h3><?=format(translate("en-us|Unsubscribe"));?></h3><?php
	echo format(translate("en-us|You have been subscribed as %%, if you do not want to receive any more emails from us click the below button:"),$blast->contact["emailPrimary"]);
	?><form method="post">
		<input type="submit" name="unsubscribe" value="<?=translate("en-us|UNSUBSCRIBE ME");?>">
	</form><?php
}

require_once(SL_INCLUDE_PATH."/class.slWeb.php");

//It's a web page, render it
$web = new slWeb(null, $GLOBALS["slConfig"]["web"]);

$web->setContent(ob_get_clean());

$web->prepareWebPage();
$web->render();
			

