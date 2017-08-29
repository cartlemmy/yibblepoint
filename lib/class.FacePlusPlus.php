<?php

require_once(SL_INCLUDE_PATH."/class.slRemote.php");

class FacePlusPlus {
	private $rem;
	
	public function __construct() {
		$this->rem = new slRemote;
		$this->url = 'https://'.FACEPP_API_URL.'/v2';
	}
	
	public function detect($url, $params = array(), $outputHTML = false) {
		$params["url"] = $url;
		if ($res = $this->request('/detection/detect',$params)) {
			if ($outputHTML) $this->outputHTML($res);
			return $res;
		}
		return false;
	}
	
	public function identify($url, $params = array(), $outputHTML = false) {
		$params["url"] = $url;
		
		if (isset($params["detect"])) {
			$detect = $this->request('/detection/detect',$params);
		} else $detect = false;
		
		if ($res = $this->request('/recognition/identify',$params)) {
			if ($detect && isset($detect["face"])) {
				foreach ($detect["face"] as $n=>$face) {
					$res["face"][$n] = array_merge($face, $res["face"][$n]);
				}
			}
			if ($outputHTML) $this->outputHTML($res);
			return $res;
		}
		return false;
	}
	
	public function createGroup($groupName, $addPeople = array()) {
		$params = array("group_name"=>$groupName);
		if ($addPeople) $params["person_name"] = implode(",",$addPeople);
		return $this->request('/group/create',$params);
	}
	
	public function addPerson($name, $url) {
		if ($detect = $this->request('/detection/detect',array("url"=>$url))) {
			if (isset($detect["face"][0])) {
				return $this->request('/person/create',array(
					"person_name"=>$name,
					"face_id"=>$detect["face"][0]["face_id"]
				));				
			}
		}
		return false;
	}
	
	public function requestStatus($action,$params) {
		return $this->request($action,$params,0,true);
	}
	
	public function request($action,$params,$cache = 3600,$statusCheck = false) {
		$hash = md5($action."-".json_encode($params));
		$asyncFile = SL_DATA_PATH."/tmp/async-".$hash;
		$cacheFile = SL_DATA_PATH."/tmp/cache-".$hash;
		
		if ($cache && is_file($cacheFile) && filemtime($cacheFile) > time() - $cache) return json_decode(file_get_contents($cacheFile),true);
		
		if (is_file($asyncFile)) {
			$res = $this->request("/info/get_session",array("session_id"=>file_get_contents($asyncFile)));
			if (!$res["INQUEUE"]) unlink($asyncFile);
			return $res;			
		}
		
		if ($statusCheck) return true;
		
		$params["api_secret"] = FACEPP_API_SECRET;
		$params["api_key"] = FACEPP_API_KEY;
		$res = $this->rem->request(array(
			CURLOPT_URL=>$this->url.$action."?".http_build_query($params),
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_SSL_VERIFYHOST=>false,
			"encode"=>"json",
			"returnCachedIfError"=>$returnCachedIfError
		));
		
		if (!setAndTrue($res,"error")) {
			if (isset($params["url"]) && !isset($res["img_height"])) {
				$res["url"] = $params["url"];
				if ($im = getimagesize($params["url"])) {
					$res["img_width"] = $im[0];
					$res["img_height"] = $im[1];
				}
			}
			
			if ($res && setAndTrue($res,"session_id") && count($res) == 1) {
				file_put_contents($asyncFile,$res["session_id"]);
			} elseif ($cache) {
				file_put_contents($cacheFile,json_encode($res));						
			}
		}
		
		return $res;
	}
	
	public function outputHTML($res) {
		$width = $outputHTML > 1 ? $outputHTML : 736;
		echo '<div style="position:relative"><img src="'.$res["url"].'" style="width:'.$width.'px">';
		$xScale = $width / 100;
		$yScale = ($res["img_height"] / $res["img_width"]) * ($width / 100);
		foreach ($res["face"] as $n=>$face) {
			$cx = $face["position"]["center"]["x"] * $xScale;
			$cy = $face["position"]["center"]["y"] * $yScale;
			$w = $face["position"]["width"] * $xScale;
			$h = $face["position"]["height"] * $yScale;
			echo '<div style="position:absolute;border:1px solid #FF0;left:'.($cx - $w / 2 - 1).'px;top:'.($cy - $h / 2 - 1).'px;width:'.($w - 2).'px;height:'.($h - 2).'px;">';
			echo '<div style="position:absolute;font-family:sans-serif,arial;font-size:12px;color:#FFF;padding:2px;background-color:rgba(0,0,0,0.5);text-shadow: 1px 1px 1px #000;">'.($n+1).'</div>';
			echo '</div>';
			
		}
		echo '<div style="clear:both"></div>';
		echo '</div>';				
		
		if ($res["face"]) {
			foreach ($res["face"] as $n=>$face) {
				echo '<div style="float:left;width:50%;padding:8px"><table class="table table-striped"><thead><tr><th colspan="2">'.($n+1).'</th></tr></thead><tbody>';
				if (isset($face["candidate"])) {
					foreach ($face["candidate"] as $n=>$candidate) {
						if ($candidate["confidence"] > 3) {
							echo '<tr>';
							echo '<td>'.$candidate["person_name"].'</td>';
							echo '<td>'.round($candidate["confidence"]).'</td>';
							echo '</tr>';
						}
					}
				}
				foreach ($face["attribute"] as $name=>$attr) {
					if ($name == "pose") continue;
					if (!isset($attr["confidence"]) || $attr["confidence"] > 95) {
						echo '<tr>';
						echo '<td>'.$name.'</td>';
						echo '<td>'.(isset($attr["range"]) ? round($attr["value"]-$attr["range"]/2).' - '.round($attr["value"]+$attr["range"]/2) : $attr["value"]);
						if (isset($attr["confidence"])) echo ' ('.$attr["confidence"].')';
						echo '</td>';
						echo '</tr>';
					}
				}
				echo '</tbody></table></div>';
			}
		} else echo '<pre>'.print_r($res["face"],true).'</pre>';
	}
}

	
//https://apius.faceplusplus.com/v2/detection/detect?url=http%3A%2F%2Ffaceplusplus.com%2Fstatic%2Fimg%2Fdemo%2F1.jpg&api_secret=YOUR_API_SECRET&api_key=YOUR_API_KEY&attribute=glass,pose,gender,age,race,smiling
