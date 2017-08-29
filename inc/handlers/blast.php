<?php

require_once(SL_LIB_PATH."/cron/blast/extras.php");
require_once(SL_LIB_PATH."/cron/blast/class.emailBlast.php");

if ($_SERVER["QUERY_STRING"] == "NEW") {
	echo "Please save before previewing.";
	exit();
}

$p = explode("/",array_pop(explode("blast/",$GLOBALS["slRequestInfo"]["path"])));
if (count($p) == 3) {
	list($type,$id,$p3) = $p;
} else {
	$type = "email";	
	$id = $_SERVER["QUERY_STRING"];
}

if (substr($id,0,6) == "blast-") {
	$p = explode("-",$id,4);
	$blast = new emailBlast($id);
} elseif ((int)$id) {
	$blast = new emailBlast((int)$id);
}

switch ($type) {
	case "f": //file	
		$blast->queueAction("view",array("method"=>"image"));
		$this->showFile(LGPHP_ROOT_DIR."/data/users/".$blast->user["user"]."/file/blast/".$blast->blast["id"]."/".$p3);
		exit();
		
	case "email":
		$blast->queueAction("view",array("method"=>"web-version"));
		$ID = $blast->getId();

		$blast->generate();

		$blastContentFile = SL_DATA_PATH."/blast/".$blast->blast["id"];

		list($header,$body) = explode("\n",file_get_contents($blastContentFile),2);
		$header = json_decode($header, true);

		$contact = $blast->contact ? $blast->contact : array();
		
		//echo "<pre>".print_r($blast,true)."</pre>"; exit();

		$body = tagParse($body,include(SL_LIB_PATH."/cron/blast/tags.php"));
		
		if ($header) {
			if ($header["IsHTML"]) {
				echo convertBlastLinks($body,$ID,true);
			} else {
				?>
	<!DOCTYPE HTML>
	<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title></title>
			<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
		<link type="text/css" rel="stylesheet" href="themes/default/style.css" />
			</script>
	</head>
	<body><div style="position:absolute;left:50%"><div style="position:relative;left:-300px"><pre style="width:600px;white-space:pre-wrap;word-wrap:break-word;"><?=$body;?></pre></div></div></body></html><?php
			}
		} else {
			echo "This blast has not been configured.";
		}
		break;
}
