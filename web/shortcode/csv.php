<?php

if (!trim($inner)) return;

echo "\n<table class=\"table\">\n";

$data = array();
$lines = explode("\n",trim($inner));
$labels = false;
foreach ($lines as $line) {
	$line = explode(",",$line);
	if (!$labels) {
		$labels = $line;
		$dataLabels = array();
		?><thead><tr><?php
		foreach ($labels as $n) {
			echo '<th>'.htmlspecialchars($n).'</th>';
			$dataLabels[] = toCamelCase($n);
		}
		?></tr></thead><tbody><?php
	} else {
		$data[] = array_combine($dataLabels,$line);
		?><tr><?php
		foreach ($line as $n) {
			echo '<td>'.htmlspecialchars($n).'</td>';
		}
		?></tr><?php
	}
}

echo "</tbody>\n</table>\n";

if (setAndTrue($params,0)) $this->set('data.'.$params[0],$data);
