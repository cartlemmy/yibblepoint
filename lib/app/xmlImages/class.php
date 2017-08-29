<?php

require_once(SL_INCLUDE_PATH."/class.secuToken.php");
require_once(SL_INCLUDE_PATH."/class.slDbIndexer.php");

$GLOBALS["SLIE_NUM"] = 0;

class slItemEdit extends slAppClass {
	private $dbi;
	private $setup = false;
	private $subSetup = null;
	private $subSetups = array();
	private $updated = array();
	private $subDBI = null;
	private $subDBIs = array();
	private $error = false;
	
	function __construct($app) {
		$this->NUM = $GLOBALS["SLIE_NUM"]++;
		$this->app = $app;
		
		if (isset($this->app->args[0])) {
			if ($this->setup = $GLOBALS["slCore"]->db->getTableInfo($this->app->args[0])) {
				$this->setup = $this->app->translateArray($this->setup);
				
				if (isset($this->setup["permissions"])) {
					if (!$this->app->checkPermissions($this->setup["permissions"])) return;
				}
				
				$this->setup["args"] = $this->app->args;
				
				$this->setup["dir"] = $GLOBALS["slSession"]->user->dir;
				if ($orderby = $this->app->get("orderby")) $this->setup["orderby"] = $orderby;
				if ($orderdir = $this->app->get("orderdir")) $this->setup["orderdir"] = $orderdir;			
				if ($search = $this->app->get("search")) $this->setup["search"] = $search;	
				$this->dbi = getSlDbIndexer($this->setup);
				} else {
				$this->error = "'".$this->app->args[0]."' not found.";
			}
		} else {
			$this->error = "Item not specified.";
		}
		parent::__construct($app);
	}

	function getAll() {
		$data = null;
		if (isset($this->app->args[1])) {
			if ($this->app->args[1] == "NEW") {
				$data = array();
				foreach ($this->setup["fields"] as $n=>$v) {
					$data[$n] = "";
				}
			} else {
				if ($res = $GLOBALS["slCore"]->db->selectOne($this->app->args[0],array("_KEY"=>$this->app->args[1]))) {
					$data = $res->fetch();
				}
			}
		}
		
		foreach ($data["images"] as &$image) {
			$image["thumb"] = self::getThumb(SL_WEB_PATH."/".$image["local"]);
		}
		
		return array(
			"setup"=>$this->setup,
			"data"=>$data,
			"error"=>$this->error
		);
	}
	
	public function addImage($n,$image) {
		$n = safeName($n);
		$src = SL_DATA_PATH."/users/".$image["user"]."/file/image/".$image["md5"].".".array_pop(explode("/",$image["type"]));
		$size = getimagesize($src);
		$dest = SL_WEB_PATH."/".array_pop(explode("/",$this->setup["table"]))."/img/".$n."/".$image["name"];
		copy($src,$dest);
		return array(
			"extra"=>"",
			"width"=>$size[0],
			"height"=>$size[1],
			"size"=>$size[0] * $size[1],
			"local"=>$dest,
			"src"=>webPath($dest),
			"thumb"=>self::getThumb($dest),
			"sortOrder"=>null
		);
	}
	
	public function delImage($image) {
		$src = SL_WEB_PATH."/".$image["local"];
		$ext = array_pop(explode(".",$src));
		$md5 = md5_file($src);
		$thumbFile = SL_DATA_PATH.'/tmp/th-'.$md5.".".$ext;
		if (is_file($thumbFile)) unlink($thumbFile);
		if (is_file($src)) unlink($src);
		$src = explode("/",$src);
		array_pop($src);
		$src = implode("/",$src);
		if (is_file($src.'/generated-thumb.jpg')) unlink($src.'/generated-thumb.jpg');
		return true;
	}
	
	public static function getThumb($src) {
		$ext = array_pop(explode(".",$src));
		$md5 = md5_file($src);
		$thumbFile = SL_DATA_PATH.'/tmp/th-'.$md5.".".$ext;
		if (!is_file($thumbFile)) {
			require_once(SL_INCLUDE_PATH."/class.slImage.php");
			$im = new slImage();
			$im->fromFile($src);
			if ($im->width() > $im->height()) {
				$im->resize(256,false);
			} else {
				$im->resize(false,256);
			}
			$im->jpeg($thumbFile);
			$im->destroy();
		}
		return webPath($thumbFile);
	}
}
