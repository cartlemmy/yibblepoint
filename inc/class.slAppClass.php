<?php

class slAppClass extends slClass {		
	protected $app;
	
	function __construct($app) {
		$this->app = $app;
	}
	
	function http($url,$params = array()) {
		require_once(SL_INCLUDE_PATH."/class.slRemote.php");
		$params[CURLOPT_URL] = $url;
		$remote = new slRemote();
		return $remote->request($params);
	}
	
	function varSet($n,$id,$tab) {
		$GLOBALS["slSession"]->fileDataSet($n,$id,$tab);
	}
	
	function varGet($n,$id) {
		return $GLOBALS["slSession"]->fileDataGet($n,$id);
	}
	
	function getInfo($args) {
		$ref = $this->app->safeRef."?".$args;
		$link = WWW_ROOT."/sl/".$ref;
		
		require_once(SL_INCLUDE_PATH."/class.slURLShortener.php");
		$short = new slURLShortener();
		
		$short->create($link);
		
		return translate(tagParse(file_get_contents(SL_INCLUDE_PATH."/app-info.html"),array(
			"www-root"=>WWW_ROOT,
			"ref"=>$ref,
			"link"=>$link,
			"short-link"=>$short->getShortenedURL(),
			"id"=>$short->id,
			"tiny-id"=>array_pop(explode("?",$short->getShortenedURL()))
		)));
	}
}
