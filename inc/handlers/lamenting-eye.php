<?php

$lamentingEyeDir = SL_DATA_PATH."/lamenting-eye";
makePath($lamentingEyeDir);
$response = array();

switch ($_GET["a"]) {
	case "check-image":
		require_once(SL_INCLUDE_PATH."/class.slRemote.php");
		$rem = new slRemote();
		
		$url = file_get_contents("php://input");
		$cacheFile = $lamentingEyeDir."/".md5($url).".".array_pop(explode(".",$url));
		if (is_file($cacheFile)) {
			$response["cached"] = 1;
		} else {
			if ($res = $rem->request(array(
				CURLOPT_URL=>$url,
				CURLOPT_USERAGENT=>$_SERVER["HTTP_USER_AGENT"]
			))) {
				file_put_contents($cacheFile,$res);
				$response["cached"] = 1;
			} else {
				$response["fileNeeded"] = 1;
			}
		}
		break;
}

echo json_encode($response);
