<?php

class slBruteForceProtect {
	private $file;
	private $hist;
	
	public $uniquePerMinute = 10;
	public $totalPerMinute = 20;
	
	function __construct() {
		$this->file = SL_DATA_PATH."/tmp/bf-".preg_replace('/[^\dA-Za-z]+/','-',$_SERVER["REMOTE_ADDR"]);
		$this->hist = is_file($this->file) ? json_decode(file_get_contents($this->file,true)) : array();
	}
	
	function __destruct() {
		file_put_contents($this->file,json_encode($this->hist));
	}
	
	public function check($params) {
		$hash = substr(preg_replace('/[^\dA-Za-z]+/','',base64_encode(md5(json_encode($params),true))),0,16);
		$this->hist[] = array(time(),$hash);
		
		while (count($this->hist) > 0) {
			if (count($this->hist) > 100 || time() > $this->hist[0][0] + 3600) {
				array_shift($this->hist);
			} else break;
		}
		
		if (count($this->hist) <= 2) return true;
		
		$u = array();
		$unique = 0;
		$startTs = $this->hist[0][0];
		$endTs = $this->hist[count($this->hist) - 1][0];
		$duration = ($endTs - $startTs) / 60;
		
		foreach ($this->hist as $h) {
			if (!in_array($h[1],$u)) {
				$unique++;
				$u[] = $h[1];
			}
		}
		
		if ($unique / $duration > $this->uniquePerMinute) return false;
		if (count($this->hist) / $duration > $this->totalPerMinute) return false;
		return true;		
	}
}

