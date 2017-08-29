<?php

class crmSurvey extends slAppClass {		
	function __construct($app) {
		$this->app = $app;
		
		if (isset($this->app->args[0])) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup = $this->app->translateArray($this->setup);
				
				if (isset($this->setup["permissions"])) {
					if (!$this->app->checkPermissions($this->setup["permissions"])) return;
				}
				
				$this->setup["args"] = $this->app->args;
				$this->setup["dir"] = $GLOBALS["slSession"]->user->dir;
			} else {
				$this->error = "'".$this->app->args[0]."' not found.";
			}
		} else {
			$this->error = "Item not specified.";
		}
		parent::__construct($app);
	}
	
	function getAll() {
		$data = null;
		if (isset($this->app->args[1])) {
			if ($this->app->args[1] == "NEW") {
				$data = array();
				foreach ($this->setup["fields"] as $n=>$v) {
					$data[$n] = "";
				}
			} else {
				if ($res = $GLOBALS["slCore"]->db->selectOne($this->app->args[0],array("_KEY"=>$this->app->args[1]))) {
					$data = $res->fetch();
				}
			}
		}
		return array(
			"setup"=>$this->setup,
			"data"=>$data
		);
	}
	
	function getUnique($params = false) {
		$a = $this->getAll();
		$unique = preg_replace('/[^A-Za-z\d\_\-]+/','_',$this->setup["name"]." ".$a["data"]["title"]);
		return strlen($unique) > 64 ? md5($unique) : $unique;
	}
	
	function export($type = "csv") {
		$exporterFile = SL_INCLUDE_PATH."/exporters/class.".safeFile($type).".php";
		if (is_file($exporterFile)) {
			require_once($exporterFile);
			$className = toCamelCase("exporter-".$type);
			$exporter = new $className($this->getUnique()."-export");
			
			$exporter->init($this->app->args[0]);
			$exporter->reset();
			
			$fields = array();
			$count = array();
			
			if ($res = $GLOBALS["slCore"]->db->select("db/surveyFields",array("survey"=>$this->app->args[1],"enabled"=>1),array("orderby"=>"ord"))) {
				while ($row = $res->fetch()) {
					$fields[$row["rawName"]] = array(
						"ref"=>"survey.".$row["id"],
						"def"=>$row
					);
					if ($row["type"] == "select") {
						$count[$row["rawName"]] = array();
					}
				}
			}
			
			$noAnswer = "No Answer";
			if ($res = $GLOBALS["slCore"]->db->select("db/surveySessions",array("survey"=>$this->app->args[1]))) {
				while ($sess = $res->fetch()) {
					$row = array();
					foreach ($fields as $n=>$field) {
						switch (array_shift(explode(".",$field["ref"]))) {
							case "survey":
								if ($r2 = $GLOBALS["slCore"]->db->selectOne("db/surveyAnswers",array("surveySession"=>$sess["id"],"surveyField"=>$field["def"]["id"]))) {
									$answer = $r2->fetch();
									$answerString = (string)json_decode($answer["answer"]);
									switch ($field["def"]["type"]) {
										case "checkbox":
											$row[$n] = $answer["answer"] == "true" ? "1" : "0";
											break;
										
										case "select":
											if (!isset($count[$n][$answerString])) $count[$n][$answerString] = 0;
											 $count[$n][$answerString]++;
											 
										default:
											$row[$n] = $answerString;
											break;
									}
								} else $row[$n] = "";
								break;
							
							default:
								$row[$n] = "";
								break;
						}
						if (trim($row[$n]) == "") $row[$n] = $noAnswer;
					}
					$exporter->add($row);
				}
			}
			
			if ($count) {
				$exporter->blankLine();
				$exporter->blankLine();
				$exporter->addArbitrary(array("# Answer Count"));
				$exporter->blankLine();
				

				foreach ($count as $n=>$o) {
					$exporter->addArbitrary(array($n,"Count"));
					foreach ($o as $on=>$tot) {
						$exporter->addArbitrary(array($on,$tot));
					}
					$exporter->blankLine();
				}
			}
			
			return array("action"=>array("open-url",$exporter->getFileUrl()));
		}
		return false;
	}

}
