<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class configEncryptApp extends slAppClass {		
	function encrypt($v) {
		return 'array("!ENCRYPTED","'.doEncOrDec($v,true).'")';
	}
}
