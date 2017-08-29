<?php

class googleAnalytics extends slWebModule {
	private $ua;
	private $sendQueue = array();
	
	public function init($ua) {
		$this->ua = $ua;
		$this->send('pageview');
	}
	
	public function getAlias() {
		return "ga";
	}
	
	public function send() {
		$this->sendQueue[] = func_get_args();
	}
	
	public function showTracking() {
		?><script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', '<?=$this->ua;?>', 'auto'); <?php 
		  foreach ($this->sendQueue as $item) {
			  array_unshift($item,"send");
			  echo 'ga('.substr(json_encode($item),1,-1).');'."\n";
		  }
		  ?></script><?php
	}
}
