<?php

require_once(SL_LIB_PATH."/cron/blast/extras.php");

if (substr($_SERVER["QUERY_STRING"],0,6) == "blast-") {
	$p = explode("-",$_SERVER["QUERY_STRING"],4);
	$link = decompressLink(array_pop($p));
	
	require_once(SL_LIB_PATH."/cron/blast/class.emailBlast.php");
	
	$blast = new emailBlast(implode("-",$p));
	$blast->queueAction("click",array("link"=>$link));
	
} else $link = decompressLink($_SERVER["QUERY_STRING"]);

header("Location: ".$link);

