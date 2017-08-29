<?php

require_once(SL_INCLUDE_PATH.'/class.pDataFolder.php');

class slXMLData extends slClass {
	private $settings;
	private $tables = array();
	private $noUpdateEvents = false;
	private $inTransaction = false;
	private $info;
	
	public function __construct() {
		
	}
	
	public function connect($settings) {
		$this->settings = $settings;
		return true;
	}
	
	public function getTable($table) {
		if (is_object($table)) return $table;
		if (!isset($this->tables[$table])) {
			
			$this->info = $GLOBALS["slCore"]->db->getTableInfo("xml/".$table);			
			$tableOb = new pDataFolder($this->settings["dir"]."/".$table, true);
			$set = array('thumbSquare','thumbCropTop','maxImgWidth','maxImgHeight');
			foreach ($set as $n) {
				if (isset($this->info[$n])) $tableOb->$n = $this->info[$n];
			}
			$this->tables[$table] = $tableOb;
		}
		return $this->tables[$table];
	}
	
	public function select($table, $find = false, $options = false) {
		$table = $this->getTable($table);
		$res = new slXMLDataResults($table);
		
		return $res->query($find, $options) ? $res : false;
	}
	
	public function delete($table, $find, $options = array()) {
		$this->update($table, "DELETE", $find, $options);
	}		
		
	public function update($table, $data, $find, $options = array()) {		
		$table = $this->getTable($table);
		$delete = $data === "DELETE";
		
		if ($res = $this->select($table, $find, $options)) {
			while ($file = $res->fetchFile()) {
				
				$base = array_pop(explode("/",array_shift(explode(".",$file))));
				
				$xml = simplexml_load_file($file);
				$oldData = json_decode(json_encode($xml),true);
				$safeNew = $base;
				
				if (!$delete) {
					$changed = array();
					foreach ($data as $n=>$v) {
						if (!is_array($v) && $n == $table->info["nameField"]) $safeNew = safeName($v);
						if (!isset($oldData[$n]) || $oldData[$n] != $v) $changed[$n] = $v;
					}
					
					if (!count($changed)) return;
					
					self::dataToXml($xml,$data);
				
					if ($base != $safeNew) {
						$cnt = 0;
						while (is_file($check = str_replace($base.'.xml',$safeNew.($cnt?"-".$cnt:"").'.xml',$file))) {
							$cnt++;
						}
						if ($cnt) $safeNew .= "-".$cnt;
						
						$table->rename($base, $safeNew);

						if ($table->historyDir) {
							rename($table->historyDir."/".$base, $table->historyDir."/".$safeNew);
						}
						rename($file, $file = str_replace($base.'.xml',$safeNew.'.xml',$file));
						$base = $safeNew;
					}
				}
				
				$_KEY = $table->getIndex($base);			
				
				if ($table->historyDir) {
					$dir = $table->historyDir."/".$base;
					if (!is_dir($dir)) mkdir($dir);
					copy($file,$dir."/".date("Y-m-d-H-i-s").".xml");
				}
				
				if ($delete) {
					$GLOBALS["slCore"]->nonUserSpecificDispatch("change-".$table->info["table"]."/".$_KEY,array("DELETE"=>true),false);
					unlink($file);
				} else {
					if ($modField = $this->getFieldByType('modified')) {
						$changed[$modField[0]] = time();
					}
					$GLOBALS["slCore"]->nonUserSpecificDispatch("change-".$table->info["table"]."/".$_KEY,$changed,false);
					file_put_contents($file,$xml->asXML());
				}
				
				$GLOBALS["slCore"]->db->updated($table->info["table"]);
			}
		}
	}
	
	function processCustomFieldUpdate($info,$n,&$data) {
		$v = $data[$n];
		
		$field = $info["fields"][$n];
		$ar = array($v,&$data,$info);
		
		if (isset($info["_O"])) {
			$method = "update_".$n;
			if (method_exists($info["_O"],$method)) call_user_func_array(array($info["_O"],$method),$ar);
		}
	}
	
	function insert($table,$data,$find = false, $options = array("select"=>"id")) {
		ob_start();
		$table = $this->getTable($table);
				
		$p = array_pop(explode("/",$table->info["table"]));
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'."\n<".$p."/>");
		
		if ($cField = $this->getFieldByType('created')) {
			$data[$cField[0]] = time();
		}
		
		uasort($table->info["fields"], array('slDB', "fieldSort"));  
		foreach ($table->info["fields"] as $fn=>$field) {
			if (!isset($data[$fn]) && $field["depLevel"] > 0) {
				if (isset($field["type"]) && $field["type"] == "placeholder") {
					$data[$fn] = $_KEY;
				} else {
					$data[$fn] = "";
				}
			}
			if (isset($data[$fn])) $this->processCustomFieldUpdate($table->info,$fn,$data);
			if ($fn == $table->info["nameField"]) {
				$base = safeName(isset($data[$table->info["nameField"]]) ? $data[$table->info["nameField"]] : "UNNAMED");
				$cnt = 0;
				while (is_file($file = $table->dir."/".$base.($cnt?"-".$cnt:"").'.xml')) {
					$cnt++;
				}
				
				$n = $base.($cnt?"-".$cnt:"");
				
				$_KEY = $table->getIndex($n);		
				
				@mkdir($table->dir."/img/".$n, 0755, true);
			}
		}
	
		if (isset($data["_KEY"])) unset($data["_KEY"]);

		self::dataToXml($xml, $data);
		
		file_put_contents($file,$xml->asXML());
		
		$GLOBALS["slCore"]->nonUserSpecificDispatch("change-".$table->info["table"]."/".$_KEY,$data);
		
		$GLOBALS["slCore"]->db->updated($table->info["table"]);
		//file_put_contents(SL_DATA_PATH.'/debug.txt', ob_get_clean());
		return $_KEY;
	}
	
	public function safe($v, $noQuotes = false, $conn = false) {
		if ($noQuotes) return substr(var_export((string)$v,true),1,-1);
	
		return var_export($v,true);
	}
			
	private static function dataToXml(&$xml,$data) {
		foreach ($data as $n=>$v) {
			if (is_array($v)) {
				
			} else {
				if (!$xml->$n) {
					$xml->addChild($n,$v);
				} else {
					$xml->$n = $v;
				}
			}
		}
	}
		
	function getTableKey($table) {
		return "_KEY";
	}
	
	function getFieldByType($type) {
		$rv = array();
		foreach ($this->info["fields"] as $n=>$field) {
			if (isset($field["type"]) && $field["type"] === $type) {
				$rv[] = $n;
			}
		}
		return count($rv) ? $rv : null;
	}
	
	function preventUpdateEvents($table) {
		$this->noUpdateEvents = true;
	}
	
	function begin($table) {
		$this->inTransaction = true;
	}
	
	function commit($table) {				
		$this->inTransaction = false;
	}
}

class slXMLDataResults {
	private $table;
	private $res = array();
	private $I = 0;
	private $options;
	
	public function __construct($table) {
		$this->table = $table;
	}
	
	public function reset() {
		$this->I = 0;
	}
	
	public function query($find, $options = array()) {
		
		$this->setOptions($options);
		
		if (isset($options["limit"])) {
			$limit = is_string($options["limit"]) ? explode(",",$options["limit"]) : array($options["limit"]);
			if (count($limit) == 1) array_unshift($limit,0);
			$limit[0] = (int)$limit[0];
			$limit[1] = (int)$limit[1];
		} else $limit = false;
		
		$this->reset();
		$this->res = array();
		$i = 0;
		
		if ($find != 1 && is_string($find)) {
			$exp = $find;

			$exp = preg_replace('/(\`?[\w][\w\d]*\`?)\s+(LIKE)\s+\'(.*?)\'/','\\$this->$2($1,\'$3\')',$exp);
			$exp = str_ireplace(array(' AND ',' OR '),array(' && ',' || '),$exp);
			foreach ($this->table->info["fields"] as $n=>$o) {
				$exp = preg_replace('/\`?'.preg_quote($n,'/').'\`?/','$o['.var_export($n,true).']',$exp);
			}
		}

		foreach ($this->table->tree as $n=>$o) {
			$match = true;
			if ($find != 1) {
				if (is_string($find)) {
					eval('$match = '.$exp.';');
				} else {
					foreach ($find as $fn=>$v) {
						if (!isset($o[$fn])) $o[$fn] = "";
						
						if (is_array($v)) {
							switch ($v[0]) {
								case "<":
									if (!($o[$fn] < $v[1])) $match = false;
									break;
									
								case "<=":
									if (!($o[$fn] <= $v[1])) $match = false;
									break;
								
								case ">":
									if (!($o[$fn] > $v[1])) $match = false;
									break;
								
								case ">=":
									if (!($o[$fn] >= $v[1])) $match = false;
									break;
							}
						} elseif ($v != $o[$fn]) $match = false;
						if (!$match) break;
					}
				}
			}
			if ($match) {
				if ($limit === false || ($i >= $limit[0] && $i < $limit[0] + $limit[1])) { 
					$ob = array($n);
					if (isset($options["orderby"])) {
						foreach ($this->options["orderby"] as $oi) {
							$ob[] = isset($o[$oi[0]]) ? $o[$oi[0]] : false;
						}
					}
					$this->res[] = $ob;
				}
				$i++;
			}
		}
		
		if (isset($options["orderby"])) {
			if ($this->options["orderby"][0][1]) {
				usort($this->res,array($this,"orderdesc"));
			} else {
				usort($this->res,array($this,"orderasc"));
			}
		}

		return count($this->res) > 0;
	}
	
	public function LIKE($v, $exp) {
		$exp = preg_quote($exp);
		$exp = '^'.str_replace('%','.*?',$exp).'$';
		return preg_match('/'.$exp.'/i',$v);
	}
	
	public function orderasc($a,$b) {
		for ($n = 1; $n < count($a); $n++) {
			$r = $this->cmp($a,$b,$n);
			if ($r != 0) return $r;
		}
		return 0;
	}
	
	public function orderdesc($b,$a) {
		for ($n = 1; $n < count($a); $n++) {
			$r = $this->cmp($a,$b,$n);
			if ($r != 0) return $r;
		}
		return 0;
	}
	
	public function cmp($a,$b,$n) {
		if ((!is_numeric($a[$n]) && !is_bool($a[$n])) || (!is_numeric($b[$n]) && !is_bool($b[$n]))) return strcmp($a[$n], $b[$n]);
		if ($a[$n] === $b[$n]) return 0;
		if ($a[$n] === false) return 1;
		if ($b[$n] === false) return -1;
		return $a[$n] > $b[$n] ? 1 : -1;
	}
	
	public function setOptions($options) {
		if (isset($options["orderby"])) {
			$orderby = explode(",",$options["orderby"]);
			$options["orderby"] = array();
			foreach ($orderby as $oi) {
				$orderby = explode(" ",trim($oi));
				$options["orderby"][] = array($orderby[0],isset($orderby[1]) && strtolower($orderby[1]) == "desc");
			}
		}
		$this->options = $options;
	}
	
	public function fetch_assoc() {
		return $this->fetch();
	}
	
	public function fetchFile() {
		return $this->I < count($this->res) ? $this->getFile($this->res[$this->I++][0]) : false;
	}
		
	public function fetch($options = false) {
		return $this->I < count($this->res) ? $this->getData($this->res[$this->I++][0]) : false;
	}
	
	public function getData($n) {
		$point = $this->table->fetch($n);
		
		$data = $point->getAll();
		
		$data["n"] = $n;
		return $data;
	}
	
	public function getFile($n) {
		return $this->table->tree[$n]["path"];
	}
	
	public function free() {
		$this->reset();
		$this->res = array();
	}
}
