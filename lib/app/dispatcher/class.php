<?php

class slDispatcher extends slAppClass {
	private $setup = false;
	
	function __construct($app) {
		$this->app = $app;
		
		if (isset($this->app->args[0])) {
		}
	}
	
	
}
