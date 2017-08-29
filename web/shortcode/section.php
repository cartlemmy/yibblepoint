<?php
//params: name, row unique

$inner = explode("\n",trim($inner));
$table = array(
	"labels"=>array(),
	"rows"=>array()	
);

$rowUnique = isset($params[1]) ? $params[1] : false;

?><table><thead><?php
$rowNum = 0;
foreach ($inner as $rowRaw) {
	$rowRaw = explode("\t",trim(preg_replace('/\t+/',"\t",$rowRaw)));
	if (!count($table["labels"])) {
		$table["labels"] = $rowRaw;
		echo '<tr>';
		foreach ($rowRaw as $label) {
			echo '<th>'.$label.'</th>';
		}
		echo '</tr></thead><tbody>';
	} else {
		$row = array();
		echo '<tr'.($rowNum&1?' class="odd"':'').'>';
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

$this->repData["table"][$params[0]] = $table;
