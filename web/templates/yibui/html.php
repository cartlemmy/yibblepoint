<?php 

if ($this->get("document")) {
	require(realpath(dirname(__FILE__))."/document.php");
	return;
}

?><!DOCTYPE html>
<html lang="<?=$this->get("language");?>">
	<meta charset="<?=$this->getCharset();?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes" />
	
	<title><?=$this->getTitle();?></title>
	
	<?=$this->getMetaTags("\t");?>	
	
	<link rel="canonical" href="<?=$this->getCanonicalLink();?>" />
	
	<meta name="apple-mobile-web-app-capable" content="yes">

	<link href="<?=WWW_RELATIVE_BASE;?>favicon.ico" type="image/x-icon" rel="icon" />
	<link href="<?=WWW_RELATIVE_BASE;?>favicon.ico" type="image/x-icon" rel="shortcut icon" />
	<link rel="apple-touch-icon" href="<?=WWW_RELATIVE_BASE;?>icon-57x57.png" />
	<link rel="apple-touch-icon" sizes="72x72" href="<?=WWW_RELATIVE_BASE;?>icon-72x72.png" />
	<link rel="apple-touch-icon" sizes="114x114" href="<?=WWW_RELATIVE_BASE;?>icon-114x114.png" />
	<link rel="apple-touch-icon" sizes="144x144" href="<?=WWW_RELATIVE_BASE;?>icon-144x144.png" />
	
	<?php 
	$this->addCSS("css/yibui.css"); 
	$this->addCSS("css/yibui/main.css"); 
	//$this->addScript("js/jquery-1.11.2.min.js");
	?>
	<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="   crossorigin="anonymous"></script>
	
	<script type="text/javascript">
		var sl = {outOfBox:1,config:{"isWeb":1}};
		
		<?php
			$yus = $this->get('yibui-*');
		?>
		
		window.yibUISettings = <?=$yus ? json_encode($yus) : '{}';?>;
		window.yibUIPreReadyCB = [];
		window.yibUIReady = function(cb){ 			
			window.yibUIPreReadyCB.push(cb);
		};
	
		(function(){
			var shown = false;
			window.STATUS = function(txt) {
				if (txt) {
					$('#status').html(txt);
					if (!shown) {
						$('#status').show();
						shown = true;
					}
				} else {
					$('#status').html('');
					if (shown) {
						$('#status').fadeOut();
						shown = false;
					}
				}
			};
		})();
	</script>
</head>
<?php
	$classes = array(
		'yibui',
		'page-'.CURRENT_PAGE_NAME.(
			($pcp = $this->get('page-class-param')) && isset($_GET[$pcp]) ?
				'-'.safeName($_GET[$pcp]) : ''	
		)
	);
	
	if ($this->get('body-class')) {
			$classes[] = $this->get('body-class');
	}
	
	$check = array('frame', 'full-page');
	foreach ($check as $n) {
		if ($this->get($n)) $classes[] = $n;
	}
?>
<body class="<?=implode(" ", $classes);?>">
	<div class="loading-overlay<?=$this->get('loading-at-start') ? ' loading-at-start' : '';?>"><div><div>Loading...</div><ul></ul></div></div>
	<?php $this->bodyStart();?>
	<?php if ($this->get('full-page') || $this->get('frame')) { echo $this->getContent(); } else { ?>
	<div class="vertical pad" style="height:40px; position:relative;">
		<div class="nav">
			<?php
			 $nav = $this->get("nav",array(
				"Pali Portal"=>array("url"=>"home"),/*
				"Devices"=>array("url"=>"devices"),
				"Activity Views"=>array("url"=>"devgo")*/
			));
			
			$isDeviceAdmin = $GLOBALS["slSession"]->user->hasPermission("super OR admin OR deviceman");
			
			if ($isDeviceAdmin && !isset($nav["Devices"])) {
				$nav["Devices"] = array("url"=>"devices");
			}
			
			foreach ($nav as $label=>$info) {
				$curNav = false;
				if ($info["url"] == CURRENT_PAGE_NAME) {
					$curNav = true;
					if (isset($info["params"])) {
						foreach ($info["params"] as $n=>$v) {
							if (!isset($_GET[$n]) || $_GET[$n] != $v) {
								$curNav = false; break;
							}
						}
					}
				}
				if ($curNav) {
					echo '<div>'.htmlspecialchars($label).'</div>';
				} else {
					if (isset($_GET["dggo"]) && $_GET["dggo"] != "1") $info["params"]["dggo"] = $_GET["dggo"];
					echo '<a href="/'.$info["url"].'/'.(
						isset($info["params"]) ? '?'.http_build_query($info["params"]) : ""
					).'"><div>'.htmlspecialchars($label).'</div></a>';
				}
			}

			
			
			if ($GLOBALS["slSession"]->isLoggedIn()) { ?>
				<div id="login-status">
					<a href="<?=WWW_RELATIVE_BASE.'logout/';?>">
						<small><b><?=$GLOBALS["slSession"]->user->get("name");?></b></small>
						LOGOUT
					</a>
				</div>
			<?php } /* END if isLoggedIn() */ ?>
		</div>
		<div id="status"></div>
	</div>
	<?php if ($this->get('content-zoom')) { ?>
		<div class="vertical fill zoom" id="content-container">
		<?=$this->getContent();?>
		</div>
	<?php } else { ?>
		<div class="vertical fill<?=$this->get('content-scroll')?' scroll':'';?>" id="content-container">
		<?=$this->getContent();?>
		</div>
	<?php }}
	if (!$this->get('frame')) { ?>
	<div id="popup-cont">
		<button class="icon close"></button>
		<div class="frame-cont"><iframe id="popup-frame" name="popup-frame" src="about:blank" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe></div>
	</div>
	<?php
	}
		$this->bodyEnd();
		$this->addScript("js/yibui.js");
		$this->addScript("js/yibui/main.js");
	?>
</body>
</html>
