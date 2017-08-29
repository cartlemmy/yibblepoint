<?php

class slBitIterator extends slClass {
	private $data;
	private $dataType = 0;
	private $i = 0;
	public $length = 0;
	
	function __construct($data = false) {
		if (is_string($data)) {
			$this->dataType = 1;
			$this->data = base64_decode($data);
			$this->length = strlen($this->data) * 8;
		} else {
			$this->data = $data;
			$this->length = count($data);
			$this->dataType = 2;
		}
	}
	
	function __destruct() {
		
	}
	
	function getNext() {
		if ($this->i >= $this->length) return false;
		if ($this->dataType == 2) {
			return $this->data[$this->i++];
		} else if ($this->dataType == 1) {
			while ($this->i < $this->length) {
				$byte = $this->i >> 3;
				$byteVal = ord($this->data{$byte});
				if ($byteVal & pow(2,$this->i & 7)) {
					return $this->i++;
				}
				$this->i ++;
			}
		}		
		return false;
	}	
	
	function setPos($pos) {
		$this->i = $pos;
	}
	
	function getPos() {
		return $this->i;
	}
	
	function reset() {
		$this->i = 0;
	}
}
