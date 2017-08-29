<?php

require_once(SL_INCLUDE_PATH."/class.slContact.php");

function survey_submit($surveys) {
	if ($GLOBALS["slSession"]->isLoggedIn()) {
		$submitted = array();
		foreach ($surveys as $n=>$survey) {
			if ($res = $GLOBALS["slCore"]->db->select("db/surveys",array("name"=>$survey["survey"]))) {
				$surveyData = $res->fetch();

				$contact = new slContact($survey["data"],true,"survey");
				if ($contact->update($survey["data"])) {
					$s = array("survey"=>$surveyData["id"],"contact"=>$contact->data["id"],"uid"=>$survey["uid"]);
					if (!$GLOBALS["slCore"]->db->select("db/surveySessions",$s)) {
						$sess = $GLOBALS["slCore"]->db->upsert("db/surveySessions",$s,$s);

						foreach ($survey["data"] as $n=>$v) {
							if ($res = $GLOBALS["slCore"]->db->select("db/surveyFields",array("survey"=>$surveyData["id"],"name"=>$n))) {
								$field = $res->fetch();
								$s = array("survey"=>$surveyData["id"],"surveySession"=>$sess,"surveyField"=>$field["id"],"contact"=>$contact->data["id"]);
								$GLOBALS["slCore"]->db->upsert("db/surveyAnswers",array_merge($s,array("answer"=>json_encode($v))),$s);
							}
						}
					}
					$submitted[] = $survey["uid"];
				}
			}
		}
		return $submitted;
	} else return "login";
}

function survey_submitOne($survey) {
	$submitted = array();

	if ($res = $GLOBALS["slCore"]->db->select("db/surveys",array("name"=>$survey["survey"]))) {
		$surveyData = $res->fetch();
	
		$check = array("id","contact","uid","user","attendee");
		$createNew = true;
		foreach ($check as $n) {
			if (isset($_SESSION["_SURVEY_SESS"][$n])) {
				$createNew = false;
				break;
			}
		}
		
		$surveySession = array_merge(array("survey"=>$surveyData["id"]),$_SESSION["_SURVEY_SESS"]);
			
		if ($createNew || !($res = $GLOBALS["slCore"]->db->select("db/surveySessions",$surveySession))) {
			$surveySession["id"] = $GLOBALS["slCore"]->db->insert("db/surveySessions",$surveySession);
			$_SESSION["_SURVEY_SESS"]["id"] = $surveySession["id"];
		} else {
			$surveySession = $res->fetch();
		}

		$fields = array();
		
		$total = $complete = 0;
		
		foreach ($survey["answers"] as $n=>$v) {
			if ($res = $GLOBALS["slCore"]->db->select("db/surveyFields",array("survey"=>$surveyData["id"],"name"=>$n))) {
				$field = $res->fetch();
				if (setAndTrue($field,"required")) {
					$total++;
					if ($v !== "" && $v !== null) $complete ++;
				}
				$s = array("survey"=>$surveyData["id"],"surveySession"=>$surveySession["id"],"surveyField"=>$field["id"]);
				$GLOBALS["slCore"]->db->upsert("db/surveyAnswers",array_merge($s,array("answer"=>json_encode($v))),$s);
			}
		}
		
		$GLOBALS["slCore"]->db->update("db/surveySessions",array(
			"requiredAnswered"=>$complete,
			"required"=>$total,
			"complete"=>$total == $complete ? 1 : 0
		),$surveySession);
		
		$file = SL_WEB_PATH."/survey/".safeFile(array_pop(explode('/',$survey["survey"]))).".updated.php";
		if (is_file($file)) require_once($file);
		return array($complete,$total);
	}
		
	return false;
}

function survey_storeFile($info) {
	if ($res = $GLOBALS["slCore"]->db->select("db/surveys",array("name"=>$info["survey"]))) {
		$survey = $res->fetch();
		
		$surveySession = array_merge(array("survey"=>$survey["id"]),$_SESSION["_SURVEY_SESS"]);
			
		if ($createNew || !($res = $GLOBALS["slCore"]->db->select("db/surveySessions",$surveySession))) {
			$surveySession["id"] = $GLOBALS["slCore"]->db->insert("db/surveySessions",$surveySession);
			$_SESSION["_SURVEY_SESS"]["id"] = $surveySession["id"];
		} else {
			$surveySession = $res->fetch();
		}
		
		$path = SL_DATA_PATH.'/survey/'.safeFile(array_pop(explode('survey/',$info["survey"])));
		makePath($path);
		$file = $path."/".$surveySession["id"]."-".safeFile($info["field"]);
		file_put_contents($file.".info",json_encode($info["file"]));
		
		return file_put_contents($file,base64_decode(substr($info["content"],strpos($info["content"],'base64,')+6))).";".$info["file"]["name"];
		
	}
	return $info;
}
