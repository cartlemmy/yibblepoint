<?php

class slName {
	public $parts = array();
	private $defaultFormat = array("namePrefix","nameFirst","alias","nameMiddle","nameLast","nameSuffix");
	private $format = array();
	
	private $namePrefixes = array(
		"ms","miss","mrs","mr","master","rev","reverend","fr","father","dr",
		"doctor","atty","attorney","prof","professor","hon","honorable","pres",
		"president","gov","governor","coach","ofc","officer","msgr","monsignor",
		"sr","sister","br","brother","supt","superintendent","rep","sir","sire",
		"representative","sen","senator","amb","ambassador","treas","treasurer",
		"sec","secretary","pvt","private","cpl","corporal","sgt","sargent",
		"adm","administrative","maj","major","capt","captain","cmdr",
		"commander","lt","lieutenant","lt col","lieutenant colonel","col",
		"colonel","gen","general","rabbi"
	);
	
	private $nameSuffixes = array(
		"jr","sr","phd","md","jd","do","pharmd","ab","ba","bfa","btech","llb",
		"bsc","ma","mfa","llm","mla","mba","msc","kbe","lld","dd","esq",
		"i","ii","iii","iv","v","vi","vii","viii","ix","x","qc","mp"
	);
	
	public function __construct() {
		$this->reset();
	}
	
	public function reset() {
		foreach ($this->defaultFormat as $n) {
			$this->parts[$n] = "";
		}
	}
	
	public function setName($name, $format = false) {		
		$this->reset();
		if ($format) {
			if (!is_array($format)) $format = $this->explode($format);
			
			$this->format = $format;
			
			$name = $this->explode($name);
			
			foreach ($name as $n=>$v) {
				if (isset($format[$n])) {
					$this->parts[$format[$n]] = str_replace(array('_','"'),array(' ',''),$v);
				}
			}
		} else {
			$this->aliasCheck($name);
						
			$name = explode(",",$name, 2);
			if (count($name) == 2) {
				$name = $name[1]." ".$name[0];
			} else {
				$name = $name[0];
			}
			
			$name = $this->explode($name);
			
			if (in_array(searchify($name[0]), $this->namePrefixes)) {
				$this->parts["namePrefix"] = array_shift($name);
			}
			
			if (in_array(searchify($name[count($name) - 1]), $this->nameSuffixes)) {
				$this->parts["nameSuffix"] = array_pop($name);
			}
			
			if (count($name)) $this->parts["nameFirst"] = array_shift($name);
			
			if (count($name)) $this->parts["nameLast"] = array_pop($name);
			if (count($name)) $this->parts["nameMiddle"] = array_pop($name);
			
			while (count($name)) {
				$this->parts["nameFirst"] .= " ".array_shift($name);
			}
			
			$this->format = array();
			foreach ($this->defaultFormat as $n) {
				if (trim($this->parts[$n])) $this->format[] = $n;
			}
		}
	}
	
	private function aliasCheck(&$name) {			
		if (preg_match('/[\"\'\(](.*?)[\"\'\)]/',$name, $match)) {
			$this->parts["alias"] = $match[1];
			$name = str_replace($match[0],'',$name);
		}
	}
			
	public function set($n, $v) {
		if (!in_array($n, $this->defaultFormat)) return false;
		
		$this->aliasCheck($v);
		
		$v = $this->explode($v);
		if (count($v) > 1) {
			switch ($n) {
				case "nameFirst":
					if (in_array(searchify($v[0]), $this->namePrefixes)) {
						$this->set("namePrefix", array_shift($v));
					}
					break;
					
				case "nameLast":
					if (in_array(searchify($v[count($v) - 1]), $this->nameSuffixes)) {
						$this->set("nameSuffix", array_pop($v));
					}
					break;
			}
		}
		
		$v = implode(" ",$v);
		
		if (!in_array($n, $this->format)) {
			$pos = -1;
			for ($i = array_search($n, $this->defaultFormat); $i >= 0; $i --) {
				$cn = $this->defaultFormat[$i];
				if (in_array($cn,$this->format)) {
					$pos = $i;
					break;
				}
			}
			if ($pos == -1) {
				$this->format[] = $n;
			} else {
				array_splice($this->format,$pos + 1,0,array($n));
			}
		}
		
		$this->parts[$n] = $v;
		
		return true;
	}
	
	public function getFormat() {
		return implode(' ',$this->format);
	}
	
	public function getFullName() {
		$rv = array();
		foreach ($this->format as $n) {
			$v = str_replace(' ','_',$this->parts[$n]);
			if ($n == "alias") $v = '"'.$v.'"';
			$rv[] = $v;
		}
		return implode(" ",$rv);
	}
	
	private function explode($v) {
		return preg_split('/[ \t\n\r\0\x0B]+/', trim($v));
	}
}
