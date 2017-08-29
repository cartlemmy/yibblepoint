<?php

function getDefinition($n) {
	$path = SL_LIB_PATH."/definitions/".safeFile($n).".json";
	if (is_file($path)) return json_decode(fileGetLock($path),true);
	return false;
}
