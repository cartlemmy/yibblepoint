<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class devInfo extends slAppClass {		
	function getInfo() {
		ob_start();
		
		echo "SL_BASE_PATH: ".SL_BASE_PATH."\n";
		
		return ob_get_clean();
	}
}
