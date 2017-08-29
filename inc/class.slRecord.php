<?php

// Write once, indexed data

class slRecord extends slClass {
	private $dir;
	private $info;
	private $additionalIndex;
	private $initialized = false;
	private $dataFile;
	private $indexFile;
	private $eof = false;
	
	private $headerSize = 1024;
	private $compressionCheckThreshold = 10000;
	private $compressionThreshold = 0.8;
	
	function __construct($dir,$additionalIndex = array()) {
		$this->dir = $dir;
		makePath($dir);
		$this->additionalIndex = $additionalIndex;
	}
	
	function append($data) {
		$this->initialize();
		
		if ($indexFp = openAndLock($this->indexFile,"c+")) {
			fseek($indexFp,0,SEEK_END);
			if ($dataFp = openAndLock($this->dataFile,"c+")) {
				
				fseek($dataFp,0,SEEK_END);
				
				$i = (ftell($indexFp) - $this->headerSize) / 4;
				
				foreach ($this->info["index"] as $index) {
					$this->writeIndex($index,isset($data[$index]) ? $data[$index] : 0, $i);
				}
				
				fwrite($indexFp,pack("N",ftell($dataFp)));
				
				fwrite($dataFp,$this->encode($data));
				
				closeAndUnlock($dataFp);
			}
			closeAndUnlock($indexFp);
		}
	}
	
	function getWhereRange($n,$start,$end) {
		
		$this->initialize();
		if (!is_file($this->dir."/".safeFile($n)."-ht")) return array();


		$startHash = $start & 0xFFFF;
		$diff = ($end - $start) & 0xFFFF;
		$rv = array();
		
		
		if ($htFp = openAndLock($this->dir."/".safeFile($n)."-ht","c+")) {
			if ($indexFp = openAndLock($this->dir."/".safeFile($n)."-index","c+")) {
				if (!$this->open()) {
					closeAndUnlock($indexFp);
					closeAndUnlock($htFp);
					return array();
				}
				for ($i = $startHash, $len = $diff + $startHash; $i <= $len; $i++) {
					$hashV = $i & 0xFFFF;
					
					fseek($htFp,$hashV*4);
					$htI = $this->indexValue($htFp);

					if ($htI != 0) {
						while (($j = $this->getLinkedListEntry($indexFp, $htI)) !== false) {
							$row = $this->get($j,true);
							if (isset($row[$n]) && $row[$n] >= $start && $row[$n] <= $end) {
								$rv[] = $row;
							}					
							$htI = false;
						}
					}			
				}
				$this->close();
				closeAndUnlock($indexFp);
			}	
			closeAndUnlock($htFp);
		}
		return $rv;
	}
	
	function getWhere($n,$v) {
		$this->initialize();
		if (!is_file($this->dir."/".safeFile($n)."-ht")) return array();

		$rv = array();
		$hashV = $this->toIndex($v);
		
		if ($htFp = openAndLock($this->dir."/".safeFile($n)."-ht","c+")) {
			if ($indexFp = openAndLock($this->dir."/".safeFile($n)."-index","c+")) {
				fseek($htFp,$hashV*4);
				$htI = $this->indexValue($htFp);

				if ($htI == 0) {
					closeAndUnlock($indexFp);					
					closeAndUnlock($htFp);
					return array();
				}			
				
				if (!$this->open()) {
					closeAndUnlock($indexFp);
					closeAndUnlock($htFp);
					return array();
				}
				
				while (($i = $this->getLinkedListEntry($indexFp, $htI)) !== false) {
					$row = $this->get($i,true);
					if (isset($row[$n]) && $row[$n] == $v) {
						$rv[] = $row;
					}					
					$htI = false;
				}
				$this->close();
				closeAndUnlock($indexFp);
			}	
			closeAndUnlock($htFp);
		}
		return $rv;
	}
	
	function get($i, $noOpenClose = false) {
		$this->eof = false;
		$rv = false; 
		$this->initialize();
		if ($noOpenClose || $this->open()) {
			fseek($this->indexFp,$this->headerSize + $i * 4);
			
			$start = $this->indexValue($this->indexFp); 
			if ($start != -1) {
				$end = $this->indexValue($this->indexFp);
				
				if ($end == -1) {
					fseek($this->dataFp,0,SEEK_END);
					$end = ftell($this->dataFp);
				}
				
				fseek($this->dataFp,$start);
			
				$rv = $this->decode(fread($this->dataFp,$end - $start));
			} else $this->eof = true;
			if (!$noOpenClose) {
				closeAndUnlock($this->dataFp);
				closeAndUnlock($this->indexFp);
			}
		}
		return $rv;
	}
	
	function count() {
		$this->initialize();
		return (filesize($this->indexFile) - $this->headerSize) / 4;
	}
	
	function open() {
		if (!is_file($this->indexFile)) return false;
		if ($this->indexFp = openAndLock($this->indexFile,"r")) {
			if ($this->dataFp = openAndLock($this->dataFile,"r")) {
				return true;
			}
			closeAndUnlock($this->indexFp);
		}
		return false;
	}
	
	function close() {
		closeAndUnlock($this->dataFp);
		closeAndUnlock($this->indexFp);
	}
	
	function indexValue($fp) {
		$d = fread($fp,4);
		if (strlen($d) < 4) return -1;
		$p = unpack("N",$d);
		return $p[1];
	}
	
	function initialize() {
		if ($this->initialized) return;
		$this->initialized = true;
		
		if (!is_dir($this->dir)) {
			makePath($this->dir);
		}
		
		$this->dataFile = $this->dir."/data";
		$this->indexFile = $this->dir."/index";
		
		$updateIndexFile = false;
		
		if (is_file($this->indexFile)) {
			$this->info = json_decode(trim(fileGetLock($this->indexFile,false,1024)),true);
		} else {
			$this->info = array(
				"created"=>time(),
				"index"=>array()
			);
			$updateIndexFile = true;
		}
		
		//Add any indexes?
		foreach ($this->additionalIndex as $index) {
			if (!in_array($index,$this->info["index"])) {
				$this->info["index"][] = $index;
				$this->initIndex($index);
				$updateIndexFile = true;
			}
		}
		
		if ($updateIndexFile) {
			if ($fp = openAndLock($this->indexFile,"c+")) {
				$s = json_encode($this->info);
				if (strlen($s) > $this->headerSize) $this->criticalError($this->indexFile." info header too big."); // TODO: this is obviously not a graceful way to handle this
				
				fseek($fp,0);
				fwrite($fp,$s.str_repeat("\0",$this->headerSize - strlen($s)));
				
				closeAndUnlock($fp);
			}
		}
	
	}
	
	function encode($data) {
		$data = json_encode($data);
		if (($len = strlen($data)) > $this->compressionCheckThreshold) {
			$compressed = gzcompress($data);
			if (strlen($compressed) / $len <= $this->compressionThreshold) return "g".$compressed;
			
		}
		return "j".$data;
	}
	
	function decode($data) {
		switch ($data{0}) {
			case "g":
				return json_decode(gzuncompress(substr($data,1)),true);
				
			case "j":
				return json_decode(substr($data,1),true);
		}
		return null;
	}
	
	function initIndex($index) {
		if ($htFp = openAndLock($this->dir."/".safeFile($index)."-ht","c+")) {
			ftruncate($htFp,65536*4);
			closeAndUnlock($htFp);
		}
		if ($indexFp = openAndLock($this->dir."/".safeFile($index)."-index","c+")) {
			ftruncate($indexFp,8); // First entry is nill
			closeAndUnlock($indexFp);
		}
		
		if (!$this->open()) return;
			
		$i = 0;
		while (1) {
			$data = $this->get($i,true);
			if ($this->eof) break;
			$this->writeIndex($index,isset($data[$index]) ? $data[$index] : 0, $i);
			$i++;
		}
		$this->close();
	}
	
	function writeIndex($index,$v,$i) {
		$v = $this->toIndex($v);
		if ($htFp = openAndLock($this->dir."/".safeFile($index)."-ht","c+")) {
			if ($indexFp = openAndLock($this->dir."/".safeFile($index)."-index","c+")) {
				fseek($htFp,$v*4);
				
				$htI = $this->indexValue($htFp);
				if ($htI == 0) { // Create new linked list
					fseek($htFp,$v*4);
					fseek($indexFp,0,SEEK_END);
					
					fwrite($htFp,pack("N",ftell($indexFp) / 8));
					fwrite($indexFp,pack("N",$i));
					fwrite($indexFp,pack("N",0));
				} else {
					while (($llV = $this->getLinkedListEntry($indexFp, $htI, $i)) !== false) {
						$htI = false;
					}
				}
				closeAndUnlock($indexFp);
			}	
			closeAndUnlock($htFp);
		}
	}
	
	function getLinkedListEntry($fp, $entry = false, $setEntryIfLast = false) {
		if ($entry !== false) fseek($fp, $entry * 8);
		$rv = $this->indexValue($fp);
		$nextEntry = $this->indexValue($fp);
		if ($nextEntry == -1) return false;
		if ($nextEntry == 0) {
			if ($setEntryIfLast !== false) {
				$lastEntryPos = ftell($fp) - 4;
				//Create new
				fseek($fp,0,SEEK_END);
				$newEntryPos = ftell($fp);
				fwrite($fp,pack("N",$setEntryIfLast));
				fwrite($fp,pack("N",0));
				
				//Link old to new
				fseek($fp, $lastEntryPos);
				fwrite($fp, pack("N",$newEntryPos / 8));
				return false;
			}
			fseek($fp,0,SEEK_END);
		} else {
			fseek($fp,$nextEntry * 8);
		}
		return $rv;
	}
	
	function toIndex($v) {
		if (is_numeric($v)) {
			return $v & 0xFFFF;
		} else {
			return hexdec(substr(md5($v),0,4));
		}
	}
}
