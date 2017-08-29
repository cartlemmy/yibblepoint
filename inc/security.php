<?php

function encryptToConfig($v) {
	if ($v == "") return $v;
	return array("!ENCRYPTED",doEncOrDec($v));
}

function doEncOrDec($v,$encrypt = true) {
	if ($v == "") return $v;
	$td = mcrypt_module_open('rijndael-256', '', 'ofb', '');

	if ($encrypt) {
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_DEV_URANDOM);
	} else {
		if (strpos($v,".") === false) return $v;
		list($v,$iv) = explode(".",$v);
		$v = pack("H*", $v);
		$iv = pack("H*", $iv);
	}
		
  $ks = mcrypt_enc_get_key_size($td);
	
	$key = substr(file_get_contents(SL_KEY_FILE), 0, $ks);
	
	mcrypt_generic_init($td, $key, $iv);
	
	$v = $encrypt ? mcrypt_generic($td, $v) : mdecrypt_generic($td, $v);

	mcrypt_generic_deinit($td);
	
	mcrypt_module_close($td);	 
	 
	return $encrypt ? bin2hex($v).".".bin2hex($iv) : $v;
}
