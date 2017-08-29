<?php

$filter = isset($params[0]) ? $params[0] : true;

$p = $this->get('data.params');

$out = array();
foreach ($p as $pv) {
	$out[] = $pv["apiName"];
}
echo implode(", ",$out);
