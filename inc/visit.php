<?php

function recordVisitData($action,$data) {
	$file = SL_DATA_PATH."/web-visits/".date("Y");
	$data["a"] = $action;
	$data["id"] = $GLOBALS["_SESSION"]["VISIT_RECORDED"];
	$data["ts"] = time();
	file_put_contents($file,json_encode($data)."\n",FILE_APPEND);
};
