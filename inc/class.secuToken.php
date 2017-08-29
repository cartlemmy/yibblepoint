<?php

class secuToken {
	private $privateKey = "AzWw1IFQ0TKAyaGEihTzfBUxjFocMEQxsy8q9pFcXrG7ngwAYzoeHlYBRafouoW";
	private $calculatedKey = "";
	private $keyLength = 10;
	private $token = "";
	private $lastError = "";
	private $dbg = array();
	
	function __construct($opt = array()) {
		$this->dataDir = realpath(dirname(__FILE__)."/../data/")."/secuTokenData";
		makePath($this->dataDir);
		
		$this->cleanOld();
		
		$file = realpath(dirname(__FILE__)."/.secuTokenKey");
		$v = $this->interleave($this->privateKey."-".$_SERVER["SERVER_ADMIN"]."-".(is_file($file) ? file_get_contents($file) : ""));
		$this->calculatedKey = sha1($v[0]).sha1($v[1]);
		
		$base = substr($_SERVER["SCRIPT_FILENAME"],0,-strlen($_SERVER["PHP_SELF"]));
		$defaultPage = array_pop(explode("/",$_SERVER["PHP_SELF"]));
		
		$this->opt = array_merge(array(
			"expires"=>"+1 hour",
			"base"=>$base,
			"page"=>$_SERVER["PHP_SELF"]
		),$opt);
		
	}
	
	function cleanOld() {
		if ($dp = opendir($this->dataDir)) {
			while ($file = readdir($dp)) {
				if ($file != "." && $file != "..") {
					$path = $this->dataDir."/".$file;
					if ($fp = fopen($path,"r")) {
						$expires = (int)fgets($fp);
						fclose($fp);
						if (time() > $expires) {
							unlink($path);
						}						
					}
				}
			}
			closedir($dp);
		}
	}
		
	function create($data) {
		$publicKey = $this->generateKey();
		$this->dbg["publicKey"] = $publicKey;
		
		$this->token = $this->tokenize($publicKey,array(strtotime($this->opt["expires"])));
		
		$expires = isset($this->opt["expires"]) ? strtotime($this->opt["expires"]) : time() + 86400 * 30;
		
		$file = $this->getFileName($publicKey);
		
		$this->dbg["file"] = $file;
		
		if (file_put_contents($file,$expires."\n".mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->token), json_encode($data), MCRYPT_MODE_CBC, md5(md5($this->token))))) {
			return true;
		}
		
		return false;
	}
	
	function getFileName($publicKey) {
		$this->dbg["calculatedKey"] = $this->calculatedKey;
		return $this->dataDir."/".sha1($publicKey."-".$this->calculatedKey);
	}
	
	function getDebug() {
		return $this->dbg;
	}
	
	function tokenize($publicKey,$limitations = array()) {
		if ($limitations[0]) $limitations[0] = $this->encodeTS($limitations[0]);
		$limitations = substr(json_encode($limitations),1,-1);
		return strtr(base64_encode(
			substr(sha1($limitations."-".$publicKey."-".$this->calculatedKey,true),0,$this->keyLength).
			pack("H*", $publicKey).
			$limitations
		),"+/=","-._");
	}
	
	function detokenize($token) {
		$bin = base64_decode(strtr($token,"-._","+/="));
		$rv = array(
			"hash"=>bin2hex(substr($bin,0,$this->keyLength)),
			"publicKey"=>bin2hex(substr($bin,$this->keyLength,$this->keyLength)),
			"limitations"=>json_decode("[".substr($bin,$this->keyLength*2)."]",true)
		);

		if ($rv["hash"] != substr(sha1(substr($bin,$this->keyLength*2)."-".bin2hex(substr($bin,$this->keyLength,$this->keyLength))."-".$this->calculatedKey),0,$this->keyLength * 2)) return false;
		
		if ($rv["limitations"][0]) $rv["limitations"][0] = $this->decodeTS($rv["limitations"][0]);
		return $rv;
	}
	
	function get($token = false) {
		if ($token === false) $token = $_SERVER["QUERY_STRING"];
		
		$this->token = $token;
		$data = $this->detokenize($token);

		if (!$data) return $this->error("Bad token.");
		
		if ($data["limitations"][0] && time() > $data["limitations"][0]) return $this->error("Expired token.");
				
		
		$rv = json_decode(rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->token), array_pop(explode("\n",file_get_contents($this->getFileName($data["publicKey"])),2)), MCRYPT_MODE_CBC, md5(md5($this->token))), "\0"),true);
		if (!$rv) return $this->error("Bad token.");
		return $rv;
	}
	
	function getToken() {
		return $this->token;
	}
	
	function getUrl() {
		return WWW_BASE.$this->opt["page"]."?".$this->getToken();
	}

	
	function encodeTS($i) {
		$hex = dechex(ceil($i / 60));
		if (strlen($hex)&1) $hex = "0".$hex;
		return base64_encode(pack("H*", $hex));
	}
	
	function decodeTS($v) {
		return hexdec(bin2hex(base64_decode($v))) * 60;
	}
	
	function interleave($string) {
		$rv = array("","");
		for ($i = 0; $i < strlen($string); $i++) {
			$rv[$i&1] .= $string{$i};
		}
		return $rv;
	}
	
	function generateKey($len = false) {
		return substr(sha1(microtime(true).".".rand(0,0xFFFFFFFF)),0,$len ? $len : $this->keyLength * 2);
	}
	
	function error($txt) {
		$this->lastError = $txt;
		return false;		
	}
	
	function getLastError() {
		return $this->lastError;
	}
}
