<?php

class slRestAPI {
	public function __construct() {
	}
	
	public static function generatePublicToken($userId = 0, $ts = false, $rand = false) { //$userId 0 = Any
		if ($ts === false) $ts = time();
		if ($rand === false) $rand = rand(0,0xFFFFFFFF);
		$publicKey = pack("NNN",$userId ^ 0x7133df81,$ts ^ 0x1ab302cb,$rand);
		return strtr(base64_encode($publicKey.substr(sha1($publicKey."-".$GLOBALS["slConfig"]["package"]["key"],true),0,12)),'+/=','-_.');
	}
	
	public static function decodeToken($token) {
		$rv = base64_decode(strtr($token,'-_.','+/='));
		$rv = unpack('NuserId/Nts/Nrand',substr($rv,0,12));
		$rv["userId"] = $rv["userId"] ^ 0x7133df81;
		$rv["ts"] = $rv["ts"] ^ 0x1ab302cb;
		$rv["token"] = $token;
		return $rv;
	}
	
	public static function tokenValid($token) {
		$token = self::decodeToken($token);
		if ($token["userId"] != 0) {
			//TODO: check that user is logged in
			return "Invalid token";
		}
		if ($token["ts"] < time() - 86400*365) return "Token expired";
		if ($token["token"] != self::generatePublicToken($token["userId"], $token["ts"], $token["rand"])) return "Invalid token";
		return true;
	}
	
	public function go() {
		if (isset($_GET["t"])) {
			if (($err = self::tokenValid($_GET["t"])) === true) {
				if (!setAndTrue($_GET,"action")) $_GET["action"] = "def";
				$method = toCamelCase("do-".$_GET["action"]);
				
				if (method_exists($this,$method)) {
					$params = array();
					if (setAndTrue($_GET,"params")) {
						if (is_array($_GET["params"])) {
							$params = json_decode($_GET["params"],true);
						} else {
							$params = array((string)$_GET["params"]);
						}
					} else {
						$params[0] = array();
						foreach ($_GET as $n=>$v) {
							switch ($n) {
								case "action": case "t":
									break;
									
								default:
									$params[0][$n] = $v;
									break;
							}
						}
					}

					call_user_func_array(array($this,$method),$params);
					$this->fail("Unknown response");
				} else $this->fail(get_class($this)."::".$method." not found");
			} else $this->fail($err);
		} else $this->fail("Token not specified");
	}
	
	protected function fail($message,$extra = array()) {
		$this->res(array_merge(array("success"=>false,"message"=>$message),$extra));
	}
	
	protected function suc($res, $ret = false) {
		if ($ret) return $res;
		$this->res(array("success"=>true,"res"=>$res));
	}
	
	private function res($res) {
		echo json_encode($res, JSON_PRETTY_PRINT); exit();
	}
}
