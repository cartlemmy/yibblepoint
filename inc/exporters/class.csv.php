<?php

class exporterCsv {
	private $ref;
	private $uid;
	private $file;
	private $labels = array();
	private $fp = false;
	private $outputted = false;
	
	function __construct($uid = false) {
		if ($uid) {
			$this->uid = safeFile($uid);
			$this->file = $GLOBALS["slSession"]->userFilePath($this->uid,"text/csv");
		}
	}
	
	function __destruct() {
		$this->close();
	}
	
	function init($ref) {
		$this->ref = $ref;
		$this->fp = fopen($this->file,"a");
	}
	
	function getInfo() {
		return array(
			"name"=>"CSV",
			"options"=>array("field-selection")
		);
	}
	
	function add($row) {
		if (ftell($this->fp) == 0) {
			$this->labels = array();

			foreach ($row as $label=>$v) {
				if (!in_array($label,array("_KEY","_UNIQUE","_NAME","_IMAGE"))) {
					$this->labels[] = $label;
				}
			}
			fputcsv($this->fp,$this->labels);
		}
		$out = array();
		foreach ($this->labels as $n=>$label) {
			$out[] = isset($row[$label]) ? $row[$label] : "";			
		}
		fputcsv($this->fp,$out);
	}
	
	function blankLine() {
		fputcsv($this->fp,array());
	}
	
	function addArbitrary($row) {
		fputcsv($this->fp,$row);
	}
	
	function reset() {
		$this->close();
		$this->fp = fopen($this->file,"w");
	}
	
	function close() {
		if ($this->fp) fclose($this->fp);
		$this->fp = false;
	}
	
	function out() {
		$this->close();
		$this->outputted = true;
	}
	
	function getFile() {
		if (!$this->outputted) $this->out();
		return $this->file;
	}
	
	function getFileUrl() {
		$this->getFile();
		return $GLOBALS["slSession"]->userFileURL($this->uid,"text/csv")."?download";
	}
}
