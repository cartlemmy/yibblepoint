<?php

if (isset($request["params"]["logout"])) {
	$GLOBALS["slSession"]->logOut();
	header("Location:.");
	exit();
}

$otherWindow = false;

if ($GLOBALS["slSession"]->isLoggedIn()) {
	$status = $GLOBALS["slSession"]->getUserStatus("!self");
	if (isset($status["active"]) && $status["active"]) {
		$otherWindow = $status;
	} else {
		$GLOBALS["slSession"]->user->setWindowOpen();
	}
}

header("Content-Type: text/html; charset=utf-8");

?><!DOCTYPE HTML>
<html>
<head>
	<link rel="shortcut icon" href="favicon.ico" />
	<link rel="apple-touch-icon" href="icon-57x57.png" />
	<link rel="apple-touch-icon" sizes="72x72" href="icon-72x72.png" />
	<link rel="apple-touch-icon" sizes="114x114" href="icon-114x114.png" />
	<link rel="apple-touch-icon" sizes="144x144" href="icon-144x144.png" />
	<link rel="apple-touch-startup-image" href="splash.png" />
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="robots" content="noindex, nofollow">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title></title>
	<?php if ($GLOBALS["slConfig"]["core"]["theme"] != "default") {
		?><link type="text/css" rel="stylesheet" href="themes/default/style.css" /><?php
		echo "\n";
	}
	?>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
	<link type="text/css" rel="stylesheet" href="<?=SL_THEME_PATH_WWW;?>/style.css" />
	<link type="text/css" rel="stylesheet" href="../inc/thirdparty/font-awesome/css/font-awesome.min.css" />
	<?php if ($GLOBALS["slConfig"]["isMobile"]) { ?><link type="text/css" rel="stylesheet" href="../inc/mobile.css" /><?php } ?>
	<?php if (!$otherWindow) { ?>
	<!--[if lt IE 9]>
	<script src="js/core/ie.js"></script>
	<![endif]-->
	<script type="text/javascript" src="sl.js"></script>
	<script type="text/javascript">
		<?php require(SL_INCLUDE_PATH."/config.js.php"); ?>
	
	<?php if (setAndTrue($GLOBALS["slConfig"]["dev"],"delayLoad")) { ?>
		window.addEventListener("load",function(){
			setTimeout(sl.scriptLoader,<?=((int)$GLOBALS["slConfig"]["dev"]["delayLoad"] * 1000);?>);
		},false);
	<?php } else { ?>
		window.addEventListener("load",sl.scriptLoader,false);
	<?php } ?>
	
	window.openAtStart = <?php
	if (isset($request["app"]) && $request["app"]) {
		echo json_encode(array("ref"=>$request["app"],"args"=>explode("&",$request["rawParams"])));
	} else {
		echo "false";
	}
	?>;
	<?php } ?>
	</script>
</head>
<body>
<div id="slMain">
	<?php if ($otherWindow) { ?>
		<div class="center-message"><?php
		echo $GLOBALS["slConfig"]["package"]["name"]." is already open ";
		if ($otherWindow["ip"] != $_SERVER["REMOTE_ADDR"]) {
			echo "for '".$GLOBALS["slSession"]->getUserName()."' on another computer/device. <br /><br /><a href=\"?logout=1\">LOG OUT</a>";
		} elseif ($otherWindow["agent"] != $_SERVER["HTTP_USER_AGENT"]) {
			echo "for '".$GLOBALS["slSession"]->getUserName()."' in another browser. <br /><br /><a href=\"?logout=1\">LOG OUT</a>";
		} else {
			echo "in another tab/window.";
		}
		
		?></div>
<?php } else { ?>
<div class="center-message" id="loadMessage">
		<img src="<?=SL_THEME_PATH_WWW;?>/loader.gif" style="float:left"><div style="float:left;padding:10px 0 0 10px;">Loading <?=$GLOBALS["slConfig"]["package"]["name"];?>...</div><br />
		<div style="clear:both;"></div>
		<div id="loadMessageDetail" style="font-size:13px;padding-top:10px;"></div>
	</div>
<?php } ?></div>
<div class="common-icon"></div>
</body>
</html>
