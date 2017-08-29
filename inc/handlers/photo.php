<?php

//$GLOBALS["slConfig"] = require("inc/config.php");

//require("inc/initialize.php");
require_once(SL_INCLUDE_PATH."/class.fspot.php");
require_once(SL_INCLUDE_PATH."/class.slCache.php");

if (isset($_GET["ph"])) {
	$fs = new fspot("/home/cartlemmy/.config/f-spot/photos.db");
	$ph = explode(".",$_GET["ph"]);
	$id = (int)array_shift($ph);
	$ext = count($ph) ? array_shift($ph) : "jpeg";

	$photo = $fs->getPhoto((int)$id);
	
	$ext = strtolower($ext);
	if ($ext == "jpg") $ext = "jpeg";
	
	if (isset($_GET["height"])) {
		$cache = new slCache("fspot-".$id."-".$_GET["height"]);
		
		if ($cache->isNewerThan(filemtime($photo->data["absolutePath"]))) {
			header("Content-Type: image/jpeg");
			$cache->out();
		} else {
			if ($im = $photo->toGD()) {
				$height = (int)$_GET["height"];
				$width = round($height * (imagesx($im) / imagesy($im)));
				$newIm = imagecreatetruecolor($width,$height);
				
				imagecopyresampled($newIm,$im,0,0,0,0,$width,$height,imagesx($im),imagesy($im));
				
				header("Content-Type: image/jpeg");
				
				$cache->start();
				imagejpeg($newIm);
				$cache->complete();
				
				imagedestroy($im);
			}	
		}	
	} else {
		header("Content-Type: image/".$ext);
		readfile($photo->data["absolutePath"]);
	}
	exit();
}

ob_start();

$fs = new fspot("/home/cartlemmy/.config/f-spot/photos.db");

require_once(SL_INCLUDE_PATH."/class.slWeb.php");

$web = new slWeb();

$file = SL_INCLUDE_PATH."/handlers/photo/".(isset($_GET["action"]) ? $_GET["action"] : "view").".php";
if (!is_file($file)) $file = SL_INCLUDE_PATH."/handlers/photo/view.php";

include($file);	

$web->setDependencyFile(SL_INCLUDE_PATH."/class.fspot.php"); //Comment out when not developing
$web->setDependencyFile($file);

$web->setContent(ob_get_clean());

$web->prepareWebPage();
$web->render(true);



/*
$results = $GLOBALS["slCore"]->db->query("photos/","SELECT * FROM sqlite_master WHERE type='table'");

$tables = array();

while ($row = $results->fetchArray()) {
  $tables[] = $row;
}

foreach ($tables as $table) {
	$results = $GLOBALS["slCore"]->db->query("photos/","SELECT * FROM ".$table["name"]);
	$cnt = 0;
	if ($table["name"] == "photos" || $table["name"] == "photo_versions") continue;
	echo "<h2>".$table["name"]."</h2>";
	if ($results) {
		echo "<pre>".$table["sql"]."</pre>";
		echo "<table>";
		while ($row = $results->fetchArray()) {
			if ($cnt == 0) {
				echo "<tr>";
				foreach ($row as $n=>$col) {
					if (!is_numeric($n)) {
						echo "<th>".$n."</th>";
					}
				}
				echo "</tr>";
			}
			echo "<tr>";
				foreach ($row as $n=>$v) {
					if (!is_numeric($n)) {
						if ($n == "icon") {
							$src = gdkPixDataToGD($v,"jpeg");
							echo "<td>".($src ? "<img src=\"".$src."\">" : "")."</td>";
						} else {
							echo "<td>".htmlspecialchars($v)."</td>";
						}
					}
				}
				echo "</tr>";
			$cnt ++;
		}
		echo "</table>";
	}
}
*/
