<?php 

if (isset($fromAPI) && $fromAPI && !isset($isWeb)) {
	$theme = null;
	$GLOBALS["slConfig"]["core"]["fromAPI"] = true;
} else {
	$themeDefFile = SL_THEME_PATH."/theme.json";
	$themeParsedFile = SL_THEME_PATH."/theme-parsed";
	$themeLayoutFile = SL_THEME_PATH."/layout.png";

	$theme = json_decode(file_get_contents($themeDefFile),true);

	if (!is_file($themeParsedFile) || filemtime($themeDefFile) > filemtime($themeParsedFile)  || filemtime($themeLayoutFile) > filemtime($themeParsedFile)) {
		if ($theme) {
			if (isset($theme["images"])) {
				if ($imSource = imagecreatefrompng($themeLayoutFile)) {
					if (isset($theme["inactive-offset"])) {
						$images = $theme["images"];
						foreach ($images as $name=>$image) {
							$image[0] += $theme["inactive-offset"][0];
							$image[1] += $theme["inactive-offset"][1];
							if ($image[0] + $image[2] < imagesx($imSource) && $image[1] + $image[3] < imagesy($imSource)) {
								$theme["images"]["inactive-".$name] = $image;
							}
						}
					}
					foreach ($theme["images"] as $name=>$image) {
						$imDest = imagecreatetruecolor($image[2],$image[3]);
						imagealphablending( $imDest, false );
						imagesavealpha( $imDest, true );
						imagecopy(
							$imDest, $imSource,
							isset($image[4]) ? $image[4] : 0, isset($image[5]) ? $image[5] : 0, 
							$image[0], $image[1], $image[2],$image[3]
						);
						imagepng($imDest, SL_THEME_PATH."/".$name.".png");
						imagedestroy($imDest);
					}
					imagedestroy($imSource);
				}
			}
		}
		file_put_contents($themeParsedFile,"Theme parsed ".date("Y-m-d H:i:s T"));
	}
}

?>

if (!window.addEventListener) {
	window.addEventListener = function (type, listener, useCapture) {
		return attachEvent('on' + type, function() { listener(event) });
	}
}

<?php


$vars = array(
	"web","dev","core","sep","package","net","preferences","international","isMobile","noEfx","version","versionCode"
);


$userID = isset($_SESSION["userID"])?$_SESSION["userID"]:0;
$config = array(
	"setupMode"=>$GLOBALS["slSetupMode"],
	"root"=>"//".$_SERVER["SERVER_NAME"].$GLOBALS["slConfig"]["requestInfo"]["docParent"]."/".CORE_NAME."/",
	"webRoot"=>WWW_BASE,
	"theme"=>$theme,
	"loggedIn"=>null,
	"loginTime"=>isset($_SESSION["loginTime"]) ? $_SESSION["loginTime"] : 0,
	"sessionName"=>session_name(),
	"userID"=>$userID,
	"parentID"=>isset($_SESSION["parentID"])?$_SESSION["parentID"]:0,
	"parentUser"=>isset($_SESSION["parentID"])?$_SESSION["parentUser"]:$userID,
	"isWeb"=>isset($isWeb)
);

foreach ($vars as $n) {
	$config[$n] = $GLOBALS["slConfig"][$n];
}

?>sl.config = <?=json_encode($config);?>;
