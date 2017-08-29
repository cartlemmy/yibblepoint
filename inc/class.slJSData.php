<?php

class slJSData extends slClass {	
	private $encodeChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!#$%&()*+-./:<=>?@[]^_`{|}~';
	private $encodeCharsLen = 0;
	private $map = array();
	private $revMap = array();
	private $rows = array();
	private $cnt = array();
	private $labels = array();
	
	function __construct() {
		$this->encodeCharsLen = strlen($this->encodeChars);
	}
	
	function addRow($row) {
		foreach ($row as &$col) {
			$col = $this->mapData($col);
		}
		$this->rows[] = $row;
	}
	
	function setLabels($labels) {
		$this->labels = $labels;
	}
	
	function mapData($data) {
		$data = rawurlencode($data);
		if (isset($this->revMap[$data])) {
			$i = $this->revMap[$data];
			$this->cnt[$i] ++;
			return $i;
		}
		if (($i = array_search($data,$this->map)) !== false) {
			if ($this->cnt[$i] == 1) $this->revMap[$data] = $i;
			$this->cnt[$i] ++;
			return $i;
		}
		$i = count($this->cnt);
		
		$this->map[] = $data;
		$this->cnt[] = 1;
		return $i;
	}
	
	function outputJS() {
		echo 'new sl.jsData({"chars":'.json_encode($this->encodeChars).',"labels":'.json_encode($this->labels).',"map":'.json_encode(implode(",",$this->map)).',"rows":"';
		$this->outputRows();
		echo '"})';
	}
	
	function outputRows() {
		$first = true;
		foreach ($this->rows as $row) {
			if (!$first) echo ";";
			$first = false;
			$r = array();
			foreach ($row as $col) {
				$r[] = $this->encode($col);
			}
			echo implode(",",$r);
		}
	}
	
	function encode($v) {
		$rv = "";
		do {
			$rv = $this->encodeChars{$v % $this->encodeCharsLen}.$rv;
			$v = floor($v / $this->encodeCharsLen);
		} while ($v > 0);
		return $rv;
	}
}
