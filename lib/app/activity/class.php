<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class sessionInfo extends slAppClass {		
	function activityCnt() {
		return $GLOBALS["slSession"]->user->activity->count();
	}
	
	function activity($i) {
		return $GLOBALS["slSession"]->user->activity->get($i);
	}
	
	function sections() {
		$thisYmd = explode("-",date("Y-n-j"));
		$currentSection = "";
		$rv = array();
		for ($i = 0, $len = $GLOBALS["slSession"]->user->activity->count(); $i < $len; $i++) {
			$activity = $GLOBALS["slSession"]->user->activity->get($i);
			$ymd = explode("-",date("Y-n-j",$activity["ts"]));
			for ($j = 0; $j < 3; $j++) {
				if ($ymd[$j] != $thisYmd[$j]) break;
			}
			switch ($j) {
				case 0:
					$section = date($GLOBALS["slConfig"]["international"]["year"],$activity["ts"]);
					break;
					
				case 1:
					$section = date($GLOBALS["slConfig"]["international"]["month"],$activity["ts"]);
					break;
					
				case 2:
					$section = date($GLOBALS["slConfig"]["international"]["date"],$activity["ts"]);
					break;
			}
			if ($section != $currentSection) {
				$rv[] = array($i,$section);
				$currentSection = $section;
			}
		}
		return $rv;
	}
}
