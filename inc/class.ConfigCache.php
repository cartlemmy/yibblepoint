<?php

class ConfigCache {
	private $changed = array();
	
	public static function set() {
		$cc = self::getCC();
		$args = func_get_args();
		$v = array_pop($args);
		$n = array_pop($args);
		return $cc->_set($args, $n, $v);
	}
	
	public static function enforce() {
		$args = func_get_args();
		$opts = array_pop($args);
		if (!self::getNode($args, false)) {
			die('Required config parameter slConfig.'.implode('.',$args).' not set.');
		}
	}
	
	public static function &getNode($path, $setIfEmpty = true) {
		$cc = self::getCC();
		return $cc->_getNode($path, $setIfEmpty);
	}

	private static function getCC() {
		if (!isset($GLOBALS["slConfigCacheOb"])) {
			$GLOBALS["slConfigCacheOb"] = new ConfigCache();
		}
		return $GLOBALS["slConfigCacheOb"];
	}


	private function _set($path, $n, $v) {		
		if ($node = &self::getNode($path)) {
			if (!isset($node[$n]) || $node[$n] !== $v) {
				$node[$n] = $v;
				$path[] = $n;
				$this->changed[implode(".", $path)] = $v;
			}
		}
	}
	
	
	private function &_getNode($path, $setIfEmpty = true) {
		$node = &$GLOBALS["slConfig"];
		while ($n = array_shift($path)) {
			if (!isset($node[$n])) {
				if ($setIfEmpty) {
					$node[$n] = array();
				} else {
					$nope = null;
					$node = &$nope;
					return $node;
				}
			}
			$node = &$node[$n];
		}
		return $node;
	}
	
}
