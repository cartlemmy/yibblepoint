<?php

require_once(SL_INCLUDE_PATH."/class.slURLShortener.php");

$url = new slURLShortener();

if ($url->fromID(is_array($GLOBALS["slRequestInfo"]["params"]) && isset($GLOBALS["slRequestInfo"]["params"]["n"]) ? $GLOBALS["slRequestInfo"]["params"]["n"] : $GLOBALS["slRequestInfo"]["params"])) {
	header("Location: ".$url->getLink());			
}
