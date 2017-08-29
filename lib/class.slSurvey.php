<?php

require_once(SL_INCLUDE_PATH."/class.wiki.php");

class slSurvey {
	private $name = "";
	public $id = 0;
	private $idCnt = 0;
	private $descriptor;
	public $jsData;
	public $pages = 0;
	private $cnt = 0;
	private $offline = false;
	private $sessionData = array();
	private $logic = array();
	public $web;
	private $defaultLikert = array("Strongly Disagree","Disagree","Neither agree nor disagree","Agree","Strongly agree");
	private $surveySession = false;
	private $surveySessionInfo = array();
	private $complete = false;
	private $questionNum = 0;
	public $noSubmit = false;
	public $viewOnly = false;
	public $colPos = 0;
	private $surveyNum = 0;
	private $countries = false;
	
	public function __construct($viewOnly = false) {
		if (!isset($GLOBALS["_SL_SURVEY_NUM"])) $GLOBALS["_SL_SURVEY_NUM"] = 0;
		$this->surveyNum = "S".$GLOBALS["_SL_SURVEY_NUM"] ++;
		
		$this->viewOnly = $viewOnly;
		if ($this->viewOnly) {
			$this->surveySession = $this->viewOnly;
			
			if ($res = $GLOBALS["slCore"]->db->select("db/surveySessions",array("id"=>$this->viewOnly))) {
				$surveySession = $res->fetch();
				$this->surveySessionInfo = $surveySession;
				$this->surveySession = $surveySession["id"];	
			}
			
		}
	}
	
	function load($file) {
		$this->name = pathToRelative(str_replace(".survey.xml","",$file));
		
	
		if (is_file($file.".php")) {
			
			ob_start();
			$res = require($file.".php");	
			
			//if (is_array($res) && isset($res["name"])) $this->name = $res["name"];
			
			$file = $this->viewOnly ? SL_DATA_PATH."/tmp/survey-sess-".$this->viewOnly.".xml" : $GLOBALS["slSession"]->getUserDir()."/".array_pop(explode("/",$file));
			
			$dir = explode("/",$file);
			array_pop($dir);
			makePath(implode("/",$dir));
			
			file_put_contents($file,ob_get_clean());
		}

		if (!($this->descriptor = simplexml_load_file($file))) {
			echo "slSurvey: Error loading xml\n";
			if (!is_file($file)) echo "XML file $file does not exist.\n";
			echo '<pre>'.htmlspecialchars(file_get_contents($file)).'</pre>';
			
			exit();
		}
		
		$this->jsData = array("name"=>$this->name, "fields"=>array(), "idMap"=>array());
				
		if ($this->noSubmit) $this->jsData["noSubmit"] = true;
		$this->cnt = 0;
		
		$this->buildData($this->descriptor);
		
		$this->updateSurveyDB(true);
		
		$this->pages = count($this->descriptor->page);
	}
	
	function updateSurveyDB($noSessionCreation = false) {		
		if (!isset($_SESSION["_SURVEY_SESS"])) $_SESSION["_SURVEY_SESS"] = array();
		
		if (setAndTrue($_SESSION["_SURVEY_SESS"],"id")) {
			$noSessionCreation = false;
			$this->surveySession = $_SESSION["_SURVEY_SESS"]["id"];
		}

		if (!$this->name) return;
		
		$data = array("name"=>$this->name);
				
		foreach ($this->jsData["info"]["0"]["_CHILD"] as $n=>$o) {
			$data[$n] = $o[0];
		}
		
		$this->id = $GLOBALS["slCore"]->db->upsert("db/surveys", $data, array("name"=>$data["name"]));
		$_SESSION["_SURVEY_SESS"]["survey"] = $this->id;
		
		if (!$noSessionCreation && !$this->viewOnly) {
			if ($this->noSubmit) {
				$surveySession["id"] = 0;
			} else {
				if (isset($_SESSION["_SURVEY_SESS"]["id"]) && !$_SESSION["_SURVEY_SESS"]["id"]) unset($_SESSION["_SURVEY_SESS"]["id"]);
				
				if (!(setAndTrue($_SESSION["_SURVEY_SESS"],"id") || isset($_SESSION["_SURVEY_SESS"]["contact"]) || isset($_SESSION["_SURVEY_SESS"]["attendee"])) || !($res = $GLOBALS["slCore"]->db->select("db/surveySessions",$_SESSION["_SURVEY_SESS"]))) {
					if (isset($_SESSION["_SURVEY_SESS"]["id"])) unset($_SESSION["_SURVEY_SESS"]["id"]);
					
					$surveySession["id"] = $GLOBALS["slCore"]->db->insert("db/surveySessions",$_SESSION["_SURVEY_SESS"]);

				} else {
					$surveySession = $res->fetch();
					$GLOBALS["slCore"]->db->update("db/surveySessions",$_SESSION["_SURVEY_SESS"],array("id"=>$_SESSION["_SURVEY_SESS"]["id"]));
				}
			}
			
			$this->setComplete($surveySession["complete"]);
			
			$this->surveySessionInfo = $surveySession;
			$this->surveySession = $_SESSION["_SURVEY_SESS"]["id"] = $surveySession["id"];
		} 		
	
		if (!$this->viewOnly) $GLOBALS["slCore"]->db->update("db/surveyFields", array("enabled"=>0), array("survey"=>$this->id));
				
		foreach ($this->jsData["fields"] as $n=>$field) {
			$field["survey"] = $this->id;
			$field["enabled"] = "1";
			$field["rawName"] = $field["name"];
			$field["name"] = (setAndTrue($field,"group")?$field["group"]." - ":"").$field["name"];
			
			unset($field["id"]);
			
			$answer = null;
			if ($this->viewOnly) {
				 if ($fRes = $GLOBALS["slCore"]->db->select("db/surveyFields", array("survey"=>$this->id, "name"=>$field["name"]))) {
					 $field = $fRes->fetch();
					 $this->jsData["fields"][$n]["fieldID"] = $field["id"];
					 if ($res = $GLOBALS["slCore"]->db->select("db/surveyAnswers",array("survey"=>$this->id,"surveySession"=>$this->surveySession,"surveyField"=>$this->jsData["fields"][$n]["fieldID"]))) {
						$answer = $res->fetch();
						$answer = json_decode($answer["answer"],true);
					}
				 }
			} elseif ($this->jsData["fields"][$n]["fieldID"] = $GLOBALS["slCore"]->db->upsert("db/surveyFields", $field, array("survey"=>$this->id, "name"=>$field["name"]))) {
				if ($this->surveySession && ($res = $GLOBALS["slCore"]->db->select("db/surveyAnswers",array("survey"=>$this->id,"surveySession"=>$this->surveySession,"surveyField"=>$this->jsData["fields"][$n]["fieldID"])))) {
					$answer = $res->fetch();
					$answer = json_decode($answer["answer"],true);
				}
			}
			$this->jsData["fields"][$n]["answer"] = $answer;
		}
	}
	
	public function setComplete($isComplete = true) {
		$this->complete = !!$isComplete;
	}
	
	public function isComplete() {
		return $this->complete;
	}
	
	function setSessionData($n,$v = false) {
		if ($this->viewOnly) return;
		if (!isset($_SESSION["_SURVEY_SESS"])) $_SESSION["_SURVEY_SESS"] = array();
		
		if (is_array($n)) {
			foreach ($n as $n2=>$v) {
				$this->sessionData[$n2] = $v;
				$_SESSION["_SURVEY_SESS"][$n2] = $v;
			}
			$this->updateSurveyDB();
		} else {
			$this->sessionData[$n] = $v;
			$_SESSION["_SURVEY_SESS"][$n] = $v;
		}		
	}
	
	function buildData(&$p,$group = "[]", &$parent = false, $pageTitle = "", $branch = "") {
		$branch = $branch ? explode(",",$branch) : array();
		
		if ($parent === false) $parent = &$this->jsData;
		$group = json_decode($group,true);

		foreach ($p->children() as $n=>$child) {
			$field  = false;
			switch ($n) {
				case "item": case "row":
					switch ($n == "row" ? "likert" : (string)$child["type"]) { //TODO: shouldn't always be likert, should change based on parent type
						case "hidden": case "text": case "likert":
						case "email": case "textarea": case "select":
						case "dropdown": case "checkbox":
						case "confirm-email": case "country":
						case "date": case "number": case "next":
						case "file":
							$id = "survey-i-".$this->cnt;
							
							$label = trim(isset($child["label"]) ? (string)$child["label"] : (string)$child);
							$name = trim(isset($child["name"]) ? (string)$child["name"] : $label);

							$field = array("name"=>$name,"id"=>$id,"label"=>$label,"page"=>$pageTitle);
							if ($p->getName() == "question") $field["qName"] = (string)$p["name"];
							$field["ord"] = $this->cnt;
							
							foreach ($child->attributes() as $n=>$v) {
								$field[$n] = (string)$v;
							}

							if ($child->children()) $field["hasChildren"] = 1;
							
							if (!isset($child["id"])) $child->addAttribute("id",$field["id"]);

							if ($group) $field["group"] = implode(" - ",$group);
							
							$this->jsData["fields"][] = &$field;
							$this->cnt++;
							break;
					}
					if (isset($child["group"])) $group[] = (string)$child["group"];
				
				case "page": case "content": case "group": case "likert-table": case "subrows": case "question":
					if ($n == "question") {
						$this->questionNum++;
						$child["name"] = $this->questionNum;
						if (!isset($p->startQuestion)) $p->startQuestion = $this->questionNum;
						$p->endQuestion = $this->questionNum;
					}
					$p2 = $field ? $field : $parent;
					$this->buildData($child, json_encode($group), $p2, (string)$child["title"] ? (string)$child["title"] : $pageTitle,implode(",",$branch));
					unset($field);
					break;			
				
				case "logic":
					if (!isset($this->logic[$pageTitle])) $this->logic[$pageTitle] = array();
					$this->logic[$pageTitle][] = (string)$child;
					break;
				
				case "option":
					break;
				
				default:
					if (!isset($parent[$n])) $parent[$n] = array();
					foreach ($child->attributes() as $an=>$v) {
						$parent[$an] = (string)$v;
					}

					$parent[$n][] = $this->parseChildData($child);

					break;
			}
		}
	}
	
	public static function _NN($n) {
		return trim(preg_replace('/[^\w\d\s\-]/','-',$n));
	}
	
	function sortRepFields($a,$b) {
		return strlen($b["name"]) - strlen($a["name"]);
	}
	
	function parseLogic($page,$code) {
		$replace = array(
			'/IF\s+([\w][\w\d\s\-\']+)\s+THEN\s+(.+)/'=>'self.ON(_PARSE_COMPARE($1),function(state){$2})',
			'/(ALL|ANY)\s+OF\s+([\w][\w\d\s\-]+)\s+(CHECKED|NOT EMPTY)/'=>'self.ALLANY("$1","$2","$3")',
			'ALSO'=>";\n",
			'/(SHOW|HIDE)\s+([^\;\}]+)\s*([\;\}])/'=>'self.CHANGE("$1","$2",state)$3',
			'/(MAKE)\s+(.+)\s+(REQUIRED)/'=>'self.$1("$2","$3",state)'
		);
		$code = explode("\n",$code);
		foreach ($code as &$line) {
			$line = trim($line);
			if ($line) {
				foreach ($replace as $from=>$to) {
					if (substr($from,0,1) == '/') {
						$line = preg_replace($from,$to,$line);
					} else {
						$line = str_replace($from,$to,$line);
					}
				}
			}
		}
		
		$from = array();
		$to = array();
		
		$repFields = $this->jsData["fields"];
		
		foreach ($this->jsData["idMap"] as $name=>$id) {
			if (strlen($name) < 64) $repFields[] = array("name"=>$name,"id"=>$id);
		}
		
		uasort($repFields,array($this,"sortRepFields"));
		
		foreach ($repFields as $n=>$field) {
			$from[] = self::_NN($field["name"]);
			$to[] = '`FIELDN:'.$n.'`';
			
			$from[] = self::_NN($field["id"]);
			$to[] = '`FIELDI:'.$n.'`';
		}
		
		foreach ($repFields as $n=>$field) {
			$from[] = '`FIELDN:'.$n.'`';
			$to[] = "self.FIELD_BY_NAME(".json_encode(self::_NN($field["name"])).")";
			
			$from[] = '`FIELDI:'.$n.'`';
			$to[] = "self.FIELD_BY_ID(".json_encode(self::_NN($field["id"])).")";
		}
		
		$replace = array(
			'/\s+IS BAD/'=>'.val(1) < 0',			
			'/\s+IS LESS THAN/'=>'.val(1) < ',			
			'/\s+IS LESS THAN OR EQUAL TO/'=>'.val(1) <= ',
			'/\s+IS GREATER THAN OR EQUAL TO/'=>'.val(1) >= ',
			'/\s+IS GREATER THAN/'=>'.val(1) > ',
			'/\s+IS GREATER THAN/'=>'.val(1) > ',
			'/\s+IS (\-?[\d\.]+) OR LESS/'=>'.val(1) <= $1',
			'/\s+IS (\-?[\d\.]+) OR MORE/'=>'.val(1) >= $1',
			'/\s+IS/'=>'.val() ==',
			'/\s+IS NOT/'=>'.val() !='
		);
		
		$code = implode("\n",$code);
		
		if (preg_match_all('/_PARSE_COMPARE\(([^\)]+)\)/',$code,$matches)) {
			foreach ($matches[1] as $n=>$cmp) {
				$cmp = str_replace($from,$to,$cmp);
				
				foreach ($replace as $f2=>$t2) {
					if (substr($f2,0,1) == '/') {
						$cmp = preg_replace($f2,$t2,$cmp);
					} else {
						$cmp = str_replace($f2,$t2,$cmp);
					}
				}

				$code = str_replace($matches[0][$n],$cmp,$code);
			}
		}
	
		return trim($code)."\n";
	}
	
	function parseChildData($node) {
		$rv = array();
		
		foreach ($node->attributes() as $an=>$v) {
			$rv[$an] = (string)$v;
		}
		
		if ($node->children()) {
			$rv["_CHILD"] = array();
			foreach ($node->children() as $n=>$child) {
				if (!isset($rv["_CHILD"][$n])) $rv["_CHILD"][$n] = array();
				$rv["_CHILD"][$n][] = $this->parseChildData($child);						
			}
		} else {
			if (count($rv) == 0) return (string)$node;
			$rv["_VALUE"] = (string)$node;
		}
		
		return $rv;
	}
		
	function showPage($pNum) {
		if ($page = $this->getPage($pNum)) {
			if ($page->content->children()) {
				echo '<div class="survey-page">';
				$this->showSubItems($page->content->children(),(string)$page["title"]);
				echo '</div>';
				return true;
			}
		}
		return false;
	}
	
	function showSubItems($parent,$pageTitle) {
		for ($i = 0; $parent[$i] !== null; $i++) {
			switch ($parent[$i]->getName()) {
				case "item":
					$l = "width";
					if ((string)$parent[$i]->attributes()->$l && $this->colPos == 0) echo '<div class="row">';
					$this->showItem($parent[$i],$pageTitle,$parent);
					if ((string)$parent[$i]->attributes()->$l && $this->colPos == 0) echo '</div>';
					$l = "linkto";
					if ((string)$parent[$i]->attributes()->$l) echo '</a>';

					break;
					
				case "question":
					echo '<div class="question"><div class="question-label">'.(string)$parent[$i]["name"].'</div>';
					$this->showSubItems($parent[$i]->children(),$pageTitle);
					echo '</div>';
					break;
			}
		}
	}
	
	function getPageHTML($pNum) {
			ob_start();
			$this->showPage($pNum);
			return ob_get_clean();
	}
	
	function getPageTitle($pNum) {
		if ($page = $this->getPage($pNum)) {
			return (string)$page["title"];
		}
		return false;
	}
	
	function getPage($pNum) {
		return isset($this->descriptor->page[$pNum]) ? $this->descriptor->page[$pNum] : false;
	}
	
	function initJS() {
		if ($this->viewOnly) return;
		$this->jsData["offline"] = $this->offline;
		?>
		<script type="text/javascript">
			sl.addLoadListener(function(){
				sl.surveyOb = new slSurvey(<?=json_encode($this->jsData);?>);
				sl.surveyOb.logicListener = function(self,page) {
					switch (page) {
				<?php
				echo "\n\n/* Survey Logic */\n";
				
				foreach ($this->logic as $page=>$code) {
					echo 'case '.json_encode($page).":\n".trim($this->parseLogic($page,implode("\n",$code)))."\nbreak;\n";
				}
				echo "\n"
				?>
					}
				};
			});
		</script><?php
	}

	function parseInputAttributes(&$item) {
		$show = array("min","max");
		$attributes = array();
		foreach ($item->attributes() as $n=>$v) {
			if (in_array($n,$show)) $attributes[] = $n.'="'.$v.'"';
		}
		return $attributes ? implode(" ", $attributes) : "";	
	}
	
	function parseAttributes(&$item, $extra = array()) {
		$attr = array();
		foreach ($item->attributes() as $n=>$v) {
			$attr[$n] = $v;
		}
		
		if (isset($attr["hide"])) {
			if (!isset($attr["style"])) $attr["style"] = "";
			$attr["style"] .= "display:".($attr["hide"]?"none":"").";";
		}

		$classes = array();
		
		$attributes = array();	
		foreach ($attr as $n=>$v) {
			switch ($n) {
				case "style":
					$attributes[] = $n."=\"".htmlspecialchars((string)$v)."\"";
					break;
					
				case "required":
					$this->updateField($item["id"],$n,!!$v);
					break;
					
				case "class":
					$classes = explode(" ",(string)$v);
					break;
			}
		}
		
		if ($item["width"]) {
			$w = explode("/",$item["width"]);
			$classes[] = "col-xs-".round(($w[0] / $w[1]) * 12);
			
			if ($this->colPos == 0) {
				$item["firstCol"] = true;
				$classes[] = "first-col";
			}
			
			$this->colPos += $w[0] / $w[1];
			
			if (abs($this->colPos - 1) < 0.0001) {
				$item["lastCol"] = true;
				$classes[] = "last-col";
				$this->colPos = 0;
			}
		} else $this->colPos = 0;
		
		
		if (isset($extra["class"])) {
			foreach ($extra["class"] as $c) {
				if (!in_array($c,$classes)) $classes[] = $c;
			}
		}
		
		if ($classes) $attributes[] = "class=\"".implode(" ",$classes)."\"";
		
		return $attributes ? " ".implode(" ", $attributes) : "";		
	}
	
	function updateField($id,$n,$v) {
		foreach ($this->jsData["fields"] as &$field) {
			if ($field["id"] == $id) {
				$field[$n] = $v;
				return;
			}
		}
	}
	
	function getFieldIdByItem($item) {
		if ($field = $this->getFieldByItem($item)) {
			return $field["id"];
		}
		return false;
	}
	
	function getFieldByItem($item) {
		$label = trim(isset($item["label"]) ? (string)$item["label"] : (string)$item);
		$name = trim(isset($item["name"]) ? (string)$item["name"] : $label);
		
		foreach ($this->jsData["fields"] as &$field) {
			if ((trim($label) !== "" && trim($name) === "" && $field["label"] == $label) || (trim($name) !== "" && $field["name"] == $name)) {
				return $field;
			}
		}
		return false;
	}
	
	public static function rotCell($content, $width = 40, $height = 80, $angle = 45) {
		$negAngle = 360 - $angle;
		$spanWidth = round($height / cos(deg2rad($angle)) - $width * cos(deg2rad($angle)));
		$spanBottom = round($width * cos(deg2rad($angle)) + $spanWidth / 4 + 2);
		echo '<th class="th-rot" style="height:'.$height.'px">';
		echo '<div style="left:'.round($height * tan(deg2rad($angle)) / 2).'px;
-ms-transform:skew(-'.$angle.'deg,0deg);
-moz-transform:skew(-'.$angle.'deg,0deg);
-webkit-transform:skew(-'.$angle.'deg,0deg);
-o-transform:skew(-'.$angle.'deg,0deg);
transform:skew(-'.$angle.'deg,0deg);">';
		echo '<span style="-ms-transform:skew('.$angle.'deg,0deg) rotate('.$negAngle.'deg);
-moz-transform:skew('.$angle.'deg,0deg) rotate('.$negAngle.'deg);
-webkit-transform:skew('.$angle.'deg,0deg) rotate('.$negAngle.'deg);
-o-transform:skew('.$angle.'deg,0deg) rotate('.$negAngle.'deg);
transform:skew('.$angle.'deg,0deg) rotate('.$negAngle.'deg);
bottom:'.($spanBottom-10).'px;
left:'.(0-$spanBottom).'px;
width:'.$spanWidth.'px">';
  
		echo htmlspecialchars($content).'</span></div></th>';
	}
	
	function showItem($item,$pageTitle,&$p) {
		
		$name = isset($item["name"]) ? (string)$item["name"] : (string)$item;
		$label = trim(isset($item["label"]) ? $item["label"] : (string)$item);
		
		$id = (string)$item["id"] ? (string)$item["id"] : ((string)$item["label"] ? safeName((string)$item["label"]) : "slS".($this->idCnt++));
		
		$type = (string)$item["type"];
		
		$reverse = isset($item["order"]) && $item["order"] == "reverse";
		
		if ($label && $id) $this->jsData["idMap"][$label] = $id;
		
		$answer = null;
		$fieldData = $this->getFieldByItem($item);
		
		if ($p->startQuestion && $p->endQuestion) {
			if ($p->startQuestion === $p->endQuestion) {
				$qnums = 'question '.$p->startQuestion;
			} elseif ($p->startQuestion + 1 == $p->endQuestion) {
				$qnums = 'questions '.$p->startQuestion.' &amp; '.$p->endQuestion;
			} else $qnums = 'questions '.$p->startQuestion.'-'.$p->endQuestion;
		} else $qnums = 'the questions';
		
		$replace = array('[qnums]'=>$qnums);
		
		echo '<a name="a-'.$id.'"></a>';
		
		$l = 'linkto';
		if ($link = (string)$item->attributes()->$l) echo '<a href="'.$link.'" target="_BLANK">';
		
		if ($this->viewOnly) {
			$id = $this->surveyNum."-".$id;
			if ($type == "email" || $type == "text") $type = "textarea";
		}
		
		$extra = array();
		
		switch ($type) {
			case "group":
				?><div id="cont-<?=$id;?>" <?=$this->parseAttributes($item);?>><?php
				$this->showSubItems($item->children(),$pageTitle);
				?></div><?php
				return;
			
			case "header": case "heading":
				echo "<h2 id=\"".$id."\"".$this->parseAttributes($item).">".self::format((string)$item,$replace)."</h2>\n";
				return;
				
			case "sub-header": case "sub-heading":
				echo "<h3 id=\"".$id."\"".$this->parseAttributes($item).">".self::format((string)$item,$replace)."</h3>\n";
				return;
				
			case "sep":
				echo "<div id=\"".$id."\" class=\"sur-sep\"></div>\n";
				return;
			
			case "note":
				$extra['class'] = array("note");
				if ($this->viewOnly) return;
				
			case "p":
				if ($item["wikify"]) {
					echo "<span id=\"".$id."\"".$this->parseAttributes($item,$extra).">";
					echo wiki::wikify((string)$item);
					echo "</span>";
				} else {
					echo "<p id=\"".$id."\"".$this->parseAttributes($item,$extra).">".self::format((string)$item)."</p>\n<div style=\"clear:both\"></div>\n";
				}
				return;
			
			case "hidden":
				?><input type="hidden" name="<?=$id;?>" id="<?=$id;?>" value="<?=(string)$item["value"];?>"><?php
				return;
			
			case "file":
				echo '<input type="hidden" name="'.$id.'-val" id="'.$id.'-val" value="'.(string)$fieldData["answer"].'">';

			case "number":
			case "date":	
			case "email":
			case "text":
			case "confirm-email":
				$fileUploaded = $type == "file" && $fieldData["answer"];
				?><div id="cont-<?=$id;?>" <?=$this->parseAttributes($item,array("class"=>array('form-group','sur-'.$type)));?>>
					<label for="<?=$id;?>"><?=htmlspecialchars($label);?></label>
					<?php if ($type=="file") {
						if ($this->viewOnly) echo '<a href="'.WWW_BASE.'survey-dl/?sess='.$this->surveySession.'&t='.sha1("SUR!ie478.".$this->id."-".$this->surveySession.".".$fieldData["name"]).'&f='.urlencode($fieldData["name"]).'" target="_BLANK">';
						echo '<div class="file-uploaded" id="'.$id.'-upl"'.($fileUploaded?'':' style="display:none"').'><div id="'.$id.'-upltxt">'.array_pop(explode(';',(string)$fieldData["answer"])).'</div>';
						
						if ($this->viewOnly) {
							echo "</a>";
						} else {
							echo '<button class="btn btn-primary" onclick="document.getElementById(\''.$id.'\').style.display=\'\';document.getElementById(\''.$id.'\').click()">Upload Different File</button>';
						}
						echo '</div>';
						if ($this->viewOnly) { echo '</div>'; return; }
					}					
					?>
					<input<?=$this->viewOnly?" READONLY":"";?> type="<?=(string)$item["type"];?>" class="form-control" name="<?=$id;?>" id="<?=$id;?>" placeholder="<?=htmlspecialchars((string)$item);?>" value="<?=$type!="file"&&$fieldData["answer"]!==null?htmlspecialchars($fieldData["answer"]):"";?>" <?=$this->parseInputAttributes($item);?><?=$fileUploaded?' style="display:none"':'';?>>
					<div class="survey-prog" id="<?=$id;?>-prog" style="display:none"><div></div></div>
				</div><?php
				return;
							
			case "textarea":
				?><div id="cont-<?=$id;?>" <?=$this->parseAttributes($item,array("class"=>array('form-group','sur-'.$type)));?>>
					<label for="<?=$id;?>"><?=htmlspecialchars($label);?></label><?php
					if ($this->viewOnly) {
						echo '<div class="view-only-textarea">'.($fieldData["answer"]!==null?htmlspecialchars($fieldData["answer"]):"").'</div>';
					} else {
						?><textarea class="form-control" name="<?=$id;?>" id="<?=$id;?>" rows="<?=isset($item["rows"])?(string)$item["rows"]:5;?>" placeholder="<?=htmlspecialchars((string)$item);?>"><?=$fieldData["answer"]!==null?htmlspecialchars($fieldData["answer"]):"";?></textarea><?php
					}
				?></div><?php
				return;
			
			case "select":
				?><div id="cont-<?=$id;?>" <?=$this->parseAttributes($item,array("class"=>array('form-group','sur-'.$type)));?>>
					<?php if (!$item["hidelabel"]) { ?><label><?=htmlspecialchars((string)$item["label"]);?></label><?php } ?>
					<input type="hidden" name="<?=$id;?>" id="<?=$id;?>" value="<?=$fieldData["answer"]!==null?htmlspecialchars($fieldData["answer"]):"";?>"><?php
						$out = array();
						$defAnswered = false;
						foreach ($item->option as $option) {
							$value = self::elAttr($option,"value") ? (string)$option["value"] : (string)$option;

							$checked = $fieldData["answer"] !== null && $fieldData["answer"] == $value;
							if ($checked) $defAnswered = true;
							$out[] = array(
								'<input'.($this->viewOnly?" class=\"view-only\"":"").' name="rad-'.$id.'" type="radio" style="zoom:200%" value="'.htmlspecialchars($value).'" onchange="if(this.checked)document.getElementById(\''.$id.'\').value=this.value;window.slSF(\''.$id.'\').changed();"'.($checked?" CHECKED":"").'>',
								htmlspecialchars((string)$option)
							);
						}
						
						if ($reverse) $out = array_reverse($out);
						
						if ($item["horizontal"]) {
							echo '<table style="width:100%"><thead><tr>';
							foreach ($out as $opt) {
								if (strlen($opt[1]) == 1) {
									echo '<th class="th-non-rot">'.$opt[1].'</th>';
								} else {
									self::rotCell($opt[1],40,160);
								}
							}
							echo '<th></th></tr></thead><tbody><tr>';
							foreach ($out as $opt) {
								echo '<td style="text-align:center">'.$opt[0].'</td>';
							}							
							echo '</tr></tbody></table>';
						} else {
							foreach ($out as $opt) {
								?><label>
									<?=$opt[0];?>
									<div><?=$opt[1];?></div>
								</label><?php
							}
						}
						
						if ($item["allowother"] && !($this->viewOnly && !$isOther)) {
							$isOther = $fieldData["answer"]!==null&&!$defAnswered;
							?><label>
								<input<?=($this->viewOnly?" class=\"view-only\"":"");?> name="rad-<?=$id;?>" type="radio" style="zoom:200%" value="_OTHER" onchange="if(this.checked)document.getElementById('<?=$id;?>').value=document.getElementById('<?=$id;?>-other').value;window.slSF('<?=$id;?>').changed();"<?=$isOther ? " CHECKED":"";?>>
								<div>Other:</div>
							</label>
							<div class="cb" style="height:10px"></div><?php
							if ($this->viewOnly) {
								echo '<div class="view-only-textarea">'.htmlspecialchars($fieldData["answer"]).'</div>';
							} else {
								?><input type="text" class="form-control" style="<?=!$isOther?'display:none':'';?>" name="<?=$id;?>-other" id="<?=$id;?>-other" value="<?=$isOther?htmlspecialchars($fieldData["answer"]):"";?>" onchange="document.getElementById('<?=$id;?>').value=this.value;window.slSF('<?=$id;?>').changed();"><?php
							}
						}
						
						
						
					?><div class="cb"></div>
				</div><?php
				return;
			
			case "likert":
				?><div id="cont-<?=$id;?>" <?=$this->parseAttributes($item,array("class"=>array('form-group','sur-'.$type)));?>>
					<?php if (!$item["hidelabel"]) { ?><label><?=htmlspecialchars((string)$item["label"]);?></label><?php } ?>
					<input type="hidden" name="<?=$id;?>" id="<?=$id;?>" value="<?=$fieldData["answer"]!==null?htmlspecialchars($fieldData["answer"]):"";?>"><?php
						$v = -2;
						$out = array();
						$out2 = array();
						$options = $item->option ? $item->option : $this->defaultLikert;
						foreach ($options as $option) {
							$value = isset($option["value"]) ? (string)$option["value"] : ($v > 2 ? 0 : $v);
							
							ob_start();
							?><label>
								<input<?=($this->viewOnly?" class=\"view-only\"":"");?> name="rad-<?=$id;?>" type="radio" style="zoom:200%" value="<?=$value;?>" onchange="if(this.checked)document.getElementById('<?=$id;?>').value=this.value;window.slSF('<?=$id;?>').changed();"<?=is_numeric($fieldData["answer"]) && $fieldData["answer"] == $value?" CHECKED":"";?>>
								<div><?=htmlspecialchars((string)$option);?></div>
							</label><?php
							if ($v > 2) {
								$out2[] = ob_get_clean();
							} else {
								$out[] = ob_get_clean();
							}
							$v++;
						}
						if ($reverse) $out = array_reverse($out);
						echo implode("\n",$out);
						if (count($out2)) {
							echo "<hr>".implode("\n",$out2);
						}
					?><div class="cb"></div>
				</div><?php
				return;
			
			case "likert-table":
				echo "<div id=\"cont-".$id."\" ".$this->parseAttributes($item,array("class"=>array('sur-'.$type))).">";
				echo '<table><thead>';
				ob_start();
				echo '<tr><th></th>';
				$v = -2;
				
				$options = $item->option && count($item->option) ? $item->option : $this->defaultLikert;
				
				foreach ($options as $option) {
					ob_start();
					if (strlen((string)$option) == 1) {
						echo '<th>'.(string)$option.'</th>';
					} else {
						echo '<th class="th-rot"><div><span>'.htmlspecialchars((string)$option).'</span></div></th>';
					}
					if ($v > 2) {
						$out2[] = ob_get_clean();
					} else {
						$out[] = ob_get_clean();
					}
					$v++;
				}
				
				
				if (count($out2)) {
					echo implode("\n",$out2);
					echo "<th class=\"vsep\"></th>";
				}
				echo implode("\n",$out);
				echo '</tr>'; //TODO: label properly
				
				$theadRep = ob_get_flush();
				
				
				echo '</thead><tbody>';
				$prevRowSubRows = false;
				$rowCnt = 0;
				foreach ($item->row as $row) {
					$fieldData = $this->getFieldByItem($row);

					$id = $fieldData["id"];

					if ($prevRowSubRows) echo "</tbody><thead>".$theadRep."</thead><tbody>";
					$prevRowSubRows = false;
					
					$row["id"] = $id;
					
					$label = trim(isset($row["label"]) ? (string)$row["label"] : (string)$row);
					$name = trim(isset($row["name"]) ? (string)$row["name"] : $label);
					
					if ($name && $id) $this->jsData["idMap"][$name] = $id;
							
					$field = array("name"=>$name,"id"=>$id,"label"=>$label,"page"=>$pageTitle);
					$field["ord"] = $this->cnt;
					
					if ($this->viewOnly) $id = $this->surveyNum."-".$id;
					
					echo '<tr'.($rowCnt&1?' class="odd"':'').'><td class="rl"><div>'.$label.'</div><input type="hidden" name="'.$id.'" id="'.$id.'"  value="'.($fieldData["answer"]!==null?htmlspecialchars($fieldData["answer"]):"").'"></td>';
					$v = -2;
					$optCnt = 0;
					
					
					$out = array();
					$out2 = array();
					
					foreach ($options as $option) {
						ob_start();
						$value = self::elAttr($option,"value") ? (string)$option["value"] : ($v > 2 ? 0 : $v);
						?><td><input<?=($this->viewOnly?" class=\"view-only\"":"");?> name="rad-<?=$id;?>" type="radio" style="zoom:200%" value="<?=$value;?>" onchange="if(this.checked)document.getElementById('<?=$id;?>').value=this.value;window.slSF('<?=$id;?>').changed();"<?=is_numeric($fieldData["answer"]) && $fieldData["answer"] == $value?" CHECKED":"";?>></td><?php
						if ($v > 2) {
							$out2[] = ob_get_clean();
						} else {
							$out[] = ob_get_clean();
						}
						$v++;
						$optCnt ++;		
					}
					if (count($out2)) {
						echo implode("\n",$out2);
						echo "<td class=\"vsep\"></td>";
						$optCnt ++;
					}
					echo implode("\n",$out);

					echo '</tr>';
					
					if ($row->subrows) {
						$prevRowSubRows = true;
				
						echo '<tr'.($rowCnt&1?' class="odd"':'').'><td class="rl"><div>'.(string)$row->subrows["label"].'</div></td><td colspan="'.$optCnt.'" class="rl">';
						$this->showSubItems($row->subrows->children(),$pageTitle);
						echo '</td></tr>';
					}
					
					$this->parseAttributes($row);
					$this->cnt++;
					$rowCnt++;
					unset($field);
				}
				echo '</tbody></table></div>';
				break;
				
			case "country":
				if (!$this->countries) {
					$returnValue = true;
					$this->countries = require(SL_INCLUDE_PATH."/data/countryList/out.php");
				}

				if (!$fieldData["answer"]) $fieldData["answer"] = strtoupper(array_pop(explode("-",substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,5))));
				
			case "dropdown":
				?><div id="cont-<?=$id;?>" <?=$this->parseAttributes($item,array("class"=>array('form-group','sur-'.$type)));?>>
					<label for="<?=$id;?>"><?=htmlspecialchars((string)$item["label"]);?></label>
					<select class="form-control" name="<?=$id;?>" id="<?=$id;?>"><?php
						if ($type == "country") {
							foreach ($this->countries["iso"] as $n=>$v) {
								?><option value="<?=htmlspecialchars($v);?>"<?=$fieldData["answer"]==$v?" SELECTED":"";?>><?=htmlspecialchars(urldecode($this->countries["name"][$n]));?></option><?php
							}
						} else {
							foreach ($item->option as $option) {
								$value = self::elAttr($option,"value") ? (string)$option["value"] : $option;
								?><option value="<?=htmlspecialchars($value);?>"<?=$fieldData["answer"]==$value?" SELECTED":"";?>><?=htmlspecialchars((string)$option);?></option><?php
							}
						}
					?></select>
				</div><?php
				return;
					
			case "checkbox":
				echo "<div id=\"cont-".$id."\" ".$this->parseAttributes($item,array("class"=>array('sur-'.$type)))."><label style=\"display:block\"><input".($this->viewOnly?" class=\"view-only\"":"")." name=\"$id\" id=\"$id\" type=\"checkbox\" style=\"zoom:200%\"".($fieldData["answer"]?" CHECKED":"").'> '.$label;
				if ($item->count()) {
					echo "<div id=\"sub-".$id."\" style=\"margin-left:80px;display:none\">";
					$this->showSubItems($item->children(),$pageTitle);
					echo "</div>";
				}
				echo "</label></div>\n";
				return;
				
			case "image":
				echo '<div id="cont-'.$id.'" '.$this->parseAttributes($item,array("class"=>array('sur-'.$type))).' class="image"><img src="'.(string)$item.'"></div>';
				return;
							
			case "submit":
				if ($this->viewOnly) return;
				if ($this->offline) {
					echo '<button type="button" class="btn btn-primary" onclick="sl.surveyOb.submit()">'.htmlspecialchars($label).'</button>';
				} else {
					if ($item["redirect"]) $this->jsData["submitRedirect"] = (string)$item["redirect"];
					echo '<button type="button" class="btn btn-primary" onclick="sl.surveyOb.saveResults(sl.surveyOb.submitRedirect)">'.htmlspecialchars($label).'</button>';
				}
				return;
			
			case "next":
				if ($this->viewOnly) return;
				echo '<button type="button" class="btn btn-primary" onclick="window.slMP.navNext()">'.($label?htmlspecialchars($label):"Next").'</button>';
				return;
		}
	}
	
	public static function elAttr($el,$n,$emptyStringIsTrue = true) {
		$a = json_decode(json_encode($el),true);
		return isset($a["@attributes"][$n]) ? ($emptyStringIsTrue && $a["@attributes"][$n] === "" ? true : $a["@attributes"][$n]) : null;
	}
	
	public static function format($str,$replace = false) {
		if ($replace) $str = str_replace(array_keys($replace),array_values($replace),$str);
		return preg_replace('/\[(\/?[A-Za-z]+)\]/','<$1>',$str);
	}
}
