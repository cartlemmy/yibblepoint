<?php

$file = realpath(dirname(__FILE__))."/".$GLOBALS["slConfig"]["international"]["language"];

if (is_file($file)) {
	$data = explode("\n",file_get_contents($file));
	echo json_encode(array(explode(",",$data[0]),explode(",",$data[1])));
} else echo "null";
