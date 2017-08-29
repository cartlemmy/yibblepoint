<?php

class startMenu extends slAppClass {
	private $appList = false;
	
	function __construct($app) {
		$this->app = $app;
		$this->dirReplace = realpath(SL_LIB_PATH."/..")."/";
		$this->menuFile = $GLOBALS["slSession"]->getUserDir(true)."/menu.json";

	}
	
	function getMenu() {
		return array("menu"=>$this->buildMenu());
	}
	
	function buildMenu() {
		if (is_file($this->menuFile)) return json_decode(file_get_contents($this->menuFile),true);
		return $this->buildMenuFromAppDir();
	}
	
	static function menuSort($a,$b) {
		if (isset($a["menuOrder"]) && isset($b["menuOrder"])) return $a["menuOrder"] < $b["menuOrder"] ? 1 : -1;
		if (isset($a["menuOrder"]) && !isset($b["menuOrder"])) return -1;
		if (!isset($a["menuOrder"]) && isset($b["menuOrder"])) return 1;
		return strcasecmp($a["label"],$b["label"]);
	}

	function getAppList($coreApps) {
		$this->appList = true;
		$res = $this->buildMenuFromAppDir();
		$this->appList = false;
		return $this->appListProc($res,$coreApps);
	}
	
	function appListProc($apps,$coreApps = false, $dir = false) {
		if ($dir === false) $dir = SL_LIB_PATH."/app";
		$rv = array();
		foreach ($apps as $item) {
			if (is_dir($dir."/".$item["ref"])) {
				if (isset($item["children"])) $rv = array_merge($rv,$this->appListProc($item["children"],$coreApps,$dir."/".$item["ref"]));
				if ($coreApps === false || isset($item["coreApp"])) {
					$rv[] = array(
						"dir"=>str_replace(SL_BASE_PATH,".",$dir."/".$item["ref"]),
						"name"=>$item["label"]
					);
				}
			}
		}
		return $rv;
	}

	function buildMenuFromAppDir($dir = "lib/app") {
		$rv = array();
		if ($dp = opendir($dir)) {
			while ($file = readdir($dp)) {
				$path = $dir."/".$file;
				
				if (!($file == "." || $file == "..") && is_dir($path)) {
					if (is_file($path."/manifest.json")) {
						if ($item = $this->getMenuItem($path)) {
							$rv[] = $item;
						}			
					}
				}
			}
			closedir($dp);
		}
		usort($rv,array("startMenu","menuSort"));
		return $rv;
	}
	
	function getMenuItem($path, $fromAdditional = false) {
		if (($manifest = translate(json_decode(file_get_contents($path."/manifest.json"),true))) && ($this->appList || !(isset($manifest["noMenu"]) && $manifest["noMenu"] && !$fromAdditional))) {
			if (isset($manifest["permissions"])) {
				if (!$GLOBALS["slSession"]->user->hasPermission($manifest["permissions"])) return false;
			}
									
			$item = array(
				"ref"=>substr($path,8),
				"label"=>$manifest["name"]
			);
			
			if (!$this->appList && $fromAdditional) {
				if (isset($fromAdditional["permissions"])) {
					if (!$GLOBALS["slSession"]->user->hasPermission($fromAdditional["permissions"])) return false;
				}
				$faParams = array("label","icon","args");
				foreach ($faParams as $n) {
					if (isset($fromAdditional[$n])) $item[$n] = $fromAdditional[$n];
				}
			}
			
			if (!$this->appList) {
				if ($fromAdditional && isset($fromAdditional["icon"])) {
					$dir = realpath(SL_LIB_PATH."/".$fromAdditional["icon"]);
					if ($dir) $fromAdditional["icon"] = $dir;
				}
				
				if ($icon = slApp::loadIcon($fromAdditional && isset($fromAdditional["icon"]) ? $fromAdditional["icon"] : $path)) {
					$item["icon"] = $icon;
				}
			}
					
			if (isset($manifest["menuOrder"])) $item["menuOrder"] = $manifest["menuOrder"];
			if (isset($manifest["coreApp"])) $item["coreApp"] = $manifest["coreApp"];
			
			if (!($children = $this->buildMenuFromAppDir($path))) $children = array();
		
			if (!$this->appList && isset($manifest["additionalMenu"])) {
				foreach ($manifest["additionalMenu"] as $additionalItem) {
					if (is_string($additionalItem)) {
						$children[] = $additionalItem;
					} elseif ($child = $this->getMenuItem(str_replace($this->dirReplace,"",realpath($path."/".$additionalItem["ref"])), $additionalItem)) {
						$children[] = $child;
					}
				}
			}
			
			if ($children) $item["children"] = $children;
			
			return $item;
		}
		return false;
	}
}
