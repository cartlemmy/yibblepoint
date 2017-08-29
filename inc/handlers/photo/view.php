<?php

$web->addScript(WWW_RELATIVE_BASE."web.js");
$web->addScript(webPathFromRelative("photo.js"));
$web->addStyleSheet(webPathFromRelative("photo.css"));

if (isset($_GET["tag"]) && $_GET["tag"]) {
	$photos = $fs->getPhotosByTag($_GET["tag"]);

?><script type="text/javascript">
var photos = <?=json_encode($photos);?>;
</script><?php

}

if (isset($_GET["tag"]) && $_GET["tag"]) {
	$web->setTitle("Photos - ".$_GET["tag"]);
	$web->setDescription("Photos of ".$_GET["tag"]." (".count($photos)." Photos)");
	echo "<h1>".$_GET["tag"]."</h1>";
} else {
	$web->setTitle("Photos");
}

$tags = $fs->getTags(isset($_GET["tag"]) && $_GET["tag"] ? $_GET["tag"] : true);

if (isset($_GET["tag"]) && $_GET["tag"]) {
	$parent = $fs->getParentTag($_GET["tag"]);
	?><div class="fspot-thumb" onclick="window.location.href='?tag=<?=urlencode($parent);?>'"><div style="float:left;height:48px;width:1px"></div><label><b>&uarr; <?=$parent?$parent:"ALL";?></b></label></div><?php
} else {
	?><div class="fspot-thumb" onclick="window.location.href='?tag=ALL'"><div style="float:left;height:48px;width:1px"></div><label><b>ALL PHOTOS</b></label></div><?php
}

foreach ($tags as $tag) {
	?><div class="fspot-thumb" onclick="window.location.href='?tag=<?=urlencode($tag["name"]);?>'"><img src="<?=$tag["icon"];?>"><label><?=$tag["name"];?></label></div><?php
}

echo "<div class=\"cb\"></div><hr /><div class=\"cb\"></div>";

if (isset($_GET["tag"]) && $_GET["tag"]) {	
	foreach ($photos as $id) {
		$photo = $fs->getPhoto((int)$id);
		$dims = $photo->getDimensions(true);
		if ($dims[1]) echo "<div class=\"photo\"><a href=\"javascript:;\" onclick=\"show(".$id.");\"><img data-load-vis=\"?ph=".$id."&height=120\" style=\"width:".(round(120 * ($dims[0] / $dims[1])))."px\"></a><br /><label>".$id."</label></div>";
	}
}
