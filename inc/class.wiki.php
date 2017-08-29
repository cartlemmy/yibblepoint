<?php

class wiki {
	private static $RULES = array(
		'/^\-\-> (.*) \-\->$/'
			=> array('<div style="float:right">\1</div>',1),
		'/^= (.*) =$/'
			=>	array('<h1>\1</h1>',1),
		'/^== (.*) ==$/'
			=>	array('<h2>\1</h2>',1),
		'/^=== (.*) ===$/'
			=>	array('<h3>\1</h3>',1),
		'/^==== (.*) ====$/'
			=>	array('<h4>\1</h4>',1),
		'/^===== (.*) =====$/'
			=>	array('<h5>\1</h5>',1),
		'/^====== (.*) ======$/'
			=> array('<h6>\1</h6>',1),
		'/\[\[(.*?)\]\]/'
			=>	array('<span class="keys">\1</span>',0),
		'/\*(.+?)\*/'
			=>	array('<em>\1</em>',0),
		"/'''(.+?)'''/"
			=>	array('<b>\1</b>',0),
		"/''(.+?)''/"
			=>	array('<i>\1</i>',0),
		'/`(.+?)`/'
			=>	array('<tt>\1</tt>',0),
		'/^----$/'
			=>	array('<hr />',1),
		'/\[(https?\:\/\/.+?)\s+(.+?)\]/'
			=>  array('<a href="\1">\2</a>',0)
	);
	
	private static $OPTIONAL_RULES = array(
		"ul"=>array('/^\*\s(.+?)$/','<li>\1</li>',1),
		"ol"=>array('/^[\d]+\s(.+?)$/','<li>\1</li>',1)
	);
	
	private static $IGNORE = array("pre","table","script","style");
	
	private static function checkPrev($line,$prevRules,$curRules = array()) {
		if (in_array("ul",$curRules) && !in_array("ul",$prevRules)) return "<ul>\n".$line;
		if (!in_array("ul",$curRules) && in_array("ul",$prevRules)) return "</ul>\n".$line;
		
		if (in_array("ol",$curRules) && !in_array("ol",$prevRules)) return "<ol>\n".$line;
		if (!in_array("ol",$curRules) && in_array("ol",$prevRules)) return "</ol>\n".$line;
		
		return false;		
	}
	
	public static function matchIgnore($txt) {
		foreach (self::$IGNORE as $tag) {
			if (stripos($txt,'<'.$tag) !== false) return $tag;
		}
		if (preg_match('/\[\!(\w[\w\d\-]*)\:?([^\]]*)\]/',$txt,$match)) return "!".$match[1];
		return false;
	}
	
	public static function matchEnd($txt, $tag) {
		return (stripos($txt,'</'.$tag.'>') !== false ||
		stripos($txt,'[/'.$tag.']') !== false);
	}
	
	public static function wikify($txt, $options = array("p"=>1,"ol"=>1,"ul"=>1)) {
		$txt = explode("\n",$txt);	
			
		$insideOfStack = array();
		$prevRules = array();
		foreach ($txt as $lnum=>&$line) {
			if ($io = self::matchIgnore($line)) {
				if (!self::matchEnd($line,$io) && self::matchEnd(implode("\n",array_slice($txt, $lnum+1)),$io)) $insideOfStack[] = $io;
			}
			
			if (!count($insideOfStack)) {
				$noP = false;
				foreach (self::$RULES as $pattern=>$replace) {
					if (preg_match($pattern,$line)) {
						if ($replace[1]) $noP = true;
						$line = preg_replace($pattern,$replace[0],$line);
					}
				}
				$curRules = array();
				foreach (self::$OPTIONAL_RULES as $n=>$r) {
					if (isset($options[$n]) && $options[$n]) {
						$pattern = $r[0];
						$replace = $r[1];
						if (preg_match($pattern,$line)) {
							if ($r[2]) $noP = true;
							$line = preg_replace($pattern,$replace,$line);
							$curRules[] = $n;
						}
					}
				}
				if ($l = self::checkPrev($line,$prevRules,$curRules)) $line = $l;
				$prevRules = $curRules;
				unset($curRules);
				if (!$noP && self::SAT($options,"p") && self::addPTags($line)) $line = "<p>".$line."</p>";
			} else {
				while (
					count($insideOfStack) && self::matchEnd($line, $insideOfStack[count($insideOfStack) - 1])
				) {
					array_pop($insideOfStack);
				}
			}
		}
		
		if ($l = self::checkPrev("",$prevRules)) $txt[] = $l;
		
		return implode("\n",$txt);
	}
	
	private static function addPTags($line) {
		$noP = array("[!pali:map]");
		foreach ($noP as $check) {
			if (strpos($line,$check) !== false) return false;
		}
		return true;
	}
	
	private static function SAT($var,$p) {
		return isset($var[$p]) && $var[$p];
	}
}
