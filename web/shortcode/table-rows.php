<?php

//params: source, fields, sort by

require_once(dirname(__FILE__)."/inc/sort-norm.php");

$source = $params[0];
$fields = explode(";",$params[1]);
$sortBy = isset($params[2]) ? explode(";",$params[2]) : false;

$echo = array();

if (isset($this->repData[$source])) {	
	foreach ($this->repData[$source] as $num=>$row) {
		$extra = array("anchor"=>$source.".".$num,"orig"=>array());
		
		$extra["orig"] = $row;
		
		$out = array();
		foreach ($fields as $n) {
			$v = isset($row[$n]) ? $row[$n] : "&nbsp;";
			if (isset($this->repData["format"][$n])) {
				eval('$v='.str_replace('$'.$n,'$v',$this->repData["format"][$n]).';');
			}
			$out[] = $v;
		}
		
		if (isset($row["type"])) $extra["type"] = $row["type"];
		
		$l = implode("\t",$out)."\t#".json_encode($extra)."\n";
		if ($sortBy !== false) {
			$echo[] = array(sortNorm($row[$sortBy[0]],isset($sortBy[1])?$sortBy[1]:''),$l);
		} else {
			$echo[] = $l;
		}
	}
}

echoSorted($echo);

