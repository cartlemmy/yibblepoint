<?php

require_once(SL_INCLUDE_PATH."/class.slNibbleIndexer.php");

function getSlDbIndexer($settings, $forceNew = false) {
	if (!isset($GLOBALS["slDbIndexerList"])) $GLOBALS["slDbIndexerList"] = array();
	$settings["orderdir"] = !isset($settings["orderdir"]) || $settings["orderdir"] == "asc" ? "asc" : "desc";
	if (!isset($settings["search"])) $settings["search"] = "";
	if (!isset($settings["searchFields"])) $settings["searchFields"] = null;
	
	if (!isset($settings["filter"])) $settings["filter"] = null;
	
	if (!isset($settings["where"])) $settings["where"] = "1";
	if (!isset($settings["parent"])) $settings["parent"] = "0";
		
	$ref = md5(json_encode(array($settings["table"],str_replace("`","",$settings["where"]),$settings["orderby"],$settings["orderdir"],searchify($settings["search"]),$settings["searchFields"])));
	
	if (isset($settings["fields"][$settings["orderby"]]["sectionType"])) {
		$settings["sectionType"] = $settings["fields"][$settings["orderby"]]["sectionType"];
	}
	
	if (!isset($GLOBALS["slDbIndexerList"][$ref])) {
		$GLOBALS["slDbIndexerList"][$ref] = new slDbIndexer($settings);
	}
	
	return $GLOBALS["slDbIndexerList"][$ref];
}

class slDbIndexer extends slClass {
	private $settings;
	private $cacheFile = "";
	private $headerSize = 1024;
	private $searchIndexer = null;
	private $searchFp = null;
	private $indexingSearch = false;
	private $fp;
	private $typeMap = array("date"=>"date");
	public $searchIndexerCount = 0;
	

	function __construct($settings = false) {
		if (!isset($GLOBALS["slIndexerFp"])) $GLOBALS["slIndexerFp"] = array();
		if ($settings) $this->connect($settings);
	}
	
	function __destruct() {
		if ($this->searchIndexer) $this->searchIndexer->__destruct();
		if ($this->fp) closeAndUnlock($this->fp);
		if ($this->searchFp) closeAndUnlock($this->searchFp);
	}
	
	function connect($settings) {
		if (!isset($settings["dir"])) return $this->error("dir not specified.");
		
		if (!isset($settings["sectionType"])) {
			if (isset($settings["fields"][$settings["orderby"]]["type"]) &&
			 ($type = $settings["fields"][$settings["orderby"]]["type"]) && 
			 isset($this->typeMap[$type])) {
				$settings["sectionType"] = $this->typeMap[$type];
			} else $settings["sectionType"] = "";
		}
		
		$dir = $settings["dir"]."/tmp/indexer";
		
		$extraTables = array();
		
		makePath($dir);
				
		$this->tableKey = $GLOBALS["slCore"]->db->getTableKey($settings["table"]);
		
		if (!isset($settings["orderby"])) $settings["orderby"] = $this->tableKey;
		$settings["orderdir"] = !isset($settings["orderdir"]) || $settings["orderdir"] == "asc" ? "asc" : "desc";
		if (!isset($settings["where"])) $settings["where"] = "1";
		if (!isset($settings["parent"])) $settings["parent"] = "0";
		if (!isset($settings["search"])) $settings["search"] = "";
		if (!isset($settings["searchFields"])) $settings["searchFields"] = null;
		if (!isset($settings["filter"])) $settings["filter"] = null;
		
		$info = $GLOBALS["slCore"]->db->getTableInfo($settings["fullTable"]);
		
		$table = array_pop(explode("/",$settings["table"]));
		
		$searchWhere = array();
		if ($settings["filter"]) {
			if ($info && $settings["filter"][0] == "queryFilter" && isset($info["queryFilters"][$settings["filter"][1]])) {
				$filter = $info["queryFilters"][$settings["filter"][1]];
				$searchWhere[] = $filter["where"];
			} else {
				if ($settings["filter"][0] == "group") {				
					$groupId = false;
					if (is_numeric($settings["filter"][1])) {
						$groupId = $settings["filter"][1];
					} else {
						$res = $GLOBALS["slCore"]->db->select("db/groups",array("name"=>$settings["filter"][1]));
						$row = $res->fetch_assoc();
						$groupId = $row["id"];
					}
					
					if ($groupId) {
						$extraTables[] = "groupLink";
						$searchWhere[] = "`groupLink`.`linkedId`=".$table.".`id` AND `groupLink`.`groupId`=".$groupId;
					}
				}
			}
		}
		
		if ($settings["search"]) {
			$q = searchify($settings["search"]);
			foreach ($settings["fields"] as $field=>$info) {
				if (isset($info["searchable"])) {
					$searchWhere[] = "`".$field."` LIKE '%".$GLOBALS["slCore"]->db->safe($settings["table"],$settings["search"],true)."%'";
				}
			}
		}
		
		if ($settings["searchFields"]) {
			$sf = array();
			foreach ($settings["searchFields"] as $n=>$v) {
				if (isset($info["fields"][$n])) {
					if (is_array($v)) {
						$r = $GLOBALS["slCore"]->db->where($settings["table"],array($n=>$v)," AND ",true);
						if ($r) $sf[] = $r;
					} else {
						$sf[] = "`".$n."` LIKE '%".$GLOBALS["slCore"]->db->safe($settings["table"],$v,true)."%'";
					}
				}
			}
			$searchWhere[] = "(".implode(" AND ",$sf).")";
		}
				
		if (count($searchWhere)) {
			if ($settings["where"] == "1") {
				$settings["where"] = implode(" OR ", $searchWhere);
			} else {
				$settings["where"] .= " AND (".implode(" OR ", $searchWhere).")";
			}
		}
		
		if (isset($info["optionGroup"]) && $settings["where"] == "1") {
			$settings["where"] = "(".$settings["where"].") AND `".$info["optionGroup"]["parent"]."`=0";
		}
		
		$this->cacheFile = $dir."/".md5(json_encode(array($settings["table"],str_replace("`","",$settings["where"]),$settings["orderby"],$settings["orderdir"],searchify($settings["search"]),$settings["filter"])));
	
		$select = array($table.".".$this->tableKey);
		
		if ($settings["orderby"] != $this->tableKey) $select[] = $table.".".$settings["orderby"];
		
		$this->settings = $settings;
		
		$this->sections = array();
		
		if (1 || !(is_file($this->cacheFile) && filemtime($this->cacheFile) > $GLOBALS["slSession"]->tableLastUpdated($this->settings["table"]))) {
			//TODO: Remove all related files
			
			if ($fp = openAndLock($this->cacheFile,"c+")) {
				ftruncate($fp,$this->headerSize);
				$this->num_rows = 0;
				$this->minRowsPerSection = 0;
				$this->rowsThisSection = 0;
				$this->pos = 0;
				$sectionPos = $this->headerSize;
				
				switch ($this->settings["sectionType"]) {
					case "date": //Figure out range
						if (is_array($this->settings["where"])) {
							$where = array_merge($this->settings["where"],array($this->settings["orderby"]=>array("!=",0)));
						} else {
							$where = $this->settings["where"]." AND `".$this->settings["orderby"]."`!=0";
						}
						if ($res = $GLOBALS["slCore"]->db->select(
							$this->settings["table"],
							$where,
							array("select"=>array(
								"MIN(`".$this->settings["orderby"]."`) AS 'l'",
								"MAX(`".$this->settings["orderby"]."`) AS 'h'"
							))
						)) {
							$row = $res->fetch_assoc();
							$dist = ($row["h"] - $row["l"]) / 86400;
							
							if ($dist > 365*5) {
								$this->settings["sectionType"] = "year";
							} elseif ($dist > 30*5) {
								$this->settings["sectionType"] = "month";
							}
						}
						break;
				}
				
				if ($res = $GLOBALS["slCore"]->db->select($this->settings["fullTable"], $this->settings["where"], array("orderby"=>$this->settings["orderby"]." ".$this->settings["orderdir"],"select"=>$select,"extraTables"=>$extraTables))) {
					$this->num_rows = $res->num_rows;
					$this->minRowsPerSection = round($this->num_rows / 40);
					
					fseek($fp,$this->headerSize);
					$this->currentSection = null;
					$orderBy = $this->settings["orderby"];
					while ($row = $res->fetch_assoc()) { // ran out of memory here
						$this->add($fp,$row[$this->tableKey],$row[$orderBy]);
					}
					$res->free();
					$sectionPos = ftell($fp);
					for ($i = 0, $len = count($this->sections); $i < $len; $i++) {
						fwrite($fp,pack("N",$this->sections[$i][0]).pack("n",$this->sections[$i][1]));
						$this->sections[$i][1] = $this->sectionDecode($this->sections[$i][1]);
					}
				}
				$this->writeHeader($fp,array("count"=>$this->pos,"sectionPos"=>$sectionPos));
				closeAndUnlock($fp);
			}
			$this->fp = $this->getIndexerFp();
		} else {
			$this->fp = $this->getIndexerFp();
			$this->header = json_decode(trim(fread($this->fp,$this->headerSize)),true);
			
			fseek($this->fp,$this->header["sectionPos"]);
			while (!feof($this->fp)) {
				if ($v = fread($this->fp,6)) {
					$section = unpack("Ni/ns",$v);
					$this->sections[] = array($section["i"],$this->sectionDecode($section["s"]));
				}
			}				
		}
	}
	
	function rowToSearchString($row) {
		$rv = array();
		foreach ($this->settings["fields"] as $field=>$info) {
			$rv[] = formatAsSearchText($row[$field],$info);
		}
		return implode(" ",$rv);
	}
	
	function count() {
		return isset($this->header["count"]) ? $this->header["count"] : 0;
	}
	
	function writeHeader($fp,$data) {
		$this->header = $data;
		$data = json_encode($data);
		fseek($fp,0);
		fwrite($fp,$data.str_repeat("\0", $this->headerSize - strlen($data)));
	}
	
	function add($fp,$id,$order) {
		fwrite($fp,pack("N",$id));
		$section = $this->sectionEncode($order);

		if ($section !== $this->currentSection && $this->rowsThisSection >= $this->minRowsPerSection) {
			$this->currentSection = $section;
			$this->sections[] = array($this->pos,$section);
			$this->rowsThisSection = 0;
		}
		$this->pos++;
		$this->rowsThisSection++;
	}
					 
	function get($i,$notSearch = false) {
		if ($i > $this->header["count"]) return false;
		fseek($this->fp,$this->headerSize + $i * 4);
		if ($v = fread($this->fp,4)) {
			$r = unpack("Ni",$v);
			if ($res = $GLOBALS["slCore"]->db->select($this->settings["table"], array($this->tableKey=>$r["i"]), array("select"=>array("*","_NAME"),"limit"=>"1"))) {
				$row = $res->fetch();
				
				$res->free();
				return $row;
			}
			return false;
		}
		return false;
	}
	
	function getKey($i,$notSearch = false) {
		if ($i > $this->header["count"]) return false;
		fseek($this->fp,$this->headerSize + $i * 4);
		if ($v = fread($this->fp,4)) {
			$r = unpack("Ni",$v);
			return $r["i"];
		}
		return false;
	}
	
	function sectionEncode($v) {
		switch ($this->settings["sectionType"]) {
			case "year":
				if ($v == 0) return 0;
				return (int)date("Y",$v);
				
			case "month":
				if ($v == 0) return 0;
				return (int)date("Y",$v) * 12 + ((int)date("n",$v) - 1);
				
			case "date":
				return floor($v / 86400);
				
			case "thousand":
				return floor($v / 1000);
			
			case "million":
				return floor($v / 1000000);
			
			 default:
				$v = ord(strtolower((string)$v));
				if ($this->settings["sectionType"] != "alpha-num" && $v >= 48 && $v <= 57) return 2;
				if ($v <= 32) return 0;
				return ($v >= 48 && $v <= 57) || ($v >= 97 && $v <= 122) ? $v : 1;				
		}
	}
	
	function sectionDecode($v) {
		switch ($this->settings["sectionType"]) {
			case "year":
				if ($v == 0) return "N/A";
				return date($GLOBALS["slConfig"]["international"]["year"],mktime(0,0,0,1,1,$v));
				
			case "month":
				if ($v == 0) return "N/A";
				return date($GLOBALS["slConfig"]["international"]["month"],mktime(0,0,0,($v % 12) + 1, 1, floor($v / 12)));
				
			case "date":
				if ($v == 0) return "N/A";
				return date($GLOBALS["slConfig"]["international"]["date"],$v * 86400);
				
			
				
			case "thousand":
				return ($v * 1000)."-".($v * 1000 + 999);
			
			case "million":
				return $v."M";
			
			default:
				if ($v == 0) return "BLANK";
				if ($v == 1) return '#!$';
				if ($v == 2) return '0-9';
				return strtoupper(chr($v));
		}
	}
	
	function getSections() {
		return $this->sections;
	}
	
	function getIndexerFp() {
		if (isset($GLOBALS["slIndexerFp"][$this->cacheFile])) {
			$GLOBALS["slIndexerFp"][$this->cacheFile][1] ++;
			return $GLOBALS["slIndexerFp"][$this->cacheFile][0];
		}
		
		$GLOBALS["slIndexerFp"][$this->cacheFile] = array(openAndLock($this->cacheFile,"r"),1);
		return $GLOBALS["slIndexerFp"][$this->cacheFile][0];
	}
}
