<?php

require_once(SL_INCLUDE_PATH."/class.slDbIndexer.php");
require_once(SL_INCLUDE_PATH."/class.slReportOut.php");

class slItem extends slAppClass {
	private $dbi;
	private $setup = false;
	private $report = false;
	private $typeNames = array("text/html"=>"en-us|View / Print","text/csv"=>"en-us|Export CSV");
	
	function __construct($app) {
		$this->app = $app;
		if (isset($this->app->args[0]) && $GLOBALS["slSession"]->isLoggedIn()) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup["report"] = $this->getReport($this->app->args[1]);
				$this->setup = $this->app->translateArray($this->setup);
			}
		}
		parent::__construct($app);
	}
	
	function setup() {
		return $this->setup;
	}
	
	function getReport($name) {
		$file = $GLOBALS["slConfig"]["root"]."/lib/definitions/".safeFile(str_replace("/",".",$this->app->args[0]))."/reports/".safeName($name).".php";
		if (is_file($file)) {
			$info = require($file);
			if (class_exists($info["class"])) {
				$this->report = new $info["class"]($this);
				$info["inputs"] = $this->report->inputs;
				
				$oo = array();
				foreach ($this->report->outputOptions as $n=>$opt) {
					$file = $this->report->getOutFile($n);
					if (is_file($file)) {
						$head = file_get_contents($file,false,NULL,0,200);
						if (($pos = strpos($head,"//ALLOWED_TYPES:")) !== false) {
							$types = explode(",",array_shift(explode("\n",substr($head,$pos + 16))));
							foreach ($types as $type) {
								$o = $opt;
								$o["name"] = $opt["name"]." (".translate($this->typeNames[$type]).")";
								$oo[$n."/".$type] = $o;
							}
						}
					}
				}
				$info["outputOptions"] = $oo;
				
			}
			return $info;
		}
		return false;
	}
	
	function generate($params,$outType,$mimeType,$orderBy) {
		$this->report->setInputValues($params);
		if (method_exists($this->report,"setOrderBy")) $this->report->setOrderBy($orderBy);
		$this->report->query($outType);
		return $this->report->generate($outType,$mimeType);
	}
}
