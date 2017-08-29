<?php

class slSearchIndexer {
	private $dir;
	private $indexFile;
	public $indexLiveDays = 365;
	private $tagTree;
	private $levenshteinCalls = 0;
	private $densityIgnore = array(
		"the","and","you","for","our","your","with","that","are","will",
		"all","have","their","this","from","was","not","what","they",
		"how","when","has","had","who","but","while","than","where",
		"then","why","at"
	);
	private $searchStringFile = "";
	private $searchStrings = false;
	private $searchStringsUpdated = false;
	private $searchStringMap = array("query","updated","count","strength");
	
	function __construct($dir = false) {
		$this->dir = $dir;
		$this->indexFile = $this->dir."/index";
		makePath($dir);
	}
	
	function __destruct() {
		if ($this->searchStringsUpdated) {
			$dropDay = floor(time() / 86400) - 90;
			foreach ($this->searchStrings as $n=>$o) {
				if (isset($o[1]) && isset($o["count"]) && $o[1] < $dropDay && $o["count"] <= 2) {
					unset($this->searchStrings[$n]);
				}
			}
			file_put_contents($this->searchStringFile,json_encode($this->searchStrings));
			$this->searchStringsUpdated = false;
		}
	}
	
	function indexFromHTML($name,$html,$extra = array()) {
		$text = trim(preg_replace("/[\s]+/"," ",$this->htmlToText($html)));
		$extra["text"] = $text;
		$this->index($name,$text,$extra);
	}
	
	function index($name,$text,$extra = null) {
		$t = array();
		if ($extra) {
			foreach ($extra as $v) {
				$t[] = $v;
			}
		}
		
		$file = $this->dir."/".safeFile($name,true);
		file_put_contents(
			$file,
			searchify($name." ".$text.($t?" ".implode(" ",$t):""))."\n".
			$name."\n".
			time()."\n".
			json_encode($extra)."\n"
		);
		if (!file_exists($this->indexFile) || time() > filemtime($this->indexFile) + 60) $this->createIndex();
	}
	
	function createIndex() {
		$termDensity = array();

		if ($fp = fopen($this->indexFile,"w")) {
			if ($dp = opendir($this->dir)) {
				while ($file = readdir($dp)) {
					$path = $this->dir."/".$file;
					if (is_file($path) && $file != "index" && $file != "_search_strings" && substr($file,0,1) != ".") {
						if (filemtime($path) > time() - 86400 * $this->indexLiveDays) {
							$c = file_get_contents($path);
							$terms = explode("\n",$c);
							$terms = explode(" ",$terms[0]);
							for ($tlen = 1; $tlen <= 3; $tlen++) {
								$len = count($terms) - $tlen;
								for ($i = 0; $i < $len; $i++) {
									$t = array();
									for ($j = 0; $j < $tlen; $j++) {
										$t[] = $terms[$i+$j];
									}
									$term = implode(" ",$t);		
									if (strlen($term) >= 3 && !in_array($term,$this->densityIgnore)) {
										if (!isset($termDensity[$term])) $termDensity[$term] = 0;
										$termDensity[$term] += $tlen * 2;										
									}
								}
							}
							fwrite($fp,$c);
						} else {
							unlink($path);
						}
					}
				}
				closedir($dp);
			}
			
			foreach ($termDensity as $term=>$d) {
				if ($d < 20) unset($termDensity[$term]);
			}
			arsort($termDensity);
			$tDensOut = array();
			
			$cnt = 0;
			foreach ($termDensity as $term=>$d) {
				$tDensOut[$term] = $d;
				$cnt++;
				if ($cnt > 1000) break;
			}
			
			fwrite($fp,"_TERM_DENSITY:".json_encode($tDensOut));
			fclose($fp);
		}
	}
	
	function sort($a,$b) {
		return $b["relevance"] - $a["relevance"];
	}
	
	function loadSearchStrings() {
		if ($this->searchStrings) return;
		$this->searchStringFile = $this->dir."/_search_strings";
		$this->searchStrings = is_file($this->searchStringFile) ? json_decode(file_get_contents($this->searchStringFile),true) : array();
	}
	
	function addSearchString($terms) {
		if (strlen($terms) > 48) return;
		$this->loadSearchStrings();
		$bs = broadSearchify($terms);
		
		if (!isset($this->searchStrings[$bs])) {
			$this->searchStrings[$bs] = array(
				$terms,
				floor(time() / 86400),
				0,
				0
			);			
		}
		$this->searchStringsUpdated = true;
		$this->searchStrings[$bs][2] ++;
	}
	
	function updateSearchString($terms,$n,$v) {
		if (strlen($terms) > 48) return;
		$this->loadSearchStrings();
		$bs = broadSearchify($terms);
		$this->searchStrings[$bs][array_search($n,$this->searchStringMap)] = $v;
		$this->searchStringsUpdated = true;
	}
	
	function search($terms,$noSuggestions = false) {
		$this->createIndex();
		if (!trim($terms)) return array();
		
		$origQuery = $query = $terms;
		require_once(SL_INCLUDE_PATH."/class.slCache.php");

		$cache = new slCache("search-".broadSearchify($terms));
		
		if ($cache->isNewerThan(time() - 3600)) {
			$this->addSearchString($query);
			return $cache->get();
		} else {
			$terms = explode(" ",searchify($terms));
			$termsCnt = count($terms);
			
			$res = array("query"=>$query,"results"=>array(),"suggestion"=>false,"suggestionResults"=>false,"resultStrength"=>0);
			
			if ($fp = fopen($this->indexFile,"r")) {
				while (!feof($fp)) {
					$index = fgets($fp,0x7FFFFF);

					if (substr($index,0,14) == '_TERM_DENSITY:') {
						if (!$noSuggestions) {
							$termDensity = json_decode(substr($index,14),true);
							
							$suggestion = array();
							$foundSuggestion = false;
							
							for ($i = 0; $i < count($terms); $i++) {
								$suggestion[$i] = array($terms[$i],0);
							}
							

							for ($tlen = 1; $tlen <= $termsCnt; $tlen ++) {
								$len = max(1,count($terms) - $tlen);
								
								for ($i = 0; $i < $len; $i++) {
									$t = array();
									for ($j = 0; $j < $len; $j++) {
										if (isset($terms[$i+$j])) $t[] = $terms[$i+$j];
									}
									$sTerm = implode(" ",$t);
									
									if (!isset($termDensity[$sTerm])) {
										foreach ($termDensity as $term=>$density) {
											$dist = $this->quickLevenshtein($term,$sTerm);
											if ($dist <= 2) {
												$term = explode(" ",$term);
												
												for ($j = 0; $j < $len; $j++) {
													$n = $i + $j;
													if ($term[$n] == $t[$j]) {
														$suggestion[$n] = array($term[$j],$density);
														continue;
													}
													$dist = $this->quickLevenshtein($term[$n],$t[$j]);
													if ($density / $dist > $suggestion[$n][1]) {
														$suggestion[$n] = array($term[$j],$density / $dist);
														$foundSuggestion = true;
													}
												}
											}
											
										}
									}
								}
							}

							if ($foundSuggestion) {
								$sug = array();
								foreach ($suggestion as $s) {
									$sug[] = $s[0];
								}
								$res["suggestion"] = implode(" ",$sug);
								$sugRes = $this->search($res["suggestion"],true);

								if ($sugRes["results"]) {
									$query = $res["suggestion"];
									
									$res["suggestionResults"] = $sugRes["results"];
								} else {
									$res["suggestion"] = false;
								}
							}
							$this->addSearchString($query);
							$this->updateSearchString($query,"updated",time());
						}
					} else {
						$lastPos = 0; $pos = 0; $density = 0;
						$i = 0; $termDist = array();
						$indexLen = strlen($index);
						
						if ($terms[$i]) {
							while (($pos = strpos($index,$terms[$i],$pos)) !== false) {
								if ($i > 0) {
									$termDist[$density][1] -= ((($pos - $lastPos) - strlen($terms[$i - 1]) - 1) / $indexLen);
								} else {
									$termDist[$density] = array(1 - ($pos / $indexLen),1);
								}
								$lastPos = $pos;
								$pos += strlen($terms[$i]);
								$i++;
								if ($i >= $termsCnt) {
									$i = 0;
									$density ++;
								}
							}
						}						


						$name = trim(fgets($fp,0x7FFFFF));
							
						$dist = $this->quickLevenshtein($origQuery,trim($name,"/"));
						if ($dist <= 2) $res["pageSuggestion"] = $name;
						
						if ($density > 0) {
							
							//$startTs = microtime(true);
											
							$ts = trim(fgets($fp,0x7FFFFF));
							$extra = json_decode(fgets($fp,0x7FFFFF),true);
							
							if ((int)$ts > time() - 86400 * 14) {
								$bestMatch = 0;
								$bestMatchPos = 1;
								foreach ($termDist as $d) {
									if ($d[0] > $bestMatch) {
										$bestMatch = $d[0];
										$bestMatchPos = $d[1];
									}
								}
								
								$bestMatchPos = pow($bestMatchPos,3);
								
								$data = array(
									"name"=>$name,
									"updated"=>(int)$ts,
									"density"=>$density,
									"bestMatch"=>$bestMatch,
									"bestMatchPos"=>$bestMatchPos,
									"relevance"=>round(($bestMatch + $bestMatchPos + min(20,$density) / 40) * 1000)
								);
								
								$res["resultStrength"] += $data["relevance"];
								if (is_array($extra)) $data = array_merge($data,$extra);
								
								//TODO: The highlight function is WAAAY too slow
								/*if (isset($data["text"])) {
									$data["highlighted"] = $this->highlight($data["text"],$query);
								}*/
								
								$res["results"][] = $data;
							}
							unset($name);
							unset($ts);
							unset($extra);
							
							//echo "$name TOOK ".round((microtime(true) - $startTs) * 1000)."MS<br />";
						} else {
							fgets($fp,0x7FFFFF);
							fgets($fp,0x7FFFFF);
						}
					}
					unset($index);		
				}
			}
			
			$this->updateSearchString($query,"strength",$res["resultStrength"]);
			
			usort($res["results"],array($this, "sort"));
			$cache->set($res);
			return $res;
		}			
	}
	
	function highlight($text,$query) {
		$sText = searchify($text);
				
		$sQuery = explode(" ",searchify($query));
		$hlPos = array();
		foreach ($sQuery as $t) {
			$pos = 0;
			while (($pos = strpos($sText,$t,$pos)) !== false) {
				$hlPos[$pos] = 0;
				$hlPos[$pos+strlen($t)] = 1;
				$pos += strlen($t);
			}
		}
		
		$sPos = $pos = 0;
		$out = "";
		$len = strlen($text);
		$lastShown = false;
		
		while ($pos < $len) {
			if (substr($sText,$sPos,1) === preg_replace('/[^A-Za-z\d]+/',' ',strtolower(charNormalize(mb_substr($text,$pos,1,'UTF-8'))))) {
				if (isset($hlPos[$sPos])) {
					if ($hlPos[$sPos] == 0) {
						$out .= "<span class=\"highlight\">";
					} else {
						$out .= "</span>";
					}
				}
				$sPos ++;
			}
			
			$dist = 2000;
			foreach ($hlPos as $p=>$o) {
				$dist = min($dist,abs($p - $sPos));
			}
			if ($dist < 30) {
				if (!$lastShown) $out .= " ... ";
				$out .= mb_substr($text,$pos,1,'UTF-8');
				$lastShown = true;
			} else {
				if ($lastShown) $out .= " ... ";
				$lastShown = false;
			}
			$pos ++;
		}
		
		$out = explode(" | ",str_replace("...  ...","...",$out));
		
		$rv = array();
		foreach ($out as $l) {
			if (strlen(strip_tags(implode("",$rv)).$l) > 200) break;
			if (strpos($l,'<') !== false) $rv[] = $l;
			if (count($rv) > 4) break;
		}
		return implode("<br />",$rv);
	}
	
	function htmlToText($html) {
		$nonClosingTags = array("img","meta","!doctype","br","hr");
		$rv = array();
		
		$matchers = array(
			array("/title=(\'|\")([^\'\"]+)(\'|\")/i",2),
			array("/alt=(\'|\")([^\'\"]+)(\'|\")/i",2)
		);
		
		$pos = $end = 0;
		
		$html = str_ireplace(
			array("</p>","<br>","<br />","</div>","</li>","</h1>","</h2>","</h3>","</h4>","</h5>","</h6>","</h7>"),
			"|",
			$html
		);
		
		//strip comments
		while (($pos = strpos($html,"<!--",$pos)) !== false) {
			if (($end = strpos($html,"-->",$pos)) !== false) {
				$html = substr($html, 0, $pos).substr($html, $end + 3);				
			} else $pos += 4;
		}				
		
		$this->tagTree = array();
		$html = explode("<",$html);
		foreach ($html as $o) {
			$t = explode(">",$o,2);
			list($tag,$content) = count($t) == 1 ? array("",$t[0]) : $t;
			$tagName = array_shift(explode(" ",$tag));
			
			if ($tagName) {
				$closeTag = false;
				if (substr($tag,-1) != "/" && !in_array(strtolower($tagName),$nonClosingTags)) {
					if (substr($tagName,0,1) == "/") {
						$tagName = substr($tagName,1);
						$closeTag = true;
					}
					
					if ($closeTag) {
						while (count($this->tagTree) && array_pop($this->tagTree) != $tagName) {}
					} else {
						$this->tagTree[] = $tagName;
					}
				}
				foreach ($matchers as $matcher) {
					$match = array();
					if (preg_match_all($matcher[0],$tag,$match)) {
						foreach ($match[$matcher[1]] as $text) {
							$rv[] = $text;
						}
					}
				}
			}
			if (!$this->inTag("style") && !$this->inTag("script")) {
				$text = trim(html_entity_decode($content,NULL,"UTF-8"));
				if ($text) $rv[] = $text;
			}
		}
		return preg_replace('/^[\s\xA0\|]+|[\s\xA0\|]+$/','',preg_replace("/(\|\s*)+/",' | ',implode(" ",$rv)));
	}
	
	function inTag($tag) {
		return in_array($tag,$this->tagTree);
	}
	
	function quickLevenshtein($a,$b) {
		if (abs(strlen($a) - strlen($b)) > 1) return 20;
			
		$lDiff = 0;
		$len = min(strlen($a),strlen($b));
		$lDiv2 = $len >> 1;
		for ($i = 0; $i < $len; $i++) {
			if (substr($a,$i,1) != substr($b,$i,1)) {
				$lDiff++;
				if ($lDiff > $lDiv2) return 20;
			}
		}
		
		$this->levenshteinCalls ++;
		return levenshtein($a,$b);
	}	
}


