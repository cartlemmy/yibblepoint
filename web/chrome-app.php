<?php

$this->setCaching(false);
define('APP_DIR',SL_WEB_PATH."/chrome-app");

echo '<pre>';

echo "Updating Chrome App...\n\n";

echo "Updating icons\n";
$s = array("16","128");
foreach ($s as $w) {
	$f = APP_DIR."/yp-".$w.".png";
	echo "\t".$f."\n";
	file_put_contents($f,file_get_contents(WWW_BASE."/icon-".$w."x".$w.".png"));
}
echo "\n";

echo "Updating js includes\n";
system ('rsync -arv '.SL_INCLUDE_PATH.'/js/* '.SL_WEB_PATH.'/chrome-app/yp');
echo "\n";


echo "Creating config file\n";
file_put_contents(APP_DIR."/config.json",json_encode(array(
	"yp-home"=>WWW_BASE,
	"js"=>getDirTree(SL_WEB_PATH.'/chrome-app/yp')
),JSON_PRETTY_PRINT));
echo "\n";

echo '</pre>';

function getDirTree($dir) {
	$rv = array();
	if ($dp = opendir($dir)) {
		while (($file = readdir($dp)) !== false) {
			if ($file == "." || $file == "..") {
			} elseif (is_file($dir."/".$file)) {
				$rv[] = str_replace(SL_WEB_PATH.'/chrome-app/','',$dir."/".$file);
			} else {
				$rv = array_merge($rv,getDirTree($dir."/".$file));
			}
		}
		closedir($dp);
	}
	return $rv;
}
