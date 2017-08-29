<?php

class blog extends slWebModule {
	private $feed;
	private $filter; 
	private $data;
	private $entries;
	private $i;
	
	public function init($feed, $filter = array()) {
		$this->setFeed($feed, $filter);
		$this->reset();
	}
	
	public function setFeed($feed, $filter = array()) {
		$this->entries = array();
		$this->data = self::parseXML($feed);
		
		foreach ($this->data["entry"] as $entry) {
			if (self::filterMatch($entry,$filter)) {
				$this->entries[] = self::parseEntry($entry);
			}
		}	
		$this->refresh();
	}
	
	private function entrySort($a,$b) {
		return $b["published"] - $a["published"];
	}
	
	public function refresh() {
		usort($this->entries,array($this,"entrySort"));		
	}
	
	public function includeWordpress($params = array()) {
		$params = array_merge(array(
			"db"=>false,
			"prefix"=>"wp_"
		),$params);
		
		if (!isset($params["postsTable"])) $params["postsTable"] = $params["prefix"]."posts";
		
		if ($res = $GLOBALS["slCore"]->db->select("db/".$params["postsTable"],array("post_status"=>"publish","post_type"=>"post"))) {
			while ($post = $res->fetch()) {
				$postFile = SL_WEB_PATH."/content/blog/".$params["postsTable"]."-".safeName($post["post_title"]).".html";
				if (is_file($postFile)) {
					$p = explode("\n",file_get_contents($postFile),2);
					$entry = json_decode(trim(getStringBetween('<!--','-->',$p[0])),true);
					if (setAndTrue($entry,"hide")) continue;
					$entry["content"] = $p[1];
					$this->entries[] = $entry;
				} else {
					$entry = self::parseWPEntry($post);
					$this->entries[] = $entry;
					$content = $entry["content"];
					unset($entry["content"]);
					file_put_contents($postFile,urldecode("%EF%BB%BF")."<!-- ".json_encode($entry)." -->\n".$content);
				}
			}
		}		
		$this->refresh();
	}
	
	public function reset() {
		$this->i = 0;
	}
	
	public function fetchEntry($ref = false) {
		if ($ref) {
			$cacheFile = SL_DATA_PATH."/tmp/blog-".md5($ref);
			if (is_file($cacheFile)) return new blogEntry(json_decode(file_get_contents($cacheFile),true));
			
			foreach ($this->entries as $i=>$entry) {
				if (self::getRef($entry) == $ref) {
					file_put_contents($cacheFile,json_encode($this->entries[$i]));
					return new blogEntry($this->entries[$i]);
				}
			}
			return false;
		}
		if ($this->i >= count($this->entries)) return false;
		
		$entry = $this->entries[$this->i++];
		$cacheFile = SL_DATA_PATH."/tmp/blog-".md5(self::getRef($entry));
		if ($entry["updated"] > filemtime($cacheFile)) {
			unlink($cacheFile);			
		}
		return new blogEntry($entry);
	}
	
	public function getAlias() {
		return "blog";
	}
	
	private static function parseEntry($entry) {
		return self::parseXMLArray($entry,array(
			"link"=>array("_P"=>"mult"),
			"published"=>array("_P"=>"ts"),
			"updated"=>array("_P"=>"ts"),
			"title"=>array("_P"=>"html"),
			"content"=>array("_P"=>"html")
		));
	}
	
	public static function parseWPEntry($entry) {
		return array(
			"id"=>"wordpress.".$_SERVER["SERVER_NAME"].".".$entry["ID"],
			"published"=>strtotime($entry["post_date_gmt"]." GMT"),
			"updated"=>strtotime($entry["post_modified_gmt"]." GMT"),
			"category"=>null, //TODO not sure how to handle this
			"title"=>iconv("cp1252", "utf-8",$entry["post_title"]),
			"content"=>iconv("cp1252", "utf-8",self::parseWPContent($entry["post_content"])),
			"link"=>array(), //TODO: array of links
			"author"=>null //TODO: pull from user (post_author)
		);
	}
	
	public static function parseWPContent($content) {
		return $content;
	}
	
	public static function getRef($data) {
		return safeName($data["title"]);
	}
	
	private static function parseXMLArray($o,$def = array(), $l = false) {
		$rv = array();
		
		if (isset($o["_VAL"]) && count($o) == 1) return $o["_VAL"];
		
		foreach ($o as $n=>$v) {			
			if ($n == "_VAL" && $v === "") continue;
			
			$parse = isset($def[$n]["_P"]) ? $def[$n]["_P"] : false;
			
			if (is_array($v)) {
				if (count($v) == 1 && $parse != "mult") {
					$rv[$n] = self::parse(self::parseXMLArray($v[0],isset($def[$n]) ? $def[$n] : array(), true), $parse);
				} else {
					$rv[$n] = array();
					foreach ($v as $v2) {
						$rv[$n][] = self::parse(self::parseXMLArray($v2,isset($def[$n]) ? $def[$n] : array(), true), $parse);
					}
				}
			} else {
				$rv[$n] = self::parse($v,$parse);
			}
		}
		return $rv;
	}
	
	private static function parse($v,$parse) {
		switch ($parse) {
			case "ts":
				return strtotime($v);
			
			case "html":
				if (!isset($v["_VAL"])) return "";
				if ($v["type"] == "text") return str_replace("\n","<br />",htmlspecialchars($v["_VAL"]));
				return $v["_VAL"];
			
			case "text":
				if (!isset($v["_VAL"])) return "";
				//if ($v["type"] == "html") return ""; //TODO: convert to text
				return $v["_VAL"];
		}
		return $v;
	}
	
	private static function filterMatch($o,$filter) {
		foreach ($filter as $n=>$v) {
			if (isset($o[$n])) {
				foreach ($o[$n] as $cmp) {
					if ($cmp["_VAL"] == $v) continue 2;
					if (isset($cmp["term"]) && $cmp["term"] == $v) continue 2;
				}
			}
			return false;
		}
		return true;
	}
	
	public static function parseXML($xml) {
		if (is_string($xml)) $xml = simplexml_load_file($xml);
		
		$rv = array();
		foreach ($xml as $n=>$v) {
			if (!isset($rv[$n])) $rv[$n] = array();
			$o = array("_VAL"=>(string)$v);
			
			foreach ($v->attributes() as $an=>$av) {
				$o[$an] = (string)$av;
			}
			
			if ($v->children()) {
				$children = self::parseXML($v->children());
				foreach ($children as $cn=>$child) {
					$o[$cn] = $child;
				}
			}
			
			$rv[$n][] = $o;
		}
		return $rv;
	}
}

class blogEntry {
	private $data;
	
	public function __construct($data) {
		$this->data = $data;
	}
	
	public function get($n,$def = "") {
		return isset($this->data[$n]) ? $this->data[$n] : $def;
	}
	
	public function getRef() {
		return blog::getRef($this->data);
	}
	
	public function getImages($limit = false) {
		$rv = array();
		libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$doc->loadHTML('<?xml encoding="UTF-8">'.$this->data["content"]);
		$els = $doc->getElementsByTagName('img');
		foreach ($els as $el) {
			$img = array();
			foreach ($el->attributes as $n=>$v) {
				$img[$n] = $v->value;
			}
			if (!isset($img["title"])) {
				$title = explode(".",array_pop(explode("/",$img["src"])));
				array_pop($title);
				$img["title"] = urldecode(str_replace("_"," ",implode(".",$title)));
			}
			
			if ($limit === 1) return $img;
			
			$rv[] = $img;
			
			if ($limit && count($rv) >= $limit) break;
		}
		return $rv;
	}
	
	public function getThumb($asHTML = false, $size = array("height"=>200)) {
		$imgs = $this->getImages();
		foreach ($imgs as $im) {
			if (strpos($im["src"],"//") === false) $im["src"] = substr(WWW_BASE_NON_DEV,0,-1).$im["src"];
			$im["src"] = str_replace("/gifloader/","/",$im["src"]);
			
			$dir = SL_WEB_PATH."/img/blog";
			if (!is_dir($dir)) mkdir($dir);
			$thumbFile = $dir."/".date("Y-m-d",$this->get("published"))."-".safeFile($im["title"]."-".substr(json_encode($size),1,-1)).".jpg";

			if (!is_file($thumbFile)) {
				if (is_file($dir."/".md5($im["src"]).".nf") && filemtime($dir."/".md5($im["src"]).".nf") > time() - 0) {
					echo "\n<!-- '".$im["src"]."' Not found c -->\n";
					continue;
				}
				if (!@getimagesize($im["src"])) {
					touch($dir."/".md5($im["src"]).".nf");
					echo "\n<!-- '".$im["src"]."' Not found -->\n";
					continue;
				}
				
				require_once(SL_INCLUDE_PATH."/class.slImage.php");
				
				$slim = new slImage();
				if (!$slim) {
					echo "\n<!-- Failed to resize '".$im["src"]."' -->\n";
					continue;
				}
				$slim->fromFile($im["src"]);
				$slim->resize($size);
				$slim->jpeg($thumbFile);
			}
			$im["src"] = webPath($thumbFile);
			
			if ($asHTML) return '<img src="'.$im["src"].'" title="'.htmlspecialchars($im["title"]).'">';
			return $im;
		}
		return $asHTML ? "" : false;
	}
	
	public function getContentExcerpt($maxLen = 200) {
		$c = strip_tags($this->data["content"]);
		$c = explode(" ",$c);
		$len = 0;
		
		foreach ($c as $word) {
			$len += 1 + strlen($word);
			if ($len >= $maxLen) return implode(" ",$rv)."...";
			$rv[] = $word;
		}
		
		$rv = array();
		return implode(" ",$rv);
	}
}
