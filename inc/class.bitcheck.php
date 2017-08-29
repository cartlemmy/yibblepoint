<?php

class bitcheck {
	private $fp;
	private $file;
	private $len = 0;
	private $allowRevert = true;
	public $statePrefix;
	private $stateStack = array();
	private $currentState = false;
	private $i = 0;
	public $first = 0;
	public $last = 0;
	
	public function __construct($file, $first = 0, $last = 0, $allowRevert = true) {
		$this->statePrefix = is_string($allowRevert) ? substr(base64_encode(sha1(json_encode($_SERVER), true)),0,7).'_' : '';
		$path = explode('/',$file);
		$file = array_pop($path);
		$path = implode('/',$path);
		if ($path === "") $path = '.';
		$file = $this->file = realpath($path).'/'.$file;
		$this->first = $first;
		$this->last = $last;
		
		if ($this->allowRevert = $allowRevert) $this->saveState(true);
		if (!($this->fp = fopen($file,"c+"))) die("Could not open bitfile.");
		fseek($this->fp, 0, SEEK_END);
		$this->len = ftell($this->fp);
		for ($i = 0; $i < 8; $i++) {
			$this->getMask[$i] = pow(2,$i);
			$this->setMask[$i] = pow(2,$i) ^ 255;
		}
		$this->reset();
	}
	
	public function reset() {
		$this->i = $this->first;
	}
	public function saveState($forceSaveToDrive = false, $i = -1) {
		$append = $i == -1;
		if ($append) $i = count($this->stateStack);
		
		$state = array(0, array(), false);
		if ($forceSaveToDrive) {
			$state[0] = 1;
			if (is_file($this->file)) {
				$state[2] = $this->getBackupName($i);
				copy($this->file, $state[2]);
			}
		}
		
		if ($append) {
			$this->stateStack[] = $state;
		} else {
			$this->stateStack[$i] = $state;
		}
		$this->currentState = &$state;
		return $i;
	}
	
	private function getBackupName($i) {
		$path = explode('/', $this->file);
		$file = array_pop($path);
		return implode('/', $path).'/.'.$this->statePrefix.$file.'.'.sprintf('%02d', $i).'.bu';
	}
	
	public function revert($to = -1, $keepStateFile = false) {
		$rv = array();
		if ($to < 0) {
			$to = count($this->stateStack) + $to;
		} 
		$i = count($this->stateStack);
		$state = false;
		while ($i > $to) {
			if ($state && $state[2] && !$keepStateFile) {
				unlink($state[2]);
			}
			$state = array_pop($this->stateStack);
			if ($state[2] && $keepStateFile) {
				$rv[] = $state[2];
			}
			$i--;
		}
		
		if ($state[0] == 1) {
			if ($keepStateFile) {
				copy($state[2], $this->file);
			} else {
				rename($state[2], $this->file);
			}
		} else {
			//TODO: state that is in memory
		}
		$this->reset();
		return $rv;
	}
	
	public function __destruct() {
		if ($this->fp) fclose($this->fp);
		while ($state = array_pop($this->stateStack)) {
			if ($state[0] == 1) {
				unlink($state[2]);
			}			
		}
	}
	
	public function lastSet() {
		$i = $this->len;
		
		while ($i > 0) {
			fseek($this->fp,$i);
			$l = ord(fgetc($this->fp));

			if ($l !== 0) {
				for ($j = 7; $j >= 0; $j--) {
					if (($this->getMask[$j] & $l) !== 0) return ($i << 3) + $j;
				}
			}
			$i--;
		}
		
		return 0;
	}
	
	public function firstUnset() {
		return $this->nextUnset(0);
	}
	
	public function nextUnset($i = 0) {
		$jStart = $i & 7;
		$pos = $i >> 3;
		if ($pos < $this->len) {
			fseek($this->fp, $pos);	
			while (!feof($this->fp)) {
				$l = ord(fgetc($this->fp));
				if ($l != 255) {
					for ($j = $jStart; $j < 8; $j++) {
						if (($this->getMask[$j] & $l) === 0) return $this->checkBounds(($pos << 3) + $j);
					}
				}
				$pos++;
				$jStart = 0;
			}
		}
		return $this->checkBounds(($pos << 3) + $jStart);
	}
	
	public function getNotSet() {
		if ($this->i == 0) {
			$this->i = $this->firstUnset();
		} else {
			$this->i = $this->nextUnset($this->i);
		}
		return $this->i ++;
		
	}
	
	private function checkBounds($i) {
		if ($this->last && $i > $this->last) return false;
		return $i;
	}
	
	public function get($bit) {
		$pos = $bit >> 3;
		if ($pos > $this->len) return false;
		
		fseek($this->fp,$pos);
		return !!(ord(fgetc($this->fp)) & $this->getMask[$bit&7]);
	}
	
	public function set($bit, $yes = true) {
		$pos = $bit >> 3;
		
		fseek($this->fp,$pos);
		$old = ord(fgetc($this->fp));
		fseek($this->fp,$pos);
		
		if ($this->currentState && $this->currentState[0] == 0) {
			if ($yes !== !!($old & $this->getMask[$bit&7])) {
				$this->currentState[1][] = $bit;
			}
		}
		
		if ($yes) {
			fwrite($this->fp,chr($old | $this->getMask[$bit&7]));
		} else {
			fwrite($this->fp,chr($old & $this->setMask[$bit&7]));
		}
	}
}
