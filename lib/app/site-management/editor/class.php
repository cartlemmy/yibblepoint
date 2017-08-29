<?php

require_once(SL_INCLUDE_PATH."/class.slWeb.php");
require_once(SL_INCLUDE_PATH."/class.slRemote.php");
			
class slSiteEditor extends slAppClass {
	private $setup = false;
	private $web;
	
	function __construct($app) {
		$this->web = new slWeb();
		$this->rem = new slRemote();
		
		$this->app = $app;
	}
	
	function getPages() {
		return $this->web->getPages("editor");
	}
		
	function getPage($page,$history = false) {
		$page = safeFile($page);
		list($dynamicFile,$contentFile) = $this->getPageFiles($page);
		
		if ($dynamicFile || $contentFile) {
			if ($contentFile) {
				if ($history) {
					$useContentFile = SL_WEB_PATH."/content/history/".array_pop(explode("/",$contentFile))."/".$history;
				} else {
					$useContentFile = $contentFile;
				}
				
				if (file_get_contents($useContentFile,false,NULL,0,12) == "!yp-content:") {
					list($header,$content) = explode("\n",file_get_contents($useContentFile),2);
					$info = json_decode(substr($header,12),true);
				} else {
					$content = file_get_contents($useContentFile);
					$info = array();
				}
			} else {
				$content = "";
			}
			
			$info["dynamicFile"] = $dynamicFile;
			$info["contentFile"] = $contentFile;
			
			$info["urlName"] = $page;
			if (!$contentFile) $info["noContent"] = true;
			if (!setAndTrue($info,"noContent")) $info["content"] = $content;
			
			$info["history"] = $this->getHistory($contentFile);
			
			return $info;
		}
		return null;
	}
	
	function getPHPPreview($page,$content = false) {
		$pageData = $this->getPage($page);
		
		if ($pageData["contentFile"]) {
			$incFile = str_replace(".html","",$pageData["contentFile"]).".prev.inc.php";
			if (!$content) $content = $pageData["content"];
			
			$content = explode("<?php",$content);
			
			$num = 0;
			for ($i = 1; $i < count($content); $i++) {
				$o = explode("?>",$content[$i],2);
				$php = array_shift($o);
				$html = count($o) ? $o[0] : "";
				
				$php = '<!-- _PHP_PREV --><?php '.$php.' ?><!-- _END_PHP_PREV -->';
				$num ++;
				$content[$i] = $php.$html;
			}
			
			file_put_contents($incFile,implode("\n",$content));
			
			$content = explode("<!-- _PHP_PREV -->",$this->rem->request(array(CURLOPT_URL=>WWW_BASE.$page.".html?PHPPrev")));
			$rv = array();
			
			for ($i = 1; $i < count($content); $i++) {
				$rv[] = array_shift(explode("<!-- _END_PHP_PREV -->",$content[$i]));
			}
			
			return $rv;
		}
		return null;
	}
	
	function PHPPreviewError($errno, $errstr, $errfile, $errline) {
		$bt = debug_backtrace();
		array_shift($bt);
		
		echo errorTypeName($errno).": ".$errstr." in ". shortFile($errfile)." on ".$errline."\n";
		
		htmlBacktrace($bt);
		
		return true;
	}	
		
	function setPage($page,$data) {
		$refresh = false;
		
		$old = $this->getPage($page);
		list($dynamicFile,$contentFile) = $this->getPageFiles(isset($data["urlName"])?$data["urlName"]:$page,true);
		if (!$old) $old = array("urlName"=>false);
		
		if (isset($old["urlName"]) && isset($data["urlName"]) && $old["urlName"] != $data["urlName"]) {
			//TODO: Update parent references
			
			$refresh = true;
			if ($old["urlName"]) { // Rename
				if ($old["dynamicFile"]) rename($old["dynamicFile"],$dynamicFile);
				if ($old["contentFile"]) unlink($old["contentFile"]);
			}
		}
		
		if (isset($old["parent"]) && isset($data["parent"]) && $old["parent"] != $data["parent"]) $refresh = true;
		
		$data = array_merge($old,$data);
		
		$content = "";
		if (isset($data["content"]) && !setAndTrue($data["noContent"])) {
			$content = $data["content"];
			unset($data["content"]);
		}
		
		unset($data["urlName"]);
		unset($data["dynamicFile"]);		
		unset($data["contentFile"]);		
		
		$data["editedBy"] = $GLOBALS["slSession"]->getUserName();
		
		$historyDir = SL_WEB_PATH."/content/history/".array_pop(explode("/",$contentFile));
		makePath($historyDir);
		
		$c = ($data?"!yp-content:".json_encode($data)."\n":"").$content;
		
		file_put_contents($historyDir."/".date("Y-m-d-H-i-s")."-".$data["editedBy"],$c);
		file_put_contents($contentFile,$c);
		
		return array("refresh"=>$refresh,"history"=>$this->getHistory($contentFile));
	}
	
	function historyOrder($a,$b) {
		return $a["edited"] - $b["edited"];
	}
	
	function getHistory($contentFile) {
		$historyDir = SL_WEB_PATH."/content/history/".array_pop(explode("/",$contentFile));
		$rv = array();
		if (is_dir($historyDir)) {
			if ($dp = opendir($historyDir)) {
				while (($file = readdir($dp)) !== false) {
					if (is_file($historyDir."/".$file)) {
						$d = explode("-",$file);
						$rv[] = array("file"=>$file,"edited"=>mktime($d[3],$d[4],$d[5],$d[1],$d[2],$d[0]),"editedBy"=>$d[6]);
					}
				}
				closedir($dp);
			}
		}
		usort($rv,array($this,"historyOrder"));
		return $rv;
	}
	
	function getPageFiles($page,$ifDoesntExist = false) {
		return array(
			$ifDoesntExist || is_file(SL_WEB_PATH."/".$page.".php") ? SL_WEB_PATH."/".$page.".php" : false,
			$ifDoesntExist || is_file(SL_WEB_PATH."/content/".$page.".html") ? SL_WEB_PATH."/content/".$page.".html" : false
		);
	}
	
	function deletePage($page) {
		list($dynamicFile,$contentFile) = $this->getPageFiles($page);
		if ($dynamicFile) return array("failed"=>1);
		if ($contentFile) unlink($contentFile);
		return array("refresh"=>true);
	}
}
