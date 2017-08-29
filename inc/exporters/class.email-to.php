<?php

class exporterEmailTo {
	private $ref;
	private $uid;
	private $file;
	private $labels = array();
	private $fp = false;
	private $outputted = false;
	
	function __construct($uid = false) {
		if ($uid) {
			$this->uid = safeFile($uid);
			$this->file = $GLOBALS["slSession"]->userFilePath($this->uid,"text/plain");
		}
	}
	
	function __destruct() {
		$this->close();
	}
	
	function init($ref) {
		$this->ref = $ref;
		$this->fp = fopen($this->file,"a");
	}
	
	function getInfo($info = false) {
		if ($info && !isset($info["fields"]["email"])) return false;
		return array(
			"name"=>"E-mail 'To List'",
			//"options"=>array()
		);
	}
	
	function add($row) {
		if (!trim($row["Primary E-mail"])) return;
		fputs($this->fp,(ftell($this->fp) == 0?"":", ").($row["Name"] ? $row["Name"]." <".$row["Primary E-mail"].">":$row["Primary E-mail"]));
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
		return $GLOBALS["slSession"]->userFileURL($this->uid,"text/plain")."?download";
	}
}
