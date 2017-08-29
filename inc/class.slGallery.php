<?php

require_once(SL_INCLUDE_PATH."/class.slImage.php");

class slGallery extends slClass {	
	protected $user;
	protected $galleryName;
	protected $subGallery = false;
	protected $images = array();
	protected $hierarchical = true;
	protected $setHierarchicalDepth = 0;
	protected $galleryData = false;
	protected $ignoreChildren = false;
	private $ajaxRequest = false;
	private $flags = array("_TYPE","protected");
	private $lastResize = array(0,0);
	private $preloaderSize = false;
	protected $preloaderSrc = "";
	
	protected $params = array(
		"width"=>0,
		"height"=>200,
		"padding"=>20,
		"imageDir"=>false,
		"gridTargetIn"=>1.5,
		"showCaption"=>true,
		"copyImages"=>false,
		"createPreloader"=>false,
		"preloaderCompression"=>2,
		"preloaderGrayscale"=>false,
		"preloaderBrightness"=>0
	);
	
	function __construct($user = false, $galleryName = false) {
		$this->user = $user;
		if ($galleryName) $this->setGallery($galleryName);
	}
		
	function setGallery($galleryName) {
		$this->galleryName = $galleryName;
		$this->loadImages();
	}
	
	function setSubGallery($subGallery) {
		$this->subGallery = $subGallery;
		$this->getDir();
	}
	
	
	function setName($galleryName) {
		$this->galleryName = $galleryName;
	}
	
	function setImages($images) {
		$this->images = $images;
	}
	
	function setParam($n,$v) {
		$this->params[$n] = $v;
		switch ($n) {
			case "imageDir":
				$this->getDir();
				makePath($v.($this->subGallery?"/".$this->subGallery:""));
				break;
		}
	}
	
	function setHierarchical($h) {
		$this->hierarchical = $h;
	}
	
	function setHierarchicalDepth($h) {
		$this->setHierarchicalDepth = $h;
	}
	
	function loadImages() {
		$this->images = array();
		$this->loadImageTree($this->galleryName, $this->images);
	}
	
	function findSubGallery($gallery) {
		$g = explode("/",$this->subGallery);
		array_unshift($g,$gallery);
		$parentGallery = 0;
		while ($n = array_shift($g)) {
			$where = array("_NO_USER"=>1,"_NAME"=>array_merge(array("ANY"),explode(",",$n)));
			if ($parentGallery) $where["parentGallery"] = $parentGallery;

			if ($res = $GLOBALS["slCore"]->db->select("db/gallery",$where)) {
				$gallery = $res->fetch();
				$parentGallery = $gallery["_KEY"];
			} else return false;
		}
		return array("_KEY"=>$gallery["_KEY"]);
	}
	
	function loadImageTree($gallery, &$images, $level = 0, $path = "") {
		if ($this->subGallery && $level == 0) {
			$gallery = $this->findSubGallery($gallery);
			if (!$gallery) return false;
			$path = $this->subGallery;
		}
		
		if (is_array($gallery)) {
			$where = $gallery;
		} elseif (is_numeric($gallery)) {
			$where = array("parentGallery"=>$gallery);
		} else {
			$where = array("_NAME"=>array_merge(array("ANY"),explode(",",$gallery)));
		}

		$where["_NO_USER"] = 1 ;
		if ($res = $GLOBALS["slCore"]->db->select("db/gallery",$where)) {
			while ($gallery = $res->fetch()) {		
				if ($this->hierarchical) $gallery["children"] = array();

				if ($imRes = $GLOBALS["slCore"]->db->select("db/galleryImages",array("galleryId"=>$gallery["id"]))) {
					while ($image = $imRes->fetch()) {
						
						$image["_TYPE"] = "image";
						$image["_NAME"] = $image["caption"];
						$image["_ID"] = "i".$image["_KEY"];
						$image["path"] = $path;
						$image["src"] = slImage::getSrc($image["image"]);
						$image["image"] = delimToObject($image["image"],array("name","type","size","md5","dimensions","thumbHead","thumb","user"));						
						//echo $level." ".$image["image"]["name"]." ".print_r($images,true)."\n";
						
						unset($image["image"]["thumb"]);
						unset($image["_IMAGE"]);
	
						if ($this->hierarchical && $level != 0) {
							$gallery["children"][] = $image;
						} else {
							$images[] = $image;
						}
					}						
				}
				
				if ($level == 0) {
					$this->galleryData = $gallery;
				} elseif ($this->hierarchical) {
					$gallery["_TYPE"] = "gallery";
					$gallery["_NAME"] = $gallery["name"];
					$gallery["path"] = $path;
					$gallery["_ID"] = "g".$gallery["_KEY"];
					$images[] = &$gallery;
				}
				
				// TODO: Sort
				
				if ($this->hierarchical && $level != 0) {
					$im = &$gallery["children"];
				} else {
					$im = &$images;
				}
				
				$newPath = $path ? explode("/",$path) : array();
				if ($level != 0) $newPath[] = $gallery["nameSafe"];
				if (!$this->ignoreChildren && $level == 0 || $level < $this->setHierarchicalDepth + 1) $this->loadImageTree($gallery["id"],$im,$level+1,implode("/",$newPath));
				
				unset($gallery);
			}
		}
	}
	
	function getWebFileName($image) {				
		return ($this->subGallery?$this->subGallery."/":"").$this->_getWebFileName($image);
	}
	
	function _getWebFileName($image) {
		if (!isset($image["webFileName"])) {
			if (setAndTrue($image,"_NAME")) {
				return safeFile($image["_NAME"]).".".array_pop(explode(".",$image["image"]["name"]));
			} else {
				return $image["image"]["name"];
			}
		}
		
		if (!setAndTrue($image,"_NAME") && setAndTrue($image["image"],"name")) {
			$image["_NAME"] = array_shift(explode(".",array_pop(explode("/",$image["image"]["name"]))));
		}
		
		if (setAndTrue($image,"_NAME")) {
			$f = safeFile(($image["_TYPE"] == "gallery" ? "Gallery " : "").array_shift(explode("\n",$image["_NAME"])));
			if ($f == $image["webFileName"]) return $f;
			$num = 1;
			while ($GLOBALS["slCore"]->db->select("db/galleryImages",array("webFileName"=>($webFileName = $f.($num > 1 ? "-".$num : "")),"id"=>array("!=",$image["id"])))) {
				$num ++;
			}
			$GLOBALS["slCore"]->db->update("db/galleryImages",array("webFileName"=>$webFileName),array("id"=>$image["id"]));
			return $webFileName;
		}
		return $image["_ID"];
	}
	
	function resizeImage(&$image) {
		$src = $image["src"];

		if (function_exists("exif_read_data")) {
			surpressErrors(true);
			if ($exif = exif_read_data($src)) {
				if (!empty($exif['Orientation']) && ($exif['Orientation'] == 6 || $exif['Orientation'] == 8)) {
					$w = $image["width"];
					$image["width"] = $image["height"];
					$image["height"] = $w;
				}
			}
			surpressErrors(false);
		}
		
		if ((!$image["width"] || !$image["height"]) && isset($image["image"]["dimensions"])) {
			$dim = explode("x",$image["image"]["dimensions"]);
			$image["width"] = $dim[0];
			$image["height"] = $dim[1];
		}

		if (setAndTrue($this->params,"width") && setAndTrue($this->params,"height")) {
			$width = $this->params["width"];
			$height = $this->params["height"];
		} elseif (setAndTrue($this->params,"width")) {
			$width = $this->params["width"];
			$height = round($this->params["width"] * ($image["height"] / $image["width"]));
		} elseif (setAndTrue($this->params,"height")) {
			$height = $this->params["height"];
			$width = round($this->params["height"] * ($image["width"] / $image["height"]));
		}
		
		$image["width"] = $width;
		$image["height"] = $height;
		
		$this->lastResize = array($width,$height);
		$filePrefix = $this->params["imageDir"]."/".$this->getWebFileName($image)."_".$width."x".$height;

		if (is_file($filePrefix.".jpg") && filemtime($filePrefix.".jpg") >= $image["updated"]) {
			return $filePrefix.".jpg";
		} else {
			$srcIm = new slImage();
			if ($srcIm->fromFile($src)) {	
				
				$srcIm->rotateFromExif();
				
				$srcIm->resize($width * 2, $height * 2);
				
				$srcIm->out($filePrefix."@2x.jpg");
				
				$srcIm->resize($width, $height);
				
				$file = $filePrefix.".jpg";
				$srcIm->out($file);
				
				$srcIm->__destruct();
				
				return $file;
			}
			return false;
		}
	}
	
	function getUID() {
		return safeName("slGallery-".$this->galleryName);
	}
	
	function getJSInfo($extra = array()) {
		if (!$this->ajaxRequest) {
			$ajaxID = safeFile($GLOBALS["slConfig"]["requestInfo"]["uri"]."/".$this->galleryName);
			file_put_contents(SL_DATA_PATH."/tmp/gallery-".$ajaxID.".json",json_encode(array("name"=>$this->galleryName,"params"=>$this->params)));
		} else $ajaxID = false;
		
		$params = $this->params;
		unset($params["imageDir"]);
		return json_encode(array_merge(array(
			"path"=>webPath($this->params["imageDir"].($this->subGallery?"/".$this->subGallery:""),true),
			"galleryData"=>$this->galleryData,
			"images"=>$this->getImagesWithoutExtra(),
			"params"=>$params,
			"preloaderSrc"=>$this->preloaderSrc,
			"preloaderSrcWeb"=>webPath($this->preloaderSrc,true),
			"preloaderSize"=>$this->preloaderSize,
			"ajaxID"=>$ajaxID
		),$extra));	
	}
	
	protected function copyImages($height,$createPreloader = false) {
		$this->setParam("height",$height);
		$this->setParam("copyImages",true);
		$this->setParam("createPreloader",$createPreloader);
	}
	
	public function getDir() {
		$dir = $this->params["imageDir"].($this->subGallery?"/".$this->subGallery:"");
		$this->preloaderSrc = $dir."/preload.jpeg";
		return $dir;
	}
	
	public static function fixWebName($name) {
		$name = preg_split('/([A-Z][a-z]+)/',$name,NULL,PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $len = count($name); $i < $len; $i++) {
			if (trim($name[$i]) === "") unset($name[$i]);
		}
		return implode(" ",$name);
	}
	
	function getImagesWithoutExtra(&$images = false, $l = 0) {
		if (!$images) $images = $this->images;
		$preloaderWidth = 0;
		$preloader = array();
		foreach ($images as $n=>&$im) {
			
			if (is_array($im) && count($im)) {
				if (isset($im["_TYPE"])) {
					$im["title"] = !setAndTrue($im,"_NAME") && setAndTrue($im,"webFileName") ? self::fixWebName($im["webFileName"]) : $im["_NAME"];
					if ($im["_TYPE"] == "image" && $this->params["copyImages"]) {
						$src = $this->resizeImage($im);
						$im["image"] = webPath($src,true);
						$w = round($this->lastResize[0] / $this->params["preloaderCompression"]);
						$h = round($this->lastResize[1] / $this->params["preloaderCompression"]);
						$im["pl"] = array($preloaderWidth,0);
						$preloaderWidth += $w;
						$preloader[$im["_ID"]] = array($w,$h,$src);
					}
					if (!setAndTrue($im,"webFileName")) $im["webFileName"] = $this->getWebFileName($im);
					
					$fullImage = $this->params["imageDir"]."/".$im["webFileName"];
					if (!is_file($fullImage."@2x.jpg")) {
						copy($im["src"],$fullImage."@2x.jpg");
					}
					$im["fullImage"] = webPath($fullImage.".jpg",true);
					
					$im["f"] = $im["_TYPE"] == "image" ? 1 : 0;
					unset($im["_TYPE"]);
					foreach ($this->flags as $n=>$flag) {
						if (setAndTrue($im,$flag)) $im["f"] = $im["f"] | pow(2,$n);
					}
				}
				$this->getImagesWithoutExtra($im, $l + 1);
			} elseif (!is_numeric($n) && (substr($n,0,5) == "exif_" || $im === "" || in_array($n,array("id","lat","lng","taken","created","updated","protected","sortOrder","_KEY","_NAME","_UNIQUE","_TYPE","key","nameSafeHash","src","galleryId")))) {
				unset($images[$n]);
			}
			unset($im);
		}
		if ($preloaderWidth) {
			$dir = $this->getDir();
			$preloaderHash = md5(json_encode($preloader));
			$this->preloaderSize = array($preloaderWidth,round($this->params["height"]/$this->params["preloaderCompression"]));
			if (!(is_file($dir."/preload") && file_get_contents($dir."/preload") == $preloaderHash)) {
				$plIm = imagecreatetruecolor($preloaderWidth,round($this->params["height"]/$this->params["preloaderCompression"]));
				$x = 0;
				foreach ($preloader as $id=>&$img) {
					$file = array_pop($img);
					$srcIm = slImage::getImageFromAny($file);
					imagecopyresampled(
						$plIm, $srcIm,
						$x, 0, 0, 0,
						$img[0], $img[1], imagesx($srcIm), imagesy($srcIm)
					);
					imagedestroy($srcIm);
					$x += $img[0];
				}
				
				imageinterlace($plIm, 1);
				
				if ($this->params["preloaderGrayscale"]) imagefilter ( $plIm, IMG_FILTER_GRAYSCALE );
				if ($this->params["preloaderBrightness"]) imagefilter ( $plIm, IMG_FILTER_BRIGHTNESS, $this->params["preloaderBrightness"] );
				
				imagejpeg($plIm,$this->preloaderSrc,75);
				imagedestroy($plIm);
				file_put_contents($dir."/preload",$preloaderHash);
			}
			$this->preloader = $preloader;
		} else $this->preloader = false;
		return $images;
	}
}

class slSwipeGallery extends slGallery {	
	function __construct($name = false,$galleryName = false) {
		parent::setHierarchical(false);
		parent::__construct($name,$galleryName);
	}
	
	public function render() {
		$im = $this->images;
		$fullWidth = 0;
		$ais = array();
		
		$pad = $this->params["padding"];
		$padDiv2 = $pad / 2;
		
		ob_start();
		foreach ($im as $image) {
			$path = webPath($this->resizeImage($image),true);
			$w = round(($image["width"] / $image["height"]) * $this->params["height"]);
			echo '<div style="height:'.$this->params["height"].'px;float:left;padding:0 '.$padDiv2.'px 0 '.$padDiv2.'px;"><img src="'.$path.'" style="width:'.$w.'px;height:'.$this->params["height"].'px;" title="'.htmlspecialchars($image["caption"]).'">';
			if ($this->params["showCaption"]) echo '<div class="gallery-caption">'.$image["caption"].'</div>'."\n";
			echo '</div>';
			$ais[] = array($image["width"],$image["height"], $fullWidth + $padDiv2);
			$fullWidth += $image["width"] + $pad;
		}
		$width = $fullWidth;
		$w2 = 0;
		foreach ($im as $image) {
			$path = webPath($this->resizeImage($image),true);
			$w = round(($image["width"] / $image["height"]) * $this->params["height"]);
			echo '<div style="height:'.$this->params["height"].'px;float:left;padding:0 '.$padDiv2.'px 0 '.$padDiv2.'px;"><img src="'.$path.'" style="width:'.$w.'px;height:'.$this->params["height"].'px;" title="'.htmlspecialchars($image["caption"]).'">';
			if ($this->params["showCaption"]) echo '<div class="gallery-caption">'.$image["caption"].'</div>'."\n";
			echo '</div>';
			
			$fullWidth += $image["width"] + $pad;
			$w2 += $image["width"];
			if ($w2 >= 1024) break;
		}
		
		$c = ob_get_clean();
		
		$height = $this->params["height"] + ($this->params["showCaption"] ? 32 : 0);
		
		?><div id="gallery-nav-cont"><div id="gallery-nav" style="height:<?=$this->params["height"];?>px;">
			<div>
				<button id="gallery-prev" type="button" class="btn btn-primary">&lt;<span class="hidden-xs"> Prev</span></button>
				<button id="gallery-next" type="button" class="btn btn-primary"><span class="hidden-xs">Next </span>&gt;</button>
			</div>
		</div></div><?php
		
		echo '<div class="sl-gallery" id="'.$this->getUID().'" style="width:100%;height:'.$height.'px;overflow:hidden;"><div style="width:'.$fullWidth.'px">'."\n";
		echo $c."\n";
		
		echo '</div></div>'."\n";
		echo '<script type="text/javascript">new slSwipeGallery("'.$this->getUID().'", '.$this->getJSInfo(array("width"=>$width,"imgWHX"=>$ais)).');</script>';
	}
}

class slGridGallery extends slGallery {
	function __construct($name = false,$galleryName = false) {
		parent::setHierarchical(true);
		parent::setHierarchicalDepth(1);
		parent::copyImages(160,true);
		parent::setParam("padding",10);
		parent::__construct($name,$galleryName);
	}
	
	public function render() {
		echo '<div class="sl-grid-gallery" id="'.$this->getUID().'"></div>';
		echo '<style>.sl-grid-gallery-image,.sl-grid-gallery-overlay-image>div{background-image:url(\''.webPath($this->preloaderSrc,true).'\')}</style>';		
		echo '<script type="text/javascript">new slGridGallery("'.$this->getUID().'", '.$this->getJSInfo().');</script>';
	}
}
