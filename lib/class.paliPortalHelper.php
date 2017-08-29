<?php

class paliPortalHelper {
	private static $tMap = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	private static $tBlop =  array(32, 18, 41, 19, 34, 51);

	public static function createAuthToken($privateKey, $tToken = false, $publicToken = false) {
		if ($tToken === false) $tToken = self::intToDecodableToken((int)(time() / 300));
		if ($publicToken === false) $publicToken = self::stringToToken();
		
		return $tToken.".".$publicToken.".".self::stringToToken($tToken."-".$publicToken."-".$privateKey);
	}

	private static function stringToToken($s = false, $len = 16) {
		if ($s === false) $s = microtime(true)."-".(function_exists('openssl_random_pseudo_bytes') ? openssl_random_pseudo_bytes(40) : rand()).'-1ca312b868eae3b56b646f99b929b021975418b2';
		return substr(preg_replace('/[^A-Za-z\d]/','',base64_encode(hash('sha256', urlencode($s), true))),0,$len);
	}

	private static function intToDecodableToken($num) {
		$rv = "";
		for ($i = 0; $i < 6; $i++) {
			$rv .= substr(self::$tMap, ($num ^ self::$tBlop[$i]) % 62, 1);
			$num = floor($num / 62);
		}
		return $rv;		
	}
}
