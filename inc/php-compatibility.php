<?php

//Compatibility with PHP versions below 5

$def = array(
	'JSON_ERROR_NONE'=>0,
	'JSON_ERROR_DEPTH'=>1,
	'JSON_ERROR_STATE_MISMATCH'=>2,
	'JSON_ERROR_CTRL_CHAR'=>3,
	'JSON_ERROR_SYNTAX'=>4,
	'JSON_ERROR_UTF8'=>5,
	'JSON_ERROR_RECURSION'=>6,
	'JSON_ERROR_INF_OR_NAN'=>7,
	'JSON_ERROR_UNSUPPORTED_TYPE'=>8
);
foreach ($def as $n=>$v) {
	if (!defined($n)) define($n,$v);
}
	
if (!function_exists('json_last_error')) {
	function json_last_error() {
		return JSON_ERROR_NONE;
	}
}

// TODO: move the rest of the compatibility functions here
