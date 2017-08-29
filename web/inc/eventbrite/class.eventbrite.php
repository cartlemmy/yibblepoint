<?php

require_once(SL_INCLUDE_PATH."/class.slRemote.php");



class eventbrite extends slWebModule {
	private $oauth;
	private $rem = false;
	
	public function init($oauth) {
		$this->oauth = $oauth;
	}
	
	public function getAlias() {
		return "eventbrite";
	}
	
	private function initRemote() {
		if (!$this->rem) $this->rem = new slRemote;
	}
	
	public function get() {
		$params = func_get_args();
		$this->initRemote();
		return $this->rem->request(array(
			CURLOPT_URL=>"https://www.eventbriteapi.com/v3/".self::prepParams($params)."/?token=".$this->oauth,
			"encode"=>"json",
			"cacheFor"=>3600
		));
	}
	
	public static function prepParams($params) {
		foreach ($params as &$param) {
			//TODO ???
		}
		return implode('/',$params);
	}	
	
}
