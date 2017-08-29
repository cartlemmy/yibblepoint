<?php

require_once(SL_INCLUDE_PATH."/class.slScript.php");

class slAPILoader extends slClass {
	private $response = "";
	
	function __construct($requestInfo,$noScript = false) {
		
		$this->requestInfo = $requestInfo;
		
		$this->scriptName = isset($requestInfo["sn"]) ? $requestInfo["sn"] : array_pop(explode("/",$requestInfo["path"]));
		
		$this->script = new slScript($this->scriptName,true);
		$this->script->useAbsolutePath = true;
		
		$apiLink = "please visit ".WWW_BASE."api/ for more info.";
		
		if (!isset($this->requestInfo["params"]["key"])) {
			$this->script->alert("No API key specified, ".$apiLink);
			return;
		}
		
		if ($res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],array("user"=>$this->requestInfo["params"]["key"]))) {
			$GLOBALS["slConfig"]["APIUser"] = $res->fetch_assoc();
			if (isset($GLOBALS["slSession"])) {
				$GLOBALS["slSession"]->set("parentID",$GLOBALS["slConfig"]["APIUser"]["id"]);
				$GLOBALS["slSession"]->set("parentUser",$GLOBALS["slConfig"]["APIUser"]["user"]);
				$GLOBALS["slSession"]->set("isAPI",1);
			}
		} else {
			$this->script->alert("Invalid API key, ".$apiLink);
			return;
		}
		
		if ($noScript) return;
		
		$this->script->start();
		require(SL_INCLUDE_PATH."/slGlobal.js");
		$this->script->stop();

		$this->script->parse(SL_INCLUDE_PATH."/js/net/cookies.js",true);
		
		$includes = array(
			"core/sl.js","core/initSlClass.js","core/general.js","core/string.js",
			"core/serializer.js","core/value.js","core/date.js",
			"core/bitArray.js","core/base64.js","app/app.js","net/net.js",
			"net/browser.js"
		);

		foreach ($includes as $include) {
			$this->script->parse(SL_INCLUDE_PATH."/js/".$include);
		}
		
		$this->script->start();
		$fromAPI = 1;
		require(SL_INCLUDE_PATH."/config.js.php");
		require(SL_INCLUDE_PATH."/slLoader.js");
		require(SL_INCLUDE_PATH."/APIInit.js.php");
		
		$this->script->stop();
	}
		
	function respond() {
		$this->script->out();
		
		?>sl.config.noCSCookies = <?=isset($GLOBALS["slNoCSCookies"]) ? 1 : 0;?>;
		sl.config.sessionId = <?=json_encode(session_id());?>;
		sl.config.APIScriptName = <?=json_encode($this->scriptName);?>;
		sl.config.APIKey = <?=json_encode($this->requestInfo["params"]["key"]);?>;
		sl.scriptLoader(true);
		<?php
				
	}
}
