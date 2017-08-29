<script type="text/javascript">

function select(url,n,pn) {
	document.getElementById('imgPreview').src = url;
	var i = 0, id;
	
	document.getElementById('b-'+n).value = pn;
	
	while ((id = window.photoGroups[n][i]) && (el = document.getElementById("ph-"+n+"-"+id))) {
		el.className = pn == id ? "photo selected" : "photo";
		i++;
	}
}

</script>
<form method="post">

<div style="position:absolute;top:0px;width:100%;height:400px"><img id="imgPreview" style="height:400px"></div>
<div style="position:absolute;bottom:0px;width:98%;top:400px;overflow:auto"><?php

$pg = isset($_POST["pg"]) ? (int)$_POST["pg"] : 0;
if (isset($_POST["prev"])) $pg--;
if (isset($_POST["next"])) $pg++;

$totalGroups = $fs->getSimilarPhotoGroups(-1);
$perPage = 20;
$pages = ceil($totalGroups / $perPage);

$groups = $fs->getSimilarPhotoGroups($pg*$perPage,$perPage);

?><input name="pg" type="hidden" value="<?=$pg;?>"><?php

if ($pg > 0) echo '<input type="submit" name="prev" value="&larr; PREV"> ';
echo ' <input type="submit" name="update" value="UPDATE"> ';
if ($pg < $pages) echo ' <input type="submit" name="next" value="&rarr; NEXT">';

echo "<div>";

$groupMap = array();
foreach ($groups as $n=>$group) {
	$gm = array();
	$best = 0; $userSelectedPhoto = $bestPhoto = -1;
	if (isset($_POST["update"]) && $_POST["b-".$n]) {
		$bestPhoto = $_POST["b-".$n];
		foreach ($group as $pn=>$photo) {
			if ($pn == $bestPhoto) {
				$photo->setTag("Best");
				$userSelectedPhoto = $pn;
			} else {
				$photo->clearTag("Best");
			}
		}
	} else {
		foreach ($group as $pn=>$photo) {
			$gm[] = $pn;
			if ($photo->hasTag("Best")) {
				$userSelectedPhoto = $pn;
				$best = 100000000;
				$bestPhoto = $pn;
			} elseif (($detail = $photo->getAmountOfDetail(true)) > $best) {
				$best = $detail;
				$bestPhoto = $pn;
			}		
		}
	}
	$groupMap[] = $gm;
	
	echo "<input type=\"hidden\" id=\"b-".$n."\" name=\"b-".$n."\" value=\"$bestPhoto\">";
	foreach ($group as $pn=>$photo) {
		$selected = $pn == $bestPhoto;
		$userSelected = $pn == $userSelectedPhoto;
		echo "<div id=\"ph-".$n."-".$pn."\" class=\"photo".($selected?" selected":"").($userSelected?" user-selected":"")."\"><img src=\"".$photo->getURL()."&height=120\" onclick=\"select('".$photo->getURL()."',$n,$pn)\"><br />".$photo->getDimensions().($userSelected?" &#10003;":"")."</div>";
	}
	echo "<div style=\"clear:both\"><br /></div>\n";
}
echo "</div>";
?></div></form><script type="text/javascript">
window.photoGroups = <?=json_encode($groupMap);?>;
</script><?php
