<?php

require_once(SL_INCLUDE_PATH.'/class.fileData.php');

class DataSource {
	private $file;
	private $info;
	private $destInfo;
	public $dest;
	private $sh;
	private $sqlMap;
	private $sourceName;
	private $tableInfo = array();
	public $failed = false;
	private $nameTranslate = array();
	
	private $schoolWords = array(
		'school','elementary','high','junior','middle','of','the',
		'charter','springs','hills','ranch','street','avenue',
		'road','vista','studies','elem','california','day','hall',
		'creative','performing','arts','glen','academy','center','for',
		'intermediate','valley','hs','catholic','christian',
		'prep','preparatory',"view","private","district","public",
		"grade",'1st','2nd','3rd','4th','5th','6th','7th','8th',
		'unified','beach','parent','group','fundamental','canyon',
		'de','la','del','el','mission','store','2013','2014','2015',
		'2016','country','coop','cooperative','episcopal','girls',
		'boys','grande','international','american','magnet',
		'montessori','ms','fundamental','schools','simi','white',
		'oaks','gate'
	);
	
	public function __construct($file) {
		$this->file = $file;
		$this->sourceName = array_pop(explode("/",array_shift(explode(".",$file))));
		$this->info = require($file);
				
		$this->sqlMap = array(
			SQLITE3_TEXT=>"TEXT",
			SQLITE3_INTEGER=>"INTEGER",
			SQLITE3_FLOAT=>"REAL"
		);
		
		$file = explode("/",$file);
		array_pop($file);
		$destFile = implode('/',$file)."/".$this->info["table"].".php";
		$this->destInfo = require($destFile);
			
		if (isset($this->info["phpinc"])) {
			$this->sh = new DataSourcePHP($this->file, $this);
		} else {
			$source = $this->info["source"];
			
			if (is_array($source)) {
				if (isset($source["db"])) {
					$this->info["sourceType"] = "mysql";
					
					$this->sh = new DataSourceMysql($source);
					
				} elseif (isset($source["api"])) {
					$this->info["sourceType"] = "api";
					
					$this->sh = new DataSourceAPI($source);
				}			
			} else {
				$this->sh = new DataSourceCSV($source);
			}
			
			$this->info["sourceType"] = "mysql";
			
			foreach ($this->info["fields"] as $n=>$field) {
				if (isset($field["linkWeight"])) {
					if (!$this->destInfo["fields"][$field["to"]]["linkWeight"]) $this->destInfo["fields"][$field["to"]]["no_S"] = true;
					$this->destInfo["fields"][$field["to"]]["linkWeight"] = $field["linkWeight"];
				}
				if (isset($field["linkGroup"])) $this->destInfo["fields"][$field["to"]]["linkGroup"] = $field["linkGroup"];
			}
		
			if (!isset($this->destInfo["firstSearchField"])) {
				$best = 0;
				foreach ($this->destInfo["fields"] as $n=>$field) {
					if (isset($field["linkWeight"]) && $field["linkWeight"] > $best) {
						$this->destInfo["firstSearchField"] = $n;
						$best = $field["linkWeight"];
					}
				}
			}
		}
		
		$destDbFile = SL_DATA_PATH."/institute/dest.db";
		$this->dest = new SQLite3($destDbFile, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
		
		$tablesquery = $this->dest->query("SELECT name FROM sqlite_master WHERE type='table';");
		if (!$tablesquery) {
			$this->failed = true;
			return;
		}
		
		$tables = array();
		while ($table = $tablesquery->fetchArray(SQLITE3_ASSOC)) {
			$tables[] = $table['name'];
		}
		
		echo "TABLES: ".json_encode($tables)."\n";
		if (!in_array($this->destInfo["table"],$tables)) {

			$extra = array();
			$fields = array();
			foreach ($this->destInfo["fields"] as $n=>$field) {
				if ((isset($field["linkWeight"]) && !setAndTrue($field,'caseSensitive')) || setAndTrue($field,'searchify')) $fields['S_'.$n] = SQLITE3_TEXT;
			}
			
			foreach ($this->destInfo["fields"] as $n=>$field) {
				$fields[$n] = isset($field["SQLITE_TYPE"]) ? $field["SQLITE_TYPE"] : SQLITE3_TEXT;
				if (isset($field["otherTable"])) {
					$fields[$n."ID"] = SQLITE3_INTEGER;
					$extra[] = "CREATE INDEX \"".$n."\" on ".$this->destInfo["table"]." (".$n." ASC);";
				}
			}
			
			$fields["relIDs"] = SQLITE3_TEXT;
			$fields["extra"] = SQLITE3_TEXT;
			$fields["_updated"] = SQLITE3_INTEGER;
			
			$f = array();
			foreach ($fields as $field=>$type) {
				$f[] = '"'.$field.'" '.$this->sqlMap[$type];
			}
			
			echo "CREATE TABLE ".$this->destInfo["table"]." (\n\t\"id\" INTEGER PRIMARY KEY AUTOINCREMENT,\n\t".implode(",\n\t",$f).");\n";
			$this->dest->exec("CREATE TABLE ".$this->destInfo["table"]." (\n\t\"id\" INTEGER PRIMARY KEY AUTOINCREMENT,\n\t".implode(",\n\t",$f).");");
			foreach ($extra as $query) {
				$this->dest->exec($query);
				echo $query."\n";
			}
		}
		
		foreach ($this->destInfo["fields"] as $n=>$field) {
			if (isset($field["otherTable"])) {
				$this->destInfo["fields"][$n."ID"] = array();
			}
		}
			
		$this->destInfo["fields"]["relIDs"] = array();
		
		$nameFile = SL_DATA_PATH."/institute/name-translate.json";
		$nameTranslate = is_file($nameFile) ? json_decode(file_get_contents($nameFile), true) : array();
		
		foreach ($nameTranslate as $n=>$to) {
			if ($to[1]) $this->nameTranslate[$n] = $to[1];
		}
	}
	
	public function fetch($start = false) {
		if (isset($this->info["phpinc"])) return $this->sh->fetch($start);
		return $this->process($this->sh->fetch($start));
	}
	
	private function process($data) {
		if (!$data) return null;
		
		$otherTables = array();
		$rv = array();
		
		foreach ($data as $n=>&$v) {
			if (isset($this->info["fields"][$n])) {
				$field = $this->info["fields"][$n];
				if (isset($field["parse"])) {
					eval('$v = '.$field["parse"].';');
					if ($v === false) continue;
				}
			}
		}
		unset($v);
		
		foreach ($data as $n=>$v) {
			//echo $n.": ".json_encode($v)."\n";
			$toOrig = toCamelCase($this->info["prefix"].'-'.$n);
			$to = setAndTrue($this->info,"ignoreExtraFields") ? false : $toOrig;
			if (isset($this->info["fields"][$n])) {
				$field = $this->info["fields"][$n];
				
				if (isset($field["parse"]) && $v === false) continue;
				
				if (isset($field["skip"])) {
					eval('$skip = '.preg_replace('/\$([\w\d]+)/','\$data["$1"]', $field["skip"]).";");
					if ($skiOaksp) continue;
				}
								
				if (isset($field["prepend"])) {
					$v = $field["prepend"].$v;
				}
				
				if (isset($field["append"])) {
					$v .= $field["append"];
				}
				
				
				if (isset($field["otherTable"])) {
					$otherTable = $this->otherTableInfo($field["otherTable"]);
					
					$rv[$toOrig] = $v;
					
					$lf = isset($field["to"]) ? $field["to"] : $field["otherTable"];
				
					if (isset($field["by"])) {
						
						if ($stmt = $this->dest->prepare('SELECT * FROM `'.$field["otherTable"].'` WHERE `'.$field["by"].'`=:'.$field["by"])) {
							$stmt->bindValue(':'.$field["by"], $v, SQLITE3_TEXT);
							
							if (($res = $stmt->execute()) && self::hasRows($res)) {
								$otherRow = $this->procFetch($res->fetchArray(SQLITE3_ASSOC));

								$rv[$lf."ID"] = $otherRow["id"];
								
								if (isset($field["storeAs"])) {
									eval('$v = '.preg_replace('/\$([\w\d]+)/','\$otherRow["$1"]', $field["storeAs"]).";");
								} elseif (isset($otherTable["storeAs"])) {
									eval('$v = '.preg_replace('/\$([\w\d]+)/','\$otherRow["$1"]', $otherTable["storeAs"]).";");
								} 
								
							} elseif (setAndTrue($this->destInfo["fields"][$field["to"]],"required")) {
								echo $field["to"]." required (".$field["otherTable"].".".$field["by"].'='.json_encode($v)."), skipping\n";
								return true;
							}
						}
										
						$rv[$lf] = $v;
					}
					continue;
				} elseif (isset($field["to"])) $to = $field["to"];
				
				if ($to !== false) {			
					if (isset($this->destInfo["fields"][$to]["clean"])) {
						switch ($this->destInfo["fields"][$to]["clean"]) {
							case "email":
								$v = array_shift(preg_split('/[\,\;]/',$v));
								break;
						}
					}
				}
				
			
				if (isset($field["concat"]) && $data[$field["concat"]]) {
					$v .= "\n".$data[$field["concat"]];
				}
				
				if (isset($field["key"]) && $v) {
					$rv["relIDs"] = array($this->sourceName.".".$n.':'.$v);
				}
			}
			
			if ($to !== false) {			
				if (isset($this->destInfo["fields"][$to]["prepend"])) {
					$v = $this->destInfo["fields"][$to]["prepend"].$v;
				}
				
				if (isset($this->destInfo["fields"][$to]["append"])) {
					$v .= $this->destInfo["fields"][$to]["append"];
				}
				
				$rv[$to] = $v;
				
				if (isset($this->destInfo["fields"][$to])) {
					$destField = $this->destInfo["fields"][$to];
					if ((setAndTrue($destField,'linkWeight') || setAndTrue($destField,'searchify')) && !setAndTrue($destField,'caseSensitive') && !setAndTrue($destField,'no_S')) {
						$rv["S_".$to] = $this->procSearchField($v,$destField,$data);
					}
				}
			}
		}
		
		
		foreach ($this->destInfo["fields"] as $n=>$field) {
			if (isset($field["dynamic"]) && !setAndTrue($rv,$n)) {
				eval('$rv[$n] = '.preg_replace('/\$([\w\d]+)/','\$rv["$1"]', $field["dynamic"]).";");
				if ($rv[$n]) {
					if ((setAndTrue($field,'linkWeight') || setAndTrue($field,'searchify')) && !setAndTrue($field,'caseSensitive')) {
						$rv["S_".$n] = $this->procSearchField($rv[$n],$field,$data);
					}
				} else {
					unset($rv[$n]);
				}
			}
		}
		return $rv;
	}
	
	function otherTableInfo($table) {
		if (isset($this->tableInfo[$file])) return $this->tableInfo[$file];
		$file = SL_DATA_PATH."/institute/fieldmaps/".$table.".php";
		return $this->tableInfo[$file] = require($file);
	}
	
	private function procSearchField($v,$field,&$data) {
		$sv = trim($v);
		if (isset($field["linkProc"])) {
			switch ($field["linkProc"]) {
				case "firstChar":
					$sv = substr($sv,0,1);
					break;
				
				case "email":
					if (!strpos($sv,"@")) $sv = null;
					break;
					
				case "postal":
					$sv = array_shift(explode("-",$sv));
					break;
				
				case "school":
					$sv = trim(preg_replace('/\(.*?\)/','',$sv));
					$sv = htmlspecialchars_decode($sv);
					$sv = preg_split('/(\@|\-|\,|of)/i',$sv);
					
					$suff = array();
					while (count($sv) > 1) {
						array_unshift($suff,array_pop($sv));
					}
					$suff = implode(" ",$suff);
					
					$sv = $sv[0];
					$sv = explode(" ",searchify($sv));
					
					$cnt = 1 + floor(count($sv) / 2);
					$out = array();
					for ($i = count($sv) - 1; $i >= 0; $i--) {
						array_unshift($out,$sv[$i]);
						if (!in_array($sv[$i],$this->schoolWords)) {
							$cnt--;
							if ($cnt == 0) break;
						}
					}
					//echo implode(" ",$sv)." -> ".implode(" ",$out)."\n";
					$sv = implode(" ",$out).$suff;
					
					if (isset($this->nameTranslate[$this->S($sv)])) {
						return $this->procSearchField($this->nameTranslate[$this->S($sv)],$field,$data);
					}
					break;

				case "districtandtype":
				case "district":
					$sv = trim(preg_replace('/\(.*?\)/','',$sv));
					if ($sv == '' || setAndTrue($data,"schoolType")) break;
					$sv = explode(" ",searchify($sv));
					$svuc = explode(" ",$sv);
					$type = "public";
					if ($pos = array_search("private",$type)) {
						$type = "private";
						array_splice($sv, $pos, 1);
						array_splice($svuc, $pos, 1);
					}
					$data["schoolType"] = $type;
					$sv = implode(" ",$sv);
					if ($field["linkProc"] == "districtandtype") {
						$data["district"] = implode(" ",$svuc);
					}
					break;
			}
		}
		return $this->S($sv);		
	}
	
	public function S($v) {
		$v = explode(' ', strtolower($v));
		foreach ($v as &$k) {
			if ($k == "&" || $k == "&amp;") {
				$k = "and";
			} elseif (isset($GLOBALS["_ABBR"][$k])) $k = $GLOBALS["_ABBR"][$k];
		}
		return searchify(implode(' ',$v),'');
	}

	private function getFirstSearchField($row) {
		if (setAndTrue($row,$this->destInfo["firstSearchField"])) return $this->destInfo["firstSearchField"];
		$best = 0; $fsf = false;
		
		foreach ($this->destInfo["fields"] as $n=>$field) {
			if (isset($field["linkWeight"]) && !setAndTrue($field,'notWhere') && setAndTrue($row,$n) && $field["linkWeight"] > $best) {
				$best = $field["linkWeight"];
				$fsf = $n;
				if (setAndTrue($row,"S_".$n)) $fsf = "S_".$n;
			}
		}
		return $fsf;
	}
	
	private function getSearchFields($row) {
		$fsf = array();
		foreach ($this->destInfo["fields"] as $n=>$field) {
			if (isset($field["linkWeight"]) && !setAndTrue($field,'notWhere') && setAndTrue($row,$n)) {
				$fsf[] = setAndTrue($row,"S_".$n) ? "S_".$n : $n;
			}
		}
		return count($fsf) ? $fsf : false;
	}
	
	private static function parseSF($sf, $row = false) {
		$rv = array();
		foreach ($sf as $n) {
			$rv[] = "`".$n."`=".($row ? json_encode($row[$n]) : ":".$n);
		}		
		return implode(" OR ",$rv);
	}
	
	public function fetchAndMerge($start = false) {
		$row = $this->fetch($start);
		
		if (isset($this->info["phpinc"])) return $row;
		
		if ($row === true) return true;
		if (!$row) return false;
		
		if (isset($this->info["set"])) {
			foreach ($this->info["set"] as $n=>$v) {
				$row[$n] = $v;
			}
		}
		
		if (isset($this->info["where"])) {
			eval('$res = '.$this->info["where"].';');
			if (!$res) return true;
		}

		$sf = $this->getSearchFields($row);
		
		if (!$sf) return true;
		
		echo 'SELECT * FROM '.$this->destInfo["table"].' WHERE '.self::parseSF($sf,$row)."\n";
		$stmt = $this->dest->prepare('SELECT * FROM '.$this->destInfo["table"].' WHERE '.self::parseSF($sf));
		if (!$stmt) {
			echo 'SELECT * FROM '.$this->destInfo["table"].' WHERE '.self::parseSF($sf,$row)."\n";
			//echo $this->destInfo["table"];
			exit();
		}
		
		foreach ($sf as $n) {
			$stmt->bindValue(':'.$n, $row[$n], SQLITE3_TEXT);
		}
		
		$res = $stmt->execute();
		
		if (setAndTrue($this->info,"findClosestMatch") && !self::hasRows($res)) {
			echo "== FINDING CLOSEST MATCH ==\n";
			$searchField = null;
			$bestMatch = 0;
			foreach ($this->destInfo["fields"] as $n=>$field) {
				if (isset($field["linkWeight"]) && !setAndTrue($field,'notWhere') && setAndTrue($row,$n) && $field["linkWeight"] > $bestMatch) {
					$bestMatch = $field["linkWeight"];
					$searchField = $n;
				}
			}
			
			$terms = preg_split('/[^A-Za-z0-9\'\&]+/', $row[$searchField]);
			$where = array();
			foreach ($terms as &$term) {
				$term = $this->S($term);
				if ($term === '') continue;
				$where[] = 'S_'.$searchField.' LIKE "%'.$term.'%"';
			}
			unset($term);
			echo $searchField." similar to '".$row[$searchField]."'\n";
			
			if (($res = $this->dest->query('SELECT * FROM '.$this->destInfo["table"].' WHERE '.implode(' OR ', $where))) && self::hasRows($res)) {
				$bestScore = 0;
				$bestRow = null;
				while ($searchRow = $this->procFetch($res->fetchArray(SQLITE3_ASSOC))) {
					$score = $this->compare($terms, $searchRow[$searchField]);
					if ($score > $bestScore) {
						$bestRow = $searchRow;
						$bestScore = $score;
					}
				}
				if ($bestScore > 0.6) {
					echo "\tFOUND: ".$bestRow[$searchField].": ".$bestScore."\n\n";
					$res = $this->dest->query('SELECT * FROM '.$this->destInfo["table"].' WHERE S_'.$searchField.'="'.$bestRow['S_'.$searchField].'"');
				} else {
					echo "\tNo match found.\n\n";
				}
			} else {
				echo "\tNo match found.\n\n";
			}
		}
		
		$id = null;
		if ($res && self::hasRows($res)) {
			$bestMatch = null;
			$bestMatchScore = 0;

			while ($searchRow = $this->procFetch($res->fetchArray(SQLITE3_ASSOC))) {			
				$dbg = array();
				$match = 0;
				$linkGroupsFound = array();
				foreach ($searchRow as $n=>$v) {
					
					if (isset($this->destInfo["fields"][$n])) {
						$destField = $this->destInfo["fields"][$n];
						$lg = isset($destField["linkGroup"]) ? $destField["linkGroup"] : false;
						if (setAndTrue($destField,'linkWeight') || setAndTrue($destField,'mismatchWeight')) {
							if (!isset($destField["linkWeight"])) $destField["linkWeight"] = 0;
							
							if ($lg && in_array($lg,$linkGroupsFound)) continue;
							
							if (setAndTrue($destField,'countIfMissing') && !setAndTrue($searchRow,$n)) {
								$dbg[] = "(".$n.")";
								$match += $destField["linkWeight"];
								if ($lg) $linkGroupsFound[] = $lg;
							} elseif (setAndTrue($destField,'caseSensitive')) {
								if ($row[$n] && $searchRow[$n] && $row[$n] == $searchRow[$n]) {
									$dbg[] = $n;
									$match += $destField["linkWeight"];
									if ($lg) $linkGroupsFound[] = $lg;
								} elseif (setAndTrue($destField,'mismatchWeight')) {
									$match -= $destField['mismatchWeight'];
								}
							} else {
								if ($row["S_".$n] && $searchRow["S_".$n] && $row["S_".$n] == $searchRow["S_".$n]) {
									$dbg[] = $n;
									$match += $destField["linkWeight"];
									if ($lg) $linkGroupsFound[] = $lg;
								} elseif (setAndTrue($destField,'mismatchWeight')) {
									$match -= $destField['mismatchWeight'];
								}
							}
						}
					}
				}

				if (count($dbg)) echo implode(", ",$dbg).": ".$match." >= ".$this->destInfo["linkThresh"]."\n";
				
				if ($match >= $this->destInfo["linkThresh"]) {
					if (setAndTrue($this->info,"applyToAllMatches")) {
						echo $searchRow["id"]."\n";
						
						self::showDiff($searchRow,$row,$row);
						
						$this->update($row,array("id"=>$searchRow["id"]), $searchRow);

					} elseif ($match > $bestMatchScore) {
						$bestMatchScore = $match;
						$bestMatch = $searchRow;
						$old = $searchRow;
						$id = $searchRow["id"];
					}
					echo "\n";
				} elseif ($match) echo implode(", ",$dbg).": ".$match." >= ".$this->destInfo["linkThresh"]."\n";
			}
		}
		
		
		if (setAndTrue($this->info,"applyToAllMatches")) return true;
			
		if ($id) {
			foreach ($row as $n=>$v) {
				if ($v) {
					switch ($n) {
						case "relIDs":
							foreach ($v as $rid) {
								if (!in_array($rid,$bestMatch["relIDs"])) $bestMatch["relIDs"][] = $rid;
							}
							break;
						
						default:
							$bestMatch[$n] = $this->mergeData($bestMatch[$n],$v);
							break;
					}
				}
			}
			
			
		
			if (self::showDiff($old,$row,$bestMatch)) {
				if ($this->update($bestMatch,array("id"=>$id),$old) === false) return null;
				echo "\n\n";
			} else {
				echo "Nothing to update for ".$id."\n";
			}
			
		} else {
			//Insert
			if (!setAndTrue($this->info,'noInsert')) {
				if (($res = $this->insert($row)) === false) return null;
				echo "INSERT: (".$res.") ".json_encode($row)."\n";
			}
			
		}
		return true;		
	}
	
	private function compare($t1,$t2) {
		$count = max(count(explode(" ",$t1)),count(explode(" ",$t2)));
		$t1 = $this->prepStringForCompare($t1);
		$t2 = $this->prepStringForCompare($t2);
		
		//echo "\t'".implode(" ",$t1)."' vs '".implode(" ",$t2)."'\n";
		
		$tot = 0;
		$lastPos = -1;
		foreach ($t1 as $t1s) {
			if (($pos = array_search($t1s, $t2)) !== false) {
				$posDist = abs(($lastPos + 1) - $pos);
				
				$tot += (max(0,4 - $posDist) / 4);
				$lastPos = $pos;
				$t2[$pos] = null;
			} else {
				$best = false;
				$bestDist = 3;
				$bestPos = -1;
				foreach ($t2 as $pos=>$t2s) {
					if ($t2s === null) continue;
					$dist = levenshtein($t1s, $t2s);
					if ($dist < $bestDist) {
						$bestDist = $dist;
						$best = $t2s;
						$bestPos = $pos;
					}
				}
				if ($best) {
					$t2[$bestPos] = null;
					//echo "\t\t".$best." ".(3 - $bestDist)." ".(((3 - $bestDist) / 3) * (max(0,4 - abs(($lastPos + 1) - $bestPos)) / 4))."\n";
					$tot += ((3 - $bestDist) / 3) * (max(0,4 - abs(($lastPos + 1) - $bestPos)) / 4);
					$lastPos = $bestPos;
				}
			}
		}
		return $tot / $count;
	}
	
	private function prepStringForCompare($t) {
		if (!is_array($t)) $t = preg_split('/[^A-Za-z0-9\'\&]+/',$t);
		$rv = array();
		foreach ($t as &$s) {
			$s = $this->S($s);
			if ($s === '') continue;
			$rv[] = $s; 
		}
		return $rv;
	}
	
	public function procFetch($row) {
		if (!$row) return $row;
		if (isset($row["extra"])) {
			$extra = json_decode($row["extra"],true);
			if (is_array($extra)) {
				foreach ($extra as $n=>$v) {
					$row[$n] = $v;
				}
			}
			unset($row["extra"]);
		}
		$row["relIDs"] = setAndTrue($row,"relIDs") ? explode(",",$row["relIDs"]) : array();
		return $row;
	}
	
	public function insert($cols) {		
		if (isset($cols["relIDs"])) $cols["relIDs"] = implode(",",$cols["relIDs"]);
		$keys = array();
		$vals = array();
		
		$extra = array();
		if (isset($cols["id"])) unset($cols["id"]);
		
		foreach ($cols as $key=>$col) {
			$uk = substr($key,0,2) == "S_" ? substr($key,2) : $key;
			if (isset($this->destInfo["fields"][$uk])) {
				$keys[] = '`'.$key.'`';
				$vals[] = ":".$key;
			} else {
				$extra[$key] = $col;
			}
		}
		
		$cols["_updated"] = time();
		$keys[] = '`_updated`';
		$vals[] = ":_updated";
		
		$cols["extra"] = json_encode($extra);
		$keys[] = '`extra`';
		$vals[] = ":extra";

		//echo 'INSERT INTO '.$this->destInfo["table"].' ('.implode(", ",$keys).') VALUES ('.implode(", ",$vals).')';
	
		if ($stmt = $this->dest->prepare('INSERT INTO '.$this->destInfo["table"].' ('.implode(", ",$keys).') VALUES ('.implode(", ",$vals).')')) {
			
			foreach ($cols as $key=>$col) {
				$stmt->bindValue(':'.$key, $col, isset($this->destInfo["fields"][$key]["SQLITE_TYPE"]) ? $this->destInfo["fields"][$key]["SQLITE_TYPE"] : SQLITE3_TEXT);
			}
			
			if ($stmt->execute()) return $this->dest->lastInsertRowID();
		}
		echo 'INSERT INTO '.$this->destInfo["table"].' ('.implode(", ",$keys).') VALUES ('.implode(", ",$vals).')'."\n";
		echo $this->dest->lastErrorMsg."\n";
		return false;
	}
	
	public function update($cols, $where, $oldData = false) {
		if (isset($cols["relIDs"])) $cols["relIDs"] = implode(",",$cols["relIDs"]);
				
		$extra = array();
		foreach ($cols as $key=>$col) {
			if ($key == "id") {
				unset($cols[$key]);
				continue;
			}
			
			$uk = substr($key,0,2) == "S_" ? substr($key,2) : $key;
			if (!isset($this->destInfo["fields"][$uk])) {
				$extra[$key] = $col;
				unset($cols[$key]);
			}			
		}
		
		if (count($extra)) {
			foreach ($oldData as $key=>$col) {
				if ($key == "id") continue;
				$uk = substr($key,0,2) == "S_" ? substr($key,2) : $key;
				if (!isset($this->destInfo["fields"][$uk]) && !setAndTrue($extra,$key)) {
					$extra[$key] = $col;
				}			
			}
			$cols["extra"] = json_encode($extra);
		}
		
		$cols["_updated"] = time();
		
		$stmt = $this->dest->prepare('UPDATE `'.$this->destInfo["table"].'` SET '.$this->queryVars($cols).' WHERE '.$this->queryVars($where,' AND ', 'w_'));
		$query = 'UPDATE `'.$this->destInfo["table"].'` SET '.$this->queryVars($cols,", ","",true).' WHERE '.$this->queryVars($where,' AND ', 'w_',true);
		if (!$stmt) {
			
			echo $query."\n";
			if ($res = $this->dest->query($query)) return $res;
			echo $this->dest->lastErrorMsg."\n"; exit();	
		
		}

		foreach ($cols as $n=>$v) {
			$stmt->bindValue(':'.$n, $v, isset($this->destInfo["fields"][$n]["SQLITE_TYPE"]) ? $this->destInfo["fields"][$n]["SQLITE_TYPE"] : SQLITE3_TEXT);
		}
		foreach ($where as $n=>$v) {
			$stmt->bindValue(':w_'.$n, $v, isset($this->destInfo["fields"][$n]["SQLITE_TYPE"]) ? $this->destInfo["fields"][$n]["SQLITE_TYPE"] : SQLITE3_TEXT);
		}
		if ($res = $stmt->execute()) return $res;

		echo $query."\n";
		echo $this->dest->lastErrorNo."\n";
		echo $this->dest->lastErrorMsg."\n"; 
		return false;
	}
	
	private function queryVars($cols, $delim = ", ", $prefix = "", $values = false) {
		$rv = array();
		foreach ($cols as $n=>$v) {
			if ($values && $v === null) continue;
			$rv[] = "`$n`=".($values ? "'".SQLite3::escapeString($v)."'" : ":".$prefix."$n");
		}
		return implode($delim, $rv);
	}
	
	public static function hasRows($res) {
		if ($res->fetchArray()) {
			$res->reset();
			return true;
		}
		return false;
	}
	
	private function mergeData($a,$b) {
		$sa = $this->S($a);
		$sb = $this->S($b);
		
		if ($sa && !$sb) return $a;
		if ($sb && !$sa) return $b;
		
		/*$l = min(strlen($sa),strlen($sb));
		if (substr($sa,0,$l) == substr($sb,0,$l)) {
			return strlen($a) > strlen($b) ? $a : $b;
		}*/
		return $b;
	}
	
	private static function showDiff($old,$new,$comb) {
		//Update
		$keys = array_keys($old);
		$k2 = array_keys($new);
		foreach ($k2 as $k) {
			if (!in_array($k,$keys)) $keys[] = $k;
		}
	
		$updated = false;
		//ob_start();
		foreach ($keys as $k) {
			if ((isset($old[$k]) ? $old[$k] : null) != (isset($comb[$k]) ? $comb[$k] : null) && isset($comb[$k])) {
				$updated = true;
				echo str_pad($k.":",24)." ".str_pad(isset($old[$k]) ? substr(json_encode($old[$k]),0,60) : '',62)." -> ".(isset($comb[$k]) ? json_encode($comb[$k]) : '')."\n";
			} elseif (setAndTrue($old,$k)) {
				echo str_pad($k.":",24)." ".json_encode($old[$k])."\n";				
			}
		}
		/*if ($updated) {
			ob_flush();
		} else {
			$c = ob_get_clean();
			echo $c;
		}*/
		return $updated;
	}
	
	private static function calcGrade($grade, $dbGrades) {
		if ($dbGrades) {
			$dbGrades = explode(":",$dbGrades);
			if (setAndTrue($dbGrades,$grade - 4)) return true;
		}
		return false;
	}
	
	private static function fullName(&$data, $name) {
		$name = explode(" ",trim($name));
		$data["nameLast"] = array_pop($name);
		$data["nameFirst"] = implode(" ",$name);
		return false;
	}
}

class DataSourceCSV {
	private $labels;
	private $file;
	private $fp;
	private $i = 0;
	private $map = array();
	
	
	public function __construct($file) {
		if ($this->fp = fopen($file,'r')) {
			$this->file = $file;
			$this->labels = self::clean(fgetcsv($this->fp));
			$this->map[] = ftell($this->fp);
		} else {
			echo "Couldn't open ".$file."\n";
		}
	}
	
	public function __destruct() {
		if ($this->fp) {
			fclose($this->fp);
			$this->fp = false;
		}
	}
	public function reset() {
		fseek($this->fp,$this->map[0]);
		$this->i = 0;
	}
	
	public function createMap($to) {
		fseek($this->fp,$this->map[0]);
		for ($i = 1; $i <= $to; $i++) {
			fgetcsv($this->fp);
			$this->map[$i] = ftell($this->fp);
			if (feof($this->fp)) break;
		}
	}
	
	public function fetch($i = false) {
		if ($i === false) {
			$this->map[$this->i] = ftell($this->fp);
		} else {	
			if (!isset($this->map[$i])) $this->createMap($i);
			if (!isset($this->map[$i])) return null;
			$this->i = $i;
			fseek($this->fp,$this->map[$i]);
		}
		if ($line = self::clean(fgetcsv($this->fp))) {
			$this->i ++;
			$this->map[$this->i] = ftell($this->fp);
			return array_combine($this->labels,$line);
		}
		return null;
	}
	
	private static function clean($line) {
		foreach ($line as &$v) {
			$v = trim($v);
		}
		return $line;
	}
}

class DataSourceMysql {
	private $source;
	private $h;
	private $i = 0;
	
	public function __construct($source) {
		$this->source = $source;
		$this->h = new mysqli($source["host"], $source["user"], $source["password"], $source["db"]);
		if ($this->h->connect_errno) {
			echo "Failed to connect to MySQL: (" . $this->h->connect_errno . ") " . $this->h->connect_error;
		}
		echo $this->h->host_info . "\n";
	}
}

class DataSourceAPI {
	const BLOCK_SIZE = 100;
	
	private $source;
	private $i = 0;
	private $cache = array();
	
	public function __construct($source) {
		$this->source = $source;
	}
	
	public function fetch($start = false) {
		if ($start == false) $start = $this->i;
		
		$rv = array();	

		if (!isset($this->cache[$start])) {
			$qStart = floor($start / self::BLOCK_SIZE) * self::BLOCK_SIZE;
			$qLen = self::BLOCK_SIZE;
			
			if ($res = $this->request($qStart,$qLen)) {
				$this->cache = array();
				for ($i = 0; $i < count($res); $i ++) {
					$this->cache[$qStart + $i] = $res[$i];
				}
			} else return null;
		}
		$rv = $this->cache[$start];
		
		$this->i++;
		return $rv;
	}
	
	public function request($start, $len) {
		$url = str_replace('[limit]',$start.'-'.$len,$this->source["api"]);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_REFERER, "YP-API");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$raw = curl_exec($ch);
		curl_close($ch);
		
		
		if ($res = json_decode($raw,true)) {
			return $res;
		}
		return null;
	}
}

class DataSourcePHP {
	private $source;
	private $ds;
	
	public function __construct($source, $ds) {
		$this->source = $source;
		$this->ds = $ds;
	}
	
	public function fetch($start) {
		return require($this->source);
	}
}

