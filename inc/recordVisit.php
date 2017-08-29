<?php

$db = new slDB();

$cntFile = SL_DATA_PATH."/web-visits/cnt";

$id = (is_file($cntFile) ? (int)file_get_contents($cntFile) : 0) + 1;

$_SESSION["VISIT_RECORDED"] = $id;

recordVisitData("start",array(
	"uri"=>$_SERVER["REQUEST_URI"],
	"agent"=>$_SERVER["HTTP_USER_AGENT"],
	"ip"=>$_SERVER["REMOTE_ADDR"],
	"referrer"=>isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : ""
));

file_put_contents($cntFile,$id);
