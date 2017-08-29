<?php
//params: name, row unique, section by

$inner = explode("\n",trim($inner));
$table = array(
	"labels"=>array(),
	"rows"=>array()	
);

$rowUnique = isset($params[1]) ? $params[1] : false;
$sectionBy = isset($params[2]) ? $params[2] : false;

?><table class="table table-striped"><?php
$rowNum = 0; $curSection = false;
foreach ($inner as $rowRaw) {
	
	if (strpos($rowRaw,"\t#")) {
		$rowRaw = explode("\t#",$rowRaw);
		$extra = json_decode(array_pop($rowRaw),true);
		$rowRaw = $rowRaw[0];
	} else $extra = array();
	
	$rowRaw = explode("\t",trim(preg_replace('/\t+/',"\t",$rowRaw)));
	
	if (!count($table["labels"])) {
		$table["labels"] = $rowRaw;
		ob_start();
		echo '<tr>';
		foreach ($rowRaw as $label) {
			echo '<th>'.$label.'</th>';
		}
		echo '</tr></thead><tbody>';
		$header = ob_get_clean();
	} else {
		if ($sectionBy) {
			$rep = array();
			foreach ($rowRaw as $n=>$v) {
				$rep['$'.toCamelCase($table["labels"][$n])] = var_export($v,true);
			}
			eval('$section = '.str_replace(array_keys($rep), array_values($rep), $sectionBy).';');
		} else $section = "ALL";

		if ($section !== $curSection) {
			echo '<thead>';
			if ($section !== "ALL") echo '<tr class="table-sect"><th colspan="'.count($table["labels"]).'">'.$section.'</th></tr>';
			echo $header;
			$curSection = $section;
		}
		
	
		$row = array();
		$class= array();
		
		if ($rowNum&1) $class[] = "odd";
		if (isset($extra["type"])) $class[] = $extra["type"];
		
		echo '<tr class="'.implode(" ",$class).'" '.(isset($extra["anchor"]) ? ' style="cursor:pointer" onclick="window.location.href=\'#'.$extra["anchor"].'\'"':"").'>';
		foreach ($rowRaw as $n=>$v) {
			$row[$table["labels"][$n]] = $v;
			echo '<td>'.$v.'</td>';
		}
		echo '</tr>';
		
		if ($rowUnique) {
			$table["rows"][$row[$rowUnique]] = $row;
		} else {
			$table["rows"][] = $row;
		}
		$rowNum++;
	}
}
?></tbody></table><?php

if (!isset($this->repData[$params[0]])) $this->repData[$params[0]] = $table;
