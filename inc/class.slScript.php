<?php

require_once(SL_INCLUDE_PATH."/class.slTranslator.php");
requireThirdParty("minify",true);

class slScript extends slTranslatorBase {	
	private $cache = "";
	private $lastModified = 0;
	public $useAbsolutePath = false;
	public $maxAge = 0;
	public $debug = false;
	private $minifyAvailable = false;
	
	function __construct($id,$APILoader = false) {
		$this->APILoader = $APILoader;
		$this->id = $id;
		$this->dir = SL_DATA_PATH;
		$this->root = "//".$_SERVER["SERVER_NAME"].$GLOBALS["slConfig"]["requestInfo"]["docParent"]."/sl/";
		$this->minifyAvailable = class_exists('Minify_Source');
	}
	
	function parse($file, $noLoadScript = false) {
		$this->lastModified = max($this->lastModified,filemtime($file));
		$content = file_get_contents($file);
		$pos = 0;
		$includes = array();
		while (($pos = strpos($content,"!include(",$pos)) !== false) {
			if (($end = strpos($content,")",$pos)) !== false) {
				$end ++;
				$includes[] = substr($content,$pos+9,$end-$pos-10);
				$content = substr($content,0,$pos).substr($content,$end);
			} else break;
		}
		
		while (($pos = strpos($content,"!inline(",$pos)) !== false) {
			if (($end = strpos($content,")",$pos)) !== false) {
				$end ++;
				$include = substr($content,$pos+9,$end-$pos-11);
				if (substr($include,0,1) != "/") {
					$content = substr($content,0,$pos).file_get_contents(realpath(dirname($file))."/".$include).substr($content,$end);
				} else {
					$content = substr($content,0,$pos).file_get_contents($include).substr($content,$end);
				}
				
			} else break;
		}
		
		$this->parseLGJS($content);
		
		if ((($GLOBALS["slConfig"]["dev"]["debug"] && !$GLOBALS["slSetupMode"]) && !$noLoadScript) || $noLoadScript === "load") {
			$this->cache .= "sl.loadScript(".json_encode($this->parsePath($file)).");\n";
		} else {
			$this->cache .= $this->languageParse($content);
		}
		
		foreach ($includes as $include) {
			eval('$include = '.$include.';');

			if (substr($include,-2) == "/*") {
				$dir = realpath(dirname($file)."/".substr($include,0,-2));
				if ($dp = opendir($dir)) {
					while ($file = readdir($dp)) {
						$path = $dir."/".$file;
						if ($file != "." && $file != ".." && is_file($path)) {
							$this->parse($path);
						}
					}
					closedir($dp);
				}
			} else {
				if (substr($include,0,1) != "/") {
					$this->parse(realpath(dirname($file))."/".$include);
				} else {
					$this->parse($include);
				}
			}
		}
		
	}
	
	function parseLGJS(&$js) {
		
	}
	
	function parsePath($local) {
		return webPath($local,!$this->useAbsolutePath);
	}
	
	function fetch() {
		return $this->cache;
	}
	
	function alert($txt) {
		$this->cache .= "alert(".json_encode($txt).");";
	}
	
	function start() {
		ob_start();
	}
	
	function stop() {
		$this->cache .= ob_get_clean();
	}
	
	function append($txt) {
		$this->cache .= $txt;
	}
	
	function out($noMinify = false) {
		if (!$this->minifyAvailable) $noMinify = true;
		if ($this->cache) {
			$ri = $GLOBALS["slRequestInfo"];
			if (isset($ri["params"]["lad"])) { // Load As Data
				$name = str_replace(".js","",array_pop(explode("/",$ri["path"],2)));
				echo 'if (sl.scripts["'.substr($ri["path"],3).'"].params) var params = sl.scripts["'.substr($ri["path"],3).'"].params;';
				echo 'sl.data["'.$name.'"]='.trim($this->cache).';';
			} elseif ($noMinify || ($GLOBALS["slConfig"]["dev"]["debug"] && !$GLOBALS["slSetupMode"])|| $this->debug) {
				header("Content-type: application/javascript");
				echo $this->cache;
			} else {
				$sources = array(new Minify_Source(array(
						'id' => $this->id,
						'getContentFunc' => array($this,'fetch'),
						'contentType' => Minify::TYPE_JS,
						'lastModified' => $this->APILoader ? time() : $this->lastModified
				)));
				
				$options = array(
					'files' => $sources,
					'maxAge' => $this->APILoader ? - 1 : $this->maxAge
				);
				
				if ($this->APILoader) {
					if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) unset($_SERVER['HTTP_IF_NONE_MATCH']);
					if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
					$options["encodeMethod"] = false;
					$options["quiet"] = 1;
	
					$res = Minify::serve("Files", $options);
					
					
					unset($res["headers"]["Content-Length"]);
					
					foreach ($res["headers"] as $name => $val) {
						header($name . ': ' . $val);
					}
					
					echo $res["content"];
				} else {				
					Minify::serve("Files", $options);
				}
			}
			$this->cache = "";
		}
	}
}
