<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");

class loginApp extends slAppClass {
	private $items = false;
	private $translator;
	
	function getItems() {
		if (!$this->items) {
			$this->translator = new slTranslator();
			$this->items = array_merge(
				$this->getLangFromDir(SL_LIB_PATH."/app"),
				$this->getLangData(SL_INCLUDE_PATH."/data/lang")
			);
		}
		return $this->items;
	}
	
	function getLangFromDir($dir) {
		$rv = array();
		if ($dp = opendir($dir)) {
			while ($file = readdir($dp)) {
				$path = $dir."/".$file;
				if ($file == "lang" && is_dir($path)) {
					$rv = array_merge($rv,$this->getLangData($path,str_replace(SL_LIB_PATH."/app/","",$path)));
				} elseif ($file != "." && $file != ".." && is_dir($path)) {
					$rv = array_merge($rv,$this->getLangFromDir($path));
				}
			}
			closedir($dp);
		}
		return $rv;
	}
	
	function getLangData($dir,$app = "None") {
		$rv = array();
		if ($dp = opendir($dir)) {
			while ($file = readdir($dp)) {
				$path = $dir."/".$file;
				if (is_file($path)) {
					$data = $this->translator->decodeLanguageData(file_get_contents($path));
					$cnt = $translated = 0;
					foreach ($data as $item) {
						if ($item["type"] != "untranslated") $translated ++;
						$cnt++;
					}
					
					$rv[] = array($app, $file, $translated." / ".$cnt, $path);

				}
			}
			closedir($dp);
		}
		return $rv;
	}
	
	function cnt() {
		$this->getItems();
		return count($this->items);
	}
	
	function item($i) {
		$this->getItems();
		$item = $this->items[$i];
		return array(
			"app"=>$item[0],
			"lang"=>$item[1],
			"translated"=>$item[2],
			"file"=>$item[3]
		);
	}
}
