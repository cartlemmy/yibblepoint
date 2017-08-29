<?php

$info = array();

$url = $params[0];

if (substr($url,-1) == "/") $url = substr($url,0,-1);
if (substr($url,0,-1) == "/") $url = substr($url,0,-1);

$title = array_pop(explode("/",$url));

$contentFile = SL_WEB_PATH."/content/".safeFile($title).".html";

if (substr($url,0,1) == "/") $url = substr($url,1);

if (isset($params[1])) {
	$info["title"] = $params[1];
	if (isset($params[2])) $info["image"] = $params[2];
	if (isset($params[3])) $info["details"] = $params[3];
} else {
	if (is_file($contentFile) && file_get_contents($contentFile,false,NULL,0,12) == "!yp-content:") {
		list($header,) = explode("\n",file_get_contents($contentFile),2);
		$info = json_decode(substr($header,12),true);
	}
}

if (!setAndTrue($info,"image")) $info["image"] = "images/button/".(count(explode("/",$url))<=2 ? "home/" : "").(setAndTrue($info,"title")?safeName(str_replace("<br>"," ",$info["title"])):$url).".jpg";

$image2x = explode(".",$info["image"]);
$image2x[count($image2x)-2] .= "@2x";
$image2x = implode(".",$image2x);

if (!setAndTrue($info,"title")) $info["title"] = ucwords(preg_replace('/[^\w\d]+/',' ',$title));

if (!is_file(SL_WEB_PATH."/".$info["image"]) && !is_file(SL_WEB_PATH."/".$image2x)) {
	$info["image"] = false;
} else {
	$info["image"] = WWW_RELATIVE_BASE.$info["image"];
}

if (strpos($info["title"],"<br>") !== false) {
	$name = explode("<br>",$info["title"]);
} else {
	$n = explode(" ",$info["title"]);
	$len = 0; $name = array(array());
	foreach ($n as $num=>$word) {
		$len += strlen($word) + 1;
		if ($num != 0 && $len >= ($info["image"] ? 8 : 15)) {
			$len = 0;
			$name[] = array();
		}
		$name[count($name)-1][] = $word;
	}
	foreach ($name as &$word) {
		$word = implode(" ",$word);
	}
}

$labelClasses = array(""," two-line"," three-line");
	
?><a href="<?=WWW_RELATIVE_BASE.$url;?>/"><div class="full-button"><div>
	<?php if ($info["image"]) { ?><div class="image"><img src="<?=$info["image"];?>"></div><?php } ?>
	<div class="label<?=$labelClasses[min(2,count($name)-1)];?>"><?=implode("<br>",$name);?></div>
	<div class="button-arrow"></div>
	<div style="clear:both"></div>
</div></div><?php if (setAndTrue($info,"details")) { ?><div class="button-details"><div><?=$info["details"];?></div></div><?php } ?></a>
