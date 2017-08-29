<?php

class slTranslator extends slClass {	
	private $dir;
	
	function __construct($dir = false) {
		$this->dir = $dir;
		if ($dir) makePath($dir);
	}
	
	function translate($ref,$text,$fromLanguage,$toLanguage) {
		return $this->getText($ref,$toLanguage,$text,$fromLanguage);
	}
	
	function getText($ref,$language,$fromText,$fromLanguage = false) {
		$data = $this->getLanguageData($language);
		if (isset($data[$ref]) && $data[$ref]["type"] != "untranslated") {
			return $data[$ref]["text"];
		} 
		
		//TODO: Look it up
		if (!isset($data[$ref])) {
			$data[$ref] = array("type"=>"untranslated","text"=>$fromText,"orig"=>$fromText);		
			if ($fromLanguage) $data[$ref]["lang"] = $fromLanguage;
			
			$this->setLanguageData($language,$data);
		}
		
		return $fromText;
	}
	
	function getLanguageData($language) {
		$file = $this->dir."/".$language;
		return is_file($file) ? $this->decodeLanguageData(file_get_contents($file)) : array();
	}
	
	function setLanguageData($language,$data) {
		$file = $this->dir."/".$language;
		file_put_contents($file,$this->encodeLanguageData($data));
	}
	
	function decodeLanguageData($data) {
		$data = explode("\n----\n",trim($data));
		$rv = array();
		foreach ($data as $item) {
			$item = explode("\n",$item);
			$rv[$item[0]] = array("type"=>$item[1],"text"=>$item[2]);
			$orig = explode("|",isset($item[3]) ? $item[3] : ($item[1] == "untranslated" ? $item[2] : ""),2);
						
			if (count($orig) == 2 && strlen($orig[0]) <= 5) $rv[$item[0]]["lang"] = array_shift($orig);
			$rv[$item[0]]["orig"] = implode("|",$orig);
		}
		return $rv;
	}
	
	function encodeLanguageData($data) {
		$rv = array();
		foreach ($data as $n=>$v) {
			$rv[] = $n."\n".$v["type"]."\n".$v["text"]."\n".(isset($v["lang"]) ? $v["lang"]."|" : "").(isset($v["orig"])?$v["orig"]:$v["text"]);
		}
		return implode("\n----\n",$rv);
	}
}

class slTranslatorBase extends slClass {	
	public $dir;
	
	function languageParse($t) {
		//TODO: cache this
		$lastPos = $pos = 0; $out = ""; $match = array();
		while (preg_match("/(\'|\"|\>)(\w{2}\-\w{2})\|/",$t,$match,NULL,$pos)) {
			list($match,$sep,$language) = $match;
			
			$lastPos = $pos;
			$pos = strpos($t,$match,$pos) + 7;
			$out .= substr($t,$lastPos,$pos - $lastPos - 6);
			if ($sep == ">") $sep = "<";
			$endPos = strpos($t,$sep,$pos);
			if ($endPos === false) $endPos = strlen($t);
			
			$text = substr($t,$pos,$endPos - $pos);

			if ($language != $GLOBALS["slConfig"]["international"]["language"]) {
				$ref = safeName($text);
				
				if (strlen($ref) > 40) $ref = sha1($ref);
				
				$text = $this->translate($ref, $text, $language, $GLOBALS["slConfig"]["international"]["language"]);
			}
			
			$out .= $text;
			$pos = $endPos;
		}
		$out .= substr($t,$pos);
		return $out;
	}
	
	function translateText($text,$toLanguage) {
		$fromLanguage = substr($text,0,5);
		$text = tagParse(substr($text,6));
		
		if ($fromLanguage != $toLanguage) {
			$ref = safeName($text);
			if (strlen($ref) > 40) $ref = sha1($ref);
			
			return $this->translate($ref,$text, $fromLanguage, $toLanguage);
		}
		return $text;
	}
	
	function translateArray(&$array) {		
		$string = false;
		if (is_string($array)) {
			$string = true;
			$array = array($array);
		}
		if (is_array($array)) {
			
			$match = array();
			
			foreach ($array as $n=>$text) {
				if (is_string($n) && preg_match("/^(\w{2}\-\w{2})\|/",$n,$match)) {
					unset($array[$n]);
					$n = $this->translateText($n,$GLOBALS["slConfig"]["international"]["language"]);
					$array[$n] = $text;
				}
			}
			foreach ($array as $n=>&$text) {
				if (is_string($text) && preg_match("/^(\w{2}\-\w{2})\|/",$text,$match)) {
					$text = $this->translateText($text,$GLOBALS["slConfig"]["international"]["language"]);
				} elseif (is_array($text)) {
					$this->translateArray($text);
				}
			}
		}
		return $string ? $array[0] : $array;
	}
	
	function translate($ref,$text,$fromLanguage,$toLanguage) {
		if (!isset($this->translator) || !$this->translator) {
			$this->translator = new slTranslator($this->dir."/lang");
		}
		return tagParse($this->translator->translate($ref,$text,$fromLanguage,$toLanguage));
	}
}

function translateHTML($html) {
	$tb = new slTranslatorBase();
	$tb->dir = SL_INCLUDE_PATH."/data";
	return $tb->languageParse($html);
}

function translate($text) {
	$tb = new slTranslatorBase();
	$tb->dir = SL_INCLUDE_PATH."/data";
	return $tb->translateArray($text);
}

function noTranslation($text) {
	return preg_replace("/^(\w{2}\-\w{2})\|/","",$text);
}
