<?php

class slNibbleIndexer extends slClass {
	public $itemsPerFile = 10240;
	private $currentFile = false;
	private $currentFileOb = null;
	private $dir = "";
	private $startTs;
	public $lastQueryDuration;
	
	function __construct($dir) {
		$this->dir = $dir;
		if (!is_dir($dir)) mkdir($dir);
		//echo self::toNibbleHash('PrintAndPhotos/Staff_Pictures/Staff_Fall2013',true)."\n"; exit();
	}
	
	function __destruct() {
		
	}
		
	function startTimer() {
		$this->startTs = microtime(true);
	}
	
	function endTimer() {
		$this->lastQueryDuration = microtime(true) - $this->startTs;
	}
	
	public function update($i,$text) {
		$file = $this->getFile($i);
		
		$file->update($i, $text);
	}
	
	public function get($i) {
		$file = $this->getFile($i);
		
		return $file->get($i);
	}
	
	private function fileName($i, $byFileNum = false) {
		return $this->dir.'/'.str_pad(dechex($byFileNum ? $i : floor($i / $this->itemsPerFile)),4,'0',STR_PAD_LEFT);
	}
	
	private function getFile($i) {
		$file = $this->fileName($i);
				
		if ($file != $this->currentFile) {
			$this->currentFile = $file;
			$this->currentFileOb = new slNibbleIndexerFile($this,$file);
		}
		return $this->currentFileOb;
	}
	
	public function encodeInt($int) {
		$rv = "";
		do {
			$rv .= chr(($int % 255) + 1);
			$int = floor($int / 255);
		} while ($int > 0);
		return $rv;
	}

	public function decodeInt($enc) {
		$enc = unpack("C*",$enc);

		$rv = 0;
		while ($n = array_pop($enc)) {
			$rv *= 255;
			$rv += $n - 1;
		}
		return $rv;
	}

	public function encodeString($string) {
		return json_encode($string);
	}
	
	public function deodeString($string) {
		return json_decode($string,true);
	}
		
	public function search($text, $limit = 100) {
		$this->startTimer();
		
		$hash = $this->toNibbleHash($text,true,true);
				
		$ids = array();
		
		$i = 0; $cnt = 0;
		while (is_file($file = $this->fileName($i,true))) {
			$res = $this->searchInFile($file,$hash,$limit - count($ids));
			foreach ($res as $id) {
				if (!in_array($id,$ids)) $ids[] = $id;
			}
			$i++;
		}
		
		$this->endTimer();
		return $ids;
	}
	
	private function searchInFile($file,$hash,$limit) {
		$len = floor(strlen($hash) / 2)*2;		
		$h1 = pack("H*", substr($hash,0,$len));
		if ($len == strlen($hash)) $len -= 2;
		$h2 = pack("H*", substr($hash,1,$len));
		
		$file = new slNibbleIndexerFile($this,$file);
		
		$rv = $file->search($h1,$limit);
		$rv2 = $file->search($h2,$limit - count($rv));
		
		foreach ($rv2 as $id) {
			if (!in_array($id,$rv)) $rv[] = $id;
		}
		
		return $rv;
	}
	
	public function fetchSearchIndex() {
		$files = array();
		$map = array();
		$i = $pos = 0;
		while (is_file($file = $this->fileName($i,true))) {
			$map[] = $pos;
			$pos += filesize($file);
			$files[] = $file;
			$i++;
		}
		
		echo json_encode($map,true)."\n";
		foreach ($files as $file) {
			readfile($file);
		}
	}
	
	public function count() {
		//TODO
	}
	
	public static function toNibbleHash($text,$asHex = false, $noPad = false) {
		$text = searchify($text,'');

		$hex = strtr(
			strtolower($text),
			"abcdefghijklmnopqrstuvwxyz ",
			"2456e8fbc45d9112a7e33fd123f"
		);
		if (!$noPad && strlen($hex)&1==1) $hex .= "f";

		return $asHex ? $hex : pack("H*" ,$hex);
	}
}

class slNibbleIndexerFile {
	private $indexer;
	private $file;
	private $fp;
	private $map = array();
	private $dataStartPos;
	private $blob = false;
	
	public function __construct($indexer,$file) {
		$this->indexer = $indexer;
		$this->file = $file;
		
		$this->init();
	}
	
	public function __destruct() {
		fseek($this->fp,0);
		for ($i = 0; $i < $this->indexer->itemsPerFile + 1; $i++) {
			fwrite($this->fp,pack("N",$this->map[$i]));
		}
		
		fclose($this->fp);
	}
	
	public function init() {
		$newFile = !is_file($this->file);
		$this->fp = fopen($this->file,"c+");
		$this->dataStartPos = ($this->indexer->itemsPerFile + 1) * 4;
		if ($newFile) {
			fwrite($this->fp,str_repeat("\0",$this->dataStartPos));
		}
		fseek($this->fp,0);
		
		for ($i = 0; $i < $this->indexer->itemsPerFile + 1; $i++) {
			$enc = unpack("N",fread($this->fp,4));
			$this->map[] = $enc[1];
		}
	}
	
	public function get($i) {
		$i = $i % $this->indexer->itemsPerFile;
		
		$start = $this->getPosFor($i);
		$len = $this->getPosFor($i + 1) - $start;
		
		if (!$len) return "";
		
		fseek($this->fp, $start);
		return bin2hex(fread($this->fp,$len));
	}
	
	
	public function update($i,$text) {
		$text = slNibbleIndexer::toNibbleHash($text,false);
		if (($len = strlen($text)) == 0) return;
		
		$i = $i % $this->indexer->itemsPerFile;
		
		$oldStart = $this->getPosFor($i);
		$oldEnd = $this->getPosFor($i + 1);
		$oldLen = $oldEnd - $oldStart;
		$diff = $len - $oldLen;
		
		$this->map[$i] = $oldStart;
		
		$entriesAfter = false;
		
		for ($j = $i + 1; $j < $this->indexer->itemsPerFile + 1; $j++) {
			if ($this->map[$j] > $oldEnd) $entriesAfter = true;
			if (!$this->map[$j]) $this->map[$j] = $this->dataStartPos;
			$this->map[$j] += $diff;
		}
		
		if ($entriesAfter && $diff != 0) {
			//rebuild file
			fseek($this->fp,$oldEnd);
			$endData = fread($this->fp,$this->map[$this->indexer->itemsPerFile] - $oldEnd);

			fseek($this->fp,$oldStart);
			fwrite($this->fp,$text.$endData);
		} else {
			//Append to file
			fseek($this->fp,$oldStart);
			fwrite($this->fp,$text);
			if ($diff < 0) { //TODO: remove excess
			}
		}		
	}
	
	public function getPosFor($i) {
		if ($i >= $this->indexer->itemsPerFile) {
			fseek($this->fp,0,SEEK_END);
			return ftell($this->fp);
		}
		$pos = 0;
		while ($pos == 0 && $i >= 0) {
			$pos = $this->map[$i];
			$i --;
		}
			
		if ($pos == 0) $pos = $this->dataStartPos;
		return $pos;
	}
	
	private function updateBlob() {
		if (!$this->blob) {
			fseek($this->fp,$this->dataStartPos);
			$this->blob = fread($this->fp,$this->map[$this->indexer->itemsPerFile] - $this->dataStartPos);
		}
	}
	
	public function search($bin,$limit) {
		$this->updateBlob();
		$rv = array();
		$i = 1;
		$cnt = $pos = 0;
		while (($pos = strpos($this->blob,$bin,$pos)) !== false) {
			while ($this->getPosFor($i) < $pos + $this->dataStartPos) {
				$i++;
			}
			if (!in_array($i,$rv)) $rv[] = $i;
			
			$pos++;
			$cnt++;
			if ($cnt >= $limit) break;
		}
		return $rv;
	}
}
