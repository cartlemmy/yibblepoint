<?php

class activ extends slWebModule {
	private $params = array();
	private $rendered = false;
	private $viewer = false;
	
	public function init() {
		
	}
	
	public function getAlias() {
		return "activ";
	}
	
	public function set($n,$v) {
		if ($this->rendered) {
			echo '<script>_active.set('.json_encode($n).','.json_encode($v).')</script>';
		} else {
			$this->params[$n] = $v;
		}
	}
	
	public function enableViewer() {
		require_once(SL_BASE_PATH."/activ/config.php");
		require_once(SL_BASE_PATH."/activ/token.php");
		$this->set('token',generateActivToken());
		$this->viewer = true;
	}
	
	public function render() {
		if (isset($GLOBALS["slConfig"]["web"]["activ"]) && !$GLOBALS["slConfig"]["web"]["activ"]) return;
		if ($this->rendered || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) return;
		$this->rendered = true;
		
		$this->web->addScript('inc/activ/activ'.($this->viewer?'-viewer':'').'.js','text/javascript','body-end');
		
		if (isset($_SERVER["REQUEST_TIME_FLOAT"])) {
			$rt = (float)$_SERVER["REQUEST_TIME_FLOAT"];
		} elseif (isset($_SERVER["REQUEST_TIME"])) {
			$rt = (int)$_SERVER["REQUEST_TIME"];
		} else $rt = time();
		
		$params = array(
			"u"=>WWW_BASE."activ/",
			"requested"=>$rt
		);

		?><script>
			$(document).ready(function(){
				console.log('Activ<?=($this->viewer?'Viewer':'');?>');
				if (window._activ === undefined) window._activ = new Activ<?=($this->viewer?'Viewer':'');?>(<?=json_encode(array_merge($this->params,$params));?>);
			});
		</script>
		<?php
	} 
	
}
