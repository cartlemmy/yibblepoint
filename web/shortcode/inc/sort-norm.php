<?php

function sortNorm($v,$type = "number") {
	switch ($type) {
		case "date":
			return strtotime($v);
			
		default:
			return $v * 100000;
	}
}

function echoSorter($a,$b) {
	return $a[0] - $b[0];
}

function echoSorterDesc($a,$b) {
	return $b[0] - $a[0];
}

function echoSorted($a,$desc = false) {
	if ($a && is_array($a[0])) {
		if ($desc) {
			uasort($a,"echoSorterDesc");
		} else {
			uasort($a,"echoSorter");
		}
		
		foreach ($a as $o) {
			echo $o[1];
		}
	} else echo implode($a);
}
