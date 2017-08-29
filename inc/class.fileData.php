<?php

/* Used for on-disk data storage.
 * Default to the global /data directory, also used by the user class
 * to store user data.
 */
 
class fileData {
	private $dataFiles = array();
	private $dataTables = array();
	public $tableKey = "id";
	
	function __construct($dir = false) {
		if ($dir === false) $dir = realpath(dirname(__FILE__)."/../data/");
		$this->dir = $dir;
	}
	
	//slDB functions
	function connect($settings = null, $password = false) {
		//TODO: Not sure this needs to do anything
		return true;
	}
	
	function selectByKey($table, $key) {
		return new fileDataResults(array($this->get($table,$key)));
	}
	
	function select($table, $find = false, $options = false) {
		$n = $this->find($table,is_array($find) ? $find : array("_KEY"=>$find),true);
		if ($n === false) return false;
		return new fileDataResults($n);
	}
	
	function upsert($table,$data,$find) {
		return $this->update($table,$data,$find,true);
	}
	
	function update($table,$data,$find,$insert=false) {
		$n = is_array($find) ? $this->find($table,$find) : $find;
		if ($n === false) {
			if ($insert) return $this->insert($table,array_merge($data,$find));
			return false;
		}
		
		if (is_array($n)) {
			foreach ($n as $k) {
				$this->upset($table,$k,$data);
			}
			return true;
		} else {
			return $this->upset($table,$n,$data);
		}
	}
	
	function insert($table,$data) {
		$n = isset($data[$this->tableKey]) ? $data[$this->tableKey] : md5(time()+json_encode($data));
		return $this->set($table,$n,$data) ? $n : false;
	}
	
	function find($table,$find,$returnData = false) {
		$dt = $this->dataTable($table);
		return $dt->find($find,$returnData);
	}
	
	//Core functions
	function get($table,$n = "*") {
		list($file,$hash) = $this->getFile($table,$n);
		if (!$file) return false;
		$dataTable = $this->dataTable($table);
		if ($n == "*") return $dataTable->getAllData();
		$data = $this->dataFile($dataTable,$file);
		return $data->get($n);
	}
	
	function upset($table,$n,$v = false) {
		if ($oldData = $this->get($table,$n,"*")) {
			$v = $v === false ? $oldData : array_merge($oldData,$v);
		}
		$this->set($table,$n,$v);
	}
	
	function set($table,$n,$v = false) {
		if (is_array($n)) {
			foreach ($n as $n1=>$v1) {
				$this->set($table,$n1,$v1);
			}
			return true;
		}
		list($file,$hash) = $this->getFile($table,$n,true);
		if (!$file) return false;
		$data = $this->dataFile($this->dataTable($table),$file);			
		return $data->set($n,$v);
	}
	
	function remove($table,$n) {
		if (is_array($n)) {
			foreach ($n as $n1) {
				$this->remove($table,$n1);
			}
			return true;
		}
		list($file,$hash) = $this->getFile($table,$n,true);
		if (!$file) return false;
		$data = $this->dataFile($this->dataTable($table),$file);			
		return $data->remove($n);
	}
	
	function getAllData($table) {
		$table = $this->dataTable($table);
		return $table->getAllData();
	}
	
	function getKeys($table) {
		$table = $this->dataTable($table);
		return $table->getAllData(true);
	}
	
	function getFile($table,$n,$create = false) {
		$hash = sha1($n);
		$path = $this->dir."/".safeFile($table,true)."/".substr($hash,0,2);
		if ($create && !makePath($path,true)) return array($this->error('Could not create path "'.$path.'"'),false);
		return array($path."/".substr($hash,2,2),substr($hash,4));
	}
	
	function dataFile($table,$file) {
		if (!isset($this->dataFiles[$file])) $this->dataFiles[$file] = new lgDataFile($table,$file);
		return $this->dataFiles[$file];
	}
	
	function dataTable($table) {
		if (!isset($this->dataTables[$table])) $this->dataTables[$table] = new lgDataTable($table,$this->dir);
		return $this->dataTables[$table];
	}
	
	function drop($table = false) {
		if ($table) {
			delTree($this->dir."/".safeFile($table,true));
		} else {
			delTree($this->dir);
		}
	}
	
	function error($txt) {
		$this->lastError = $txt;
		echo $txt;
		return false;
	}
}

class fileDataResults {
	private $data;
	private $curPos = 0;
	
	function __construct($data) {
		$this->data = $data;
	}
	
	function fetch_assoc() {
		if (isset($this->data[$this->curPos])) {
			$rv = $this->data[$this->curPos];
		} else {
			$rv = NULL;
		}
		$this->curPos ++;
		return $rv;
	}
}

class lgDataFile {
	private $data = false;
	
	function __construct($table,$file) {
		$h = explode("/",$file);
		$this->hash = $h[count($h)-2].$h[count($h)-1];
		$this->file = $file;
		$this->table = $table;
		$this->openFile($file);		
	}
	
	function openFile($file) {
		$this->file = $file;
		if (is_file($file)) {
			$this->load();
			return;
		}
		$this->data = array();
	}
	 
	function get($branch) {		
		$branch = explode(".",$branch);

		$root = array_shift($branch);
		
		$node = &$this->getDataRoot($root);
		
		if (count($branch) == 0) return $node;
		
		$lastNode = count($branch) - 1;
		foreach ($branch as $num=>$n) {
			if (isset($node[$n])) {
				$node = &$node[$n];
			} else return NULL;
		}
		return $node;	
	}
	
	function set($branch,$v) {		
		$branch = explode(".",$branch);

		$root = array_shift($branch);
		
		if (count($branch) == 0) {
			
			$this->data[$root] = $v;
			$this->save();
			return;
		}
		
		$node = &$this->getDataRoot($root,true);
		
		$lastNode = count($branch) - 1;
		foreach ($branch as $num=>$n) {
			if (isset($node[$n])) {
				$node = &$node[$n];
			} elseif ($num != $lastNode) {
				$node[$n] = array();
				$node = &$node[$n];
			} else {
				$node[$n] = $v;
			}
		}
		
		$this->save();
	}	
	
	function remove($branch) {
		$branch = explode(".",$branch);

		$root = array_shift($branch);

		if (count($branch) == 0) {
			unset($this->data[$root]);
			$this->save();
			return;
		}
		
		$node = &$this->getDataRoot($root,true);
		
		$lastNode = count($branch) - 1;
		foreach ($branch as $num=>$n) {
			if (isset($node[$n])) {
				$node = &$node[$n];
			} elseif ($num != $lastNode) {
				$node[$n] = array();
				$node = &$node[$n];
			} else {
				unset($node[$n]);
			}
		}
		
		$this->save();
	}
	
	function getAllData() {
		return $this->data;
	}
	
	function &getDataRoot($n,$create = false) {
		if (!isset($this->data[$n])) {
			if ($create) {
				$id = $this->table->insert($this->hash,$n);
				$this->data[$n] = array("_ID"=>$id);
				return $this->data[$n];
			} else {
				$r = NULL;
				return $r;
			}
		}
		return $this->data[$n];
	}
	
	function load() {
		if ($fp = openAndLock($this->file,"r+")) {
			fseek($fp,0,SEEK_END);
			$size = ftell($fp);
			fseek($fp,0);
			$this->header = $this->decodeHeader(fread($fp,32));
			$this->data = unserialize(fread($fp,$size-32));
			closeAndUnlock($fp);
		}
	}
	
	function save() {
		if ($fp = openAndLock($this->file,"w+")) {
			fwrite($fp,$this->encodeHeader().serialize($this->data));
			closeAndUnlock($fp);
		}
	}
	
	function info() {
		var_dump($this->data);
	}
	
	function decodeHeader($header) {
		if (substr($header,0,6) == "lgData") {
			$o = unpack("Nlock/nlockm/Nmodified/Ctype",substr($header,10));
			return array(
				"lock"=>$o["lock"]+$o["lockm"]/1000,
				"modified"=>$o["modified"],
				"type"=>$o["type"]
			);
		}
		return false;
	}
	
	function encodeHeader($o = array()) {
		$o = array_merge(array(
			"lock"=>0,
			"modified"=>time(),
			"type"=>0
		),$o);
		return "lgData\0\0\0\0".pack("NnNCxxxxxxxxxxx",(int)$o["lock"],(int)($o["lock"]*1000)%1000,$o["modified"],$o["type"]);
	}
}

class lgDataTable {
	function __construct($table,$dir) {
		$this->table = $table;
		$this->dir = $dir;
		$this->infoFile = $dir."/".$table."/info";
		$this->indexFile = $dir."/".$table."/index";
	}
	
	function insert($hash,$n) {
		if (is_file($this->infoFile)) {
			if ($fp = openAndLock($this->infoFile,"c+")) {
				fseek($fp,0,SEEK_END);
				$size = ftell($fp);
				fseek($fp,0);
				$info = unserialize(fread($fp,$size));
			} else return false;
		} else {
			$info = array(
				"inc"=>0,
				"created"=>time(),
				"lastModified"=>0
			);
		}
		$info["inc"]++;
		$id = $info["inc"];
		$info["lastModified"] = time();
		
		if (isset($fp)) {
			fseek($fp,0);
			ftruncate($fp,0);
		} else {
			$fp = openAndLock($this->infoFile,"w+");
		}
		
		if (isset($fp)) {
			fwrite($fp,serialize($info));
			closeAndUnlock($fp);
			$this->addIndex("id",$id,$hash,$n);
			return $id;
		}
		return false;		
	}
	
	function find($find, $returnData = false) {
		//TODO: track usage, and implement indexing when necessary
		if (!is_dir($this->dir."/".$this->table)) return false;
		
		if ($dp = opendir($this->dir."/".$this->table)) {
			$dirs = array();
			while ($file = readdir($dp)) {
				if (strlen($file) == 2 && $file != "..") {
					$dirs[] = $this->dir."/".$this->table."/".$file;
				}				
			}
			closedir($dp);
			
			$res = array();
			foreach ($dirs as $dir) {
				if ($dp = opendir($dir)) {
					while ($file = readdir($dp)) {
						if (strlen($file) == 2 && $file != "..") {
							$dataFile = new lgDataFile($this->table,$dir."/".$file);
							$data = $dataFile->getAllData();
							foreach ($data as $n=>$v) {
								$v["_KEY"] = $n;
								if (arrayFind($v,$find)) $res[] = $returnData ? $v : $n;
							}
						}
					}
				}
			}
			if ($res) return $res;
		}
		
		return false;
	}
	
	function getAllData($justKeys = false) {
		if (!is_dir($this->dir."/".$this->table)) return array();
		
		if ($dp = opendir($this->dir."/".$this->table)) {
			$dirs = array();
			while ($file = readdir($dp)) {
				if (strlen($file) == 2 && $file != "..") {
					$dirs[] = $this->dir."/".$this->table."/".$file;
				}				
			}
			closedir($dp);
			
			$res = array();
			foreach ($dirs as $dir) {
				if ($dp = opendir($dir)) {
					while ($file = readdir($dp)) {
						if (strlen($file) == 2 && $file != "..") {
							$dataFile = new lgDataFile($this->table,$dir."/".$file);
							$data = $dataFile->getAllData();
							foreach ($data as $n=>$v) {
								if ($justKeys) {
									$res[] = $n;
								} else {
									$res[$n] = $v;
								}
							}
						}
					}
				}
			}
			return $res;
		}
		
		return array();
	}
	
	function addIndex($name,$i,$hash,$n) {
		if ($fp = openAndLock($this->indexFile."-".$name,"c+")) {
			fseek($fp,$i*32);
			fwrite($fp,pack("H*a30",$hash,$n));
			closeAndUnlock($fp);
		}
	}
}
