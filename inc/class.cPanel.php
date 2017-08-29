<?php

class cPanel {
	private $xmlapi;
	private $cpanel;
	
	function __construct($debug = false) {

		requireThirdParty("xmlapi-php-master");

		$this->cpanel = $GLOBALS["slConfig"]["cpanel"];

		$this->xmlapi = new xmlapi($this->cpanel["server"]);
		$this->xmlapi->set_port($this->cpanel["port"]);
		$this->xmlapi->password_auth($this->cpanel["user"],$this->cpanel["password"]);
		$this->xmlapi->set_output('json');

		if ($debug) $this->xmlapi->set_debug(1);
	}
	
	function query($p1,$p2,$p3) {		
		return json_decode($this->xmlapi->api1_query($this->cpanel["user"], $p1, $p2, $p3),true);
	}
}
