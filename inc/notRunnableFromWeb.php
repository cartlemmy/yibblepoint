<?php

if (setAndTrue($_SERVER,"REMOTE_ADDR")) {
	if (isset($_GET["_SKEY"])) {
		if ($_GET["_SKEY"] == scriptHash(
			str_replace(SL_BASE_PATH."/","",$_SERVER["SCRIPT_FILENAME"]),
			array_shift(explode("-",$_GET["_SKEY"]))
		)) return true;
	}
	echo "Not runnable from the web.\n";
	exit();
}
ob_start(); // turn on output buffer
ob_implicit_flush(0);
