<?php

class pDataFolder {
	private $exclude = array('fullDesc','webDescription','webTitle');
	public $dir;
	private $i;
	private $ord;
	
	private $orderByField = array(array('ord',false));
	private $orderAlphaFirst = false;
	private $resFilter = true;
	private $enableHide;
	
	public $filtered = false;
	
	public $tree;
	public $map;
	public $all;
	public $info;
	
	private $index;
	private $indexUpdated;
	
	public $thumbSquare = false;
	public $thumbCropTop = false;
	
	public $maxImgWidth = false;
	public $maxImgHeight = false;
	public $definition = false;
	
	public $historyDir = false;
	
	public function __construct($dir, $noHide = false) {
		$this->enableHide = !$noHide;
		$this->reset();
		$this->dir = $dir;
		$this->map = array();
		$this->all = array();
		$this->ord = array();
		$this->index = array();
		$this->tree = $this->parse($dir);
		usort($this->all,array($this,"mapSort"));
		
		$sub = array_pop(explode("/",$dir));

		$this->info = $GLOBALS["slCore"]->db->getTableInfo('xml/'.$sub);
		
		if (isset($_GET["get"])) {
			$full = array();
			foreach ($this->tree as $n=>$o) {
				$full[$n] = $this->fetch($n, true);
			}

			switch ($_GET["get"]) {
				case "images":
					ob_clean();
					$zipFile = SL_DATA_PATH.'/images-'.$sub.'.zip';
					if (!(is_file($zipFile) && filemtime($zipFile) > time() - 3600)) {
						$zip = new ZipArchive;
						$res = $zip->open($zipFile, ZipArchive::OVERWRITE | ZipArchive::CREATE);
						if ($res === TRUE) {
							foreach ($full as $n=>$o){
								foreach ($o["images"] as $img) {
									$zip->addFile(SL_WEB_PATH.'/'.$img["local"],$o["safeName"].'-'.array_pop(explode('/',$img["local"])));
								}
							}
							$zip->close();
						}
					}

					header("Pragma: public");
					header("Expires: 0");
					header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
					header("Cache-Control: public");
					header("Content-Description: File Transfer");
					header("Content-type: application/octet-stream");
					header("Content-Disposition: attachment; filename=\"".array_pop(explode('/', $zipFile))."\"");
					header("Content-Transfer-Encoding: binary");
					header("Content-Length: ".filesize($zipFile));
					ob_end_flush();
					@readfile($zipFile);
					exit();
					
				case "json":
					ob_clean();
					header('Content-Type: application/json');
					echo json_encode($full,JSON_PRETTY_PRINT);
					exit();
			}
		}
	}
	
	public function parse($dir) {
		$rv = array();
		if (is_dir($dir)) {
			if ($dp = opendir($dir)) {
				$this->index = is_file($dir.'/index') ? json_decode(file_get_contents($dir.'/index'),true) : array();
				if (is_dir($dir.'/_history')) $this->historyDir = $dir.'/_history';
				$this->indexUpdated = false;
				while (($file = readdir($dp)) !== false) {
					$path = $dir."/".$file;
					if ($file == "_history" || $file == "." || $file == ".." || substr($file,0,1) == ".") {
						//NADA
					} elseif (is_dir($path)) {
						switch ($file) {
							case "vid":
								break;
							
							case "img":
								break;
								
							default:
								$rv[$file] = array(
									"type"=>"sub",
									"path"=>$path,
									"name"=>strtr($file,"_"," "),
									"children"=>$this->parse($path)
								);
								break;
						}
					} else {
						$file = explode(".",$file);
						$ext = array_pop($file);
						$base = implode(".",$file);
						
						switch ($ext) {
							case "make":
								$make = explode("\n\n",file_get_contents($path));
								foreach ($make as $item) {
									$item = explode("\n",$item);
									$name = array_shift($item);
									$safeName = safeName($name);
									$desc = implode("\n",$item);
									
									$xmlFile = $dir."/".$safeName.".xml";
		
									if (!is_file($xmlFile)) {
										file_put_contents($xmlFile,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<elective>\n\t<name>".$name."</name>\n\t<desc>".$desc."</desc>\n\t<fullDesc></fullDesc>\n\t<webTitle></webTitle>\n\t<webDescription></webDescription>\n\t<col>3</col>\n</elective>");
										mkdir($dir."/img/".$safeName);
									}
								}
								break;
								
							case "xml":
								$imgDir = $dir."/img";
								if (!is_dir($imgDir)) mkdir($imgDir);
								$imgDir .= "/".$base;
								if (!is_dir($imgDir)) mkdir($imgDir);
								
								if ($info = self::getArrayXML($path, $base == "_define")) {
									if ($base == "_define") {
										$this->definition = $info;
										continue;
									}
									
									if ($this->enableHide && setAndTrue($info,"hide") && searchify($info["hide"],'') !== 'show') break;
									
									if (!isset($info["synonym"])) $info["synonym"] = array();
									if (!in_array($base,$info["synonym"])) $info["synonym"][] = $base;
									
									foreach ($info["synonym"] as $n) {
										$this->map[$n] = $path;
									}
									
									$node = array(
										"_KEY"=>$this->getIndex($base),
										"type"=>"node",
										"name"=>isset($info["name"]) ? $info["name"] : $base,
										"path"=>$path,
										"imgDir"=>$imgDir,
										"synonym"=>$info["synonym"]										
									);
									
														
									foreach ($info as $n=>$v) {
										if (!isset($node[$n]) && !in_array($n,$this->exclude)) $node[$n] = $v;
									}
									
									if (!isset($node["ord"]) || !is_numeric($node["ord"])) $node["ord"] = 10000;
									$rv[$base] = &$node;
									
									$this->all[] = &$node;
									unset($node);
								}
								break;
						}
					}
				}
				closedir($dp);
				$this->commit();
			}
		}
		return $rv;
	}
	
	public function commit() {
		if ($this->indexUpdated) {
			file_put_contents($this->dir.'/index',json_encode($this->index));
			$this->indexUpdated = false;
		}
	}
	
	public function rename($from, $to) {
		if (($pos = array_search($from,$this->index)) !== false) {
			$this->index[$pos] = $to;
			$this->indexUpdated = true;
			$this->commit();
		}
	}
	
	public function getIndex($n) {
		if (($pos = array_search($n,$this->index)) !== false) {
			return $pos + 1;
		}
		$this->index[] = $n;
		$this->indexUpdated = true;
		return count($this->index);
	}
	
	public function mapSort($a,$b) {
		foreach ($this->orderByField as $o) {
			$r = $o[1] ? $this->cmp($b,$a,$o[0]) : $this->cmp($a,$b,$o[0]);
			if ($r != 0) return $r;
		}
		return 0;
	}

	private function cmp($a,$b,$n) {
		if ((!is_numeric(self::SV($a,$n)) && !is_bool(self::SV($a,$n))) || (!is_numeric(self::SV($b,$n)) && !is_bool(self::SV($b,$n)))) {
			//Not sure how to handle $this->orderAlphaFirst
			return strcmp(self::SV($a,$n), self::SV($b,$n));
		}
		if (self::SV($a,$n) === self::SV($b,$n)) return 0;
		if (self::SV($a,$n) === false) return 1;
		if (self::SV($b,$n) === false) return -1;
		return self::SV($a,$n) > self::SV($b,$n) ? 1 : -1;
	}
	
	public static function SV($o,$n) {
		return isset($o[$n]) && $o[$n] !== '' ? $o[$n] : false;
	}
	
	public function ordVal($o, $n) {
		if (!isset($o[$n])) return 1000000;
		
		if (is_numeric($o[$n])) return $o[$n];
		
		if (is_string($o[$n])) {
			if (strpos($o[$n],'-') !== false) {
				return (int)trim(array_shift(explode('-',$o[$n])));
			}
			return ($this->orderAlphaFirst ? -46656 : 500000) + self::letterVal(substr($o[$n],0,1)) * 1296 + self::letterVal(substr($o[$n],1,1)) * 36 + self::letterVal(substr($o[$n],2,1));
		}		
		return 1000000;
	}
	
	public function orderBy($n, $alphaFirst = false) {
		$this->orderByField = is_array($n) ? $n : array($n);
		foreach ($this->orderByField as &$field) {
			$field = preg_split('/\s+/',trim($field));
			$field[1] = isset($field[1]) ? strtolower($field[1]) == "desc" : false; 
		}
		$this->orderAlphaFirst = $alphaFirst;
		
		usort($this->all,array($this,"mapSort"));
	}
	
	public function orderDesc($desc) {
		$this->orderByField[0][1] = $desc;
		usort($this->all,array($this,"mapSort"));
	}		
	
	public function filter($filter) {
		if (preg_match_all('/\$(\w[\w\d]*)/',$filter,$match)) {
			foreach ($match[0] as $n=>$var) {
				$filter = str_replace($var,'$res->get('.var_export($match[1][$n],true).')',$filter);
			}
		}
		$this->resFilter = $filter;
		
		$this->reset();
		$prevItem = null;

		$this->filtered = array();
		
		while ($item = $this->fetch()) {
			$this->filtered[] = $item->get('safeName');
		}
		
		$this->reset();
	}
	
	public function allFromSafe($n) {
		foreach ($this->all as $o) {
			if (array_shift(explode('.',array_pop(explode("/",$o["path"])))) === $n) return $o;
		}
		return false;
	}
	
	public function letterVal($l) {
		$v = ord(strtolower($l));
		if ($v >= 48 && $v <= 57) return $v - 48;
		return ($v + 21) % 36;
	}
	
	public static function parseXML($xml) {
		$rv = array();
		foreach ($xml as $n=>$o) {
			$rv[$n] = self::parseXML($o);
			if (trim((string)$o)) $rv[$n]["label"] = (string)$o;
			
			foreach ($o->attributes() as $n2=>$v2) {
				$rv[$n][$n2] = (string)$v2;
			}
			
		}
		return $rv;
	}
	
	public static function getArrayXML($file, $definition = false) {
		if ($definition) return self::parseXML(simplexml_load_file($file));	
		
		return self::FIXES(json_decode(json_encode(simplexml_load_file($file)),true));
	}

	public static function FIXES($data) {
		/*if (isset($data["tzfix"])) {
			
			$fix = explode(',', $data["tzfix"]);
			foreach ($fix as $fn) {
				if (setAndTrue($data,$fn)) {
					//if (isset($_GET["test"])) echo "__TZFIX__ ".$fn." ".date('Y-m-d g:ia', $data[$fn]);
					$data[$fn] -= (int)date('Z', $data[$fn]);
					//if (isset($_GET["test"])) echo " to ".date('Y-m-d g:ia', $data[$fn])." __ ";
				}
			}
			unset($data["tzfix"]);
		}*/
		return $data;
	}
		
	public function reset() {
		$this->i = 0;
	}
	
	public function count() {
		return count($this->all);
	}
	
	public function getPosFrom($n,$v) {
		foreach ($this->all as $pos=>$o) {
			if ($v == $o[$n]) return $pos;
		}
		return -1;
	}
	
	public function fetch($n = false, $asData = false) {
		if ($this->resFilter !== true) {
			do {
				if ($res = $this->__fetch($n, $asData)) {
					eval('$use = '.$this->resFilter.';');
				} else return false;
			} while (!$use);
			return $res;
		}
		return $this->__fetch($n, $asData);
	}
	
	private function __fetch($n, $asData = false) {
		if ($n) {
			if (isset($this->map[$n])) {
				$dp = $this->getDataPoint($this->map[$n], $this->getPosFrom("path",$this->map[$n]));
				return $asData ? $dp->getAll() : $dp;
			}
			return false;
		}
		
		if ($this->i >= count($this->all)) return false;
		$info = $this->all[$this->i++];
		$dp = $this->getDataPoint($info["path"], $this->i - 1);
		return $asData ? $dp->getAll() : $dp;
	}
	
	public function getDataPoint($file, $pos = -1) {
		return new pDataPoint($file, $this, $pos);
	}
}

class pDataPoint {
	private $xmlFile;
	private $info;
	private $folder;
	private $pos;
	public $convString = array("name","desc","prev","next","fullDesc","webTitle","webDescription");
	
	public function __construct($xmlFile, $folder = false, $pos = -1) {
		$this->folder = $folder;
		$this->xmlFile = $xmlFile;
		$this->info = pDataFolder::getArrayXML($xmlFile);
		
		//if (isset($_GET["test"])) {echo "<pre>\n\n".json_encode(array($xmlFile, count($folder), $pos))."\n\n</pre>"; }
		$cs = $this->convString == true ? array_keys($this->info) : $this->convString;
		foreach ($cs as $n) {
			if (isset($this->info[$n]) && is_array($this->info[$n]) && (isset($this->info[$n][0]) || !count($this->info[$n]))) $this->info[$n] = implode("",$this->info[$n]);
		}
		
		$this->info["safeName"] = array_pop(explode("/",array_shift(explode(".",$xmlFile))));
		
		$this->info["_KEY"] = $folder->getIndex($this->info["safeName"]);
		
		if (!isset($this->info["synonym"])) $this->info["synonym"] = array();
		if (!in_array($this->info["safeName"],$this->info["synonym"])) $this->info["synonym"][] = $this->info["safeName"];
		
		$p = explode("/",str_replace(SL_WEB_PATH."/",'',$xmlFile));
		array_pop($p);
		$this->info["dir"] = implode("/",$p);
		
		$this->info["url"] = WWW_BASE.$this->info["dir"]."/".$this->info["safeName"]."/";
		$this->info["urlRel"] = WWW_RELATIVE_BASE.$this->info["dir"]."/".$this->info["safeName"]."/";
		
		$this->pos = $pos;
		
		if ($folder) {		
			if ($folder->filtered) {
				$fPos = array_search($this->info["safeName"],$folder->filtered);
				if ($fPos !== false) {
					if ($fPos > 0 && !isset($this->info["prev"])) {
						$p = $folder->allFromSafe($folder->filtered[$fPos - 1]);
						$this->info["prev"] = $this->info["dir"]."/".array_pop(explode("/",array_shift(explode(".",$p["path"])))).'/';
					}
					if ($fPos < count($folder->filtered) - 1 && !isset($this->info["next"])) {
						$p = $folder->allFromSafe($folder->filtered[$fPos + 1]);
						$this->info["next"] = $this->info["dir"]."/".array_pop(explode("/",array_shift(explode(".",$p["path"])))).'/';				
					}
				}
			} else {
				if ($pos > 0 && !isset($this->info["prev"])) {
					$p = $folder->all[$pos - 1];
					$this->info["prev"] = $this->info["dir"]."/".array_pop(explode("/",array_shift(explode(".",$p["path"])))).'/';
				}
				if ($pos < $folder->count() - 1 && !isset($this->info["next"])) {
					$p = $folder->all[$pos + 1];
					$this->info["next"] = $this->info["dir"]."/".array_pop(explode("/",array_shift(explode(".",$p["path"])))).'/';				
				}
			}
		}
		
		
		if (isset($this->info["prev"])) $this->info["prev"] = WWW_RELATIVE_BASE.$this->info["prev"].(setAndTrue($_GET,"frame") ? "?frame=1" : "");
		if (isset($this->info["next"])) $this->info["next"] = WWW_RELATIVE_BASE.$this->info["next"].(setAndTrue($_GET,"frame") ? "?frame=1" : "");
		
		
		$this->info["imgDir"] = $this->info["dir"]."/img/".$this->info["safeName"];
		$this->info["images"] = array();
	
		$this->info["imgPath"] = webPath(SL_WEB_PATH."/".$this->info["imgDir"],true);
		
		if (!setAndTrue($this->info,"fullDesc") && isset($this->info["desc"])) $this->info["fullDesc"] = $this->info["desc"];
		if (!setAndTrue($this->info,"webTitle") && isset($this->info["name"])) $this->info["webTitle"] = $this->info["name"];
		if (!setAndTrue($this->info,"webDescription") && isset($this->info["fullDesc"]) && !is_array($this->info["fullDesc"])) $this->info["webDescription"] = $this->info["fullDesc"];
	
		if (!isset($this->info["video"])) {
			$this->info["video"] = array();

			$exts = array("mp4","webv","png","jpg","jpeg");
			foreach ($exts as $ext) {
				$file = $this->info["dir"]."/vid/".$this->info["safeName"].".".$ext;
				//if (is_file("web/".$file)) $this->info["video"][] = $file;
				if (is_file(SL_WEB_PATH_NON_DEV.'/'.$file)) $this->info["video"][] = $file;
			}
		}
		
		$imgDir = SL_WEB_PATH.'/'.$this->info["imgDir"];
		if ($dp3 = opendir($imgDir)) {
			$order = 100;
			while (($file3 = readdir($dp3)) !== false) {	

				$fileBase = explode(".",$file3);
				$ext = strtolower(array_pop($fileBase));
				$fileBase = implode(".",$fileBase);
				
				if (in_array($ext,array("jpg","jpeg","png"))) {
					$image = array("local"=>str_replace(SL_WEB_PATH."/","",$imgDir."/".$file3),"src"=>str_replace(SL_WEB_PATH."/",WWW_BASE,$imgDir."/".$file3));
					if (!($size = getimagesize($imgDir."/".$file3))) continue;
					
					$image["width"] = $size[0];
					$image["height"] = $size[1];
					$image["size"] = $size[0] * $size[1];
					
					$extStr = array();
					if (is_file($imgDir."/".$fileBase.".json") && is_array($extra = json_decode(file_get_contents($imgDir."/".$fileBase.".json"),true))) {
						$image = array_merge($image,$extra);
						foreach ($extra as $n=>$v) {
							if (substr($n,0,5) == "data-") $extStr[] = $n.'="'.htmlspecialchars($v).'"';
						}
					}
					$image["extra"] = implode(" ",$extStr);
					if ($fileBase == "blur" || $fileBase == "header" || $fileBase == "01" || $fileBase == "review") $image["noGallery"] = 1;
					
					if (!isset($image["sortOrder"])) {
						$image["sortOrder"] = $fileBase == "main" ? 1 : $order;
					}
					
					if (strtolower($fileBase) == "generated-thumb") {
						if (!isset($this->info["thumb"])) $this->info["thumb"] = $image;
					} else $this->info["images"][] = $image;
					
					if (strtolower($fileBase) == "thumb") $this->info["thumb"] = $image;				
					
					$order++;
				}
			}
			
			closedir($dp3);
			usort($this->info["images"],array($this,'imageSort'));
			
			if (count($this->info["images"])) {
				if ($this->folder->maxImgWidth || $this->folder->maxImgHeight) {
					foreach ($this->info["images"] as &$image) {
						$resize = array();
						if ($this->folder->maxImgWidth && $image["width"] > $this->folder->maxImgWidth) {
							$resize = array($this->folder->maxImgWidth, false);
						}
						if ($this->folder->maxImgHeight && $image["height"] > $this->folder->maxImgHeight) {
							$resize = array(false, $this->folder->maxImgHeight);
						}
						if ($resize) {
							
							$src = explode(".",$image["local"]);
							$ext = array_pop($src);
							$src = explode("/",implode(".",$src));
							$file = array_pop($src);
							$dir = implode("/",$src)."/resized";
							if (!is_dir(SL_WEB_PATH.'/'.$dir)) mkdir(SL_WEB_PATH.'/'.$dir);							
							$src = $dir.'/'.$file."-".($resize[0]?$resize[0].'w':$resize[1].'h').".jpg";					
							
							if (!is_file(SL_WEB_PATH."/".$src)) {
								$im = imagecreatefromjpeg(SL_WEB_PATH."/".$image["local"]);
								
								if ($resize[0]) {
									$scale = $resize[0] / imagesx($im);
									$nw = $resize[0];
									$nh = round(imagesy($im) * $scale);
								} else {
									$scale = $resize[1] / imagesy($im);
									$nw = round(imagesx($im) * $scale);
									$nh = $resize[1];
								}
								
								//TODO resize any data in 'extra'
								
								$newIm = imagecreatetruecolor($nw,$nh);
								imagecopyresampled(
									$newIm, $im,
									0, 0, 0, 0,
									$nw, $nh, imagesx($im), imagesy($im)
								);

								imagejpeg($newIm,SL_WEB_PATH."/".$src);
								
								imagedestroy($im);
								imagedestroy($newIm);
							}
							
							$image["local"] = $src;
							$image["src"] = webPath(SL_WEB_PATH."/".$src);
						}					
					}

				}
				
				if (!isset($this->info["thumb"])) {
					$imgs = array_values($this->info["images"]);
					
					$imSize = getimagesize(SL_WEB_PATH."/".$imgs[0]["local"]);
					$h = 480;
					
					if ($this->folder->thumbSquare) {
						$thumbFile = $imgDir."/generated-thumb.jpg";
						$im = imagecreatefromjpeg(SL_WEB_PATH."/".$imgs[0]["local"]);
						$newIm = imagecreatetruecolor($h,$h);
						
						if (imagesx($im) > imagesy($im)) {
							imagecopyresampled(
								$newIm, $im,
								0, 0, round((imagesx($im) - imagesy($im)) / 2), 0,
								$h, $h, imagesy($im), imagesy($im)
							);
						} else {
							imagecopyresampled(
								$newIm, $im,
								0, 0, 0, $this->folder->thumbCropTop ? 0 : round((imagesy($im) - imagesx($im)) / 2),
								$h, $h, imagesx($im), imagesx($im)
							);
						}
						
						imagejpeg($newIm,$thumbFile);
							
						imagedestroy($im);
						imagedestroy($newIm);
					} else {				
						if ($imSize[1] > $h) {
							$thumbFile = $imgDir."/generated-thumb.jpg";
							$im = imagecreatefromjpeg(SL_WEB_PATH."/".$imgs[0]["local"]);
							$w = round(imagesx($im) * ($h / imagesy($im)));
							
							$newIm = imagecreatetruecolor($w,$h);
							
							imagecopyresampled(
								$newIm, $im,
								0, 0, 0, 0,
								$w, $h, imagesx($im), imagesy($im)
							);
							imagejpeg($newIm,$thumbFile);
							
							imagedestroy($im);
							imagedestroy($newIm);
						}
					}
					$this->info["thumb"] = $imgs[0];
				}
			}
		}
	}
	
	public function getThumb() {
		if (setAndTrue($this->info,"thumb")) return $this->info["thumb"];
		return array("src"=>"","extra"=>"");
	}
	
	public function get($n,$def = "") {
		return isset($this->info[$n]) ? $this->info[$n] : $def;
	}
	
	public function getAll() {
		return $this->info;
	}
	
	private function imageSort($a, $b) {
		return $a["sortOrder"] - $b["sortOrder"];
	}
	
	public function prev($n = false) {
		return $this->getSibling(-1,$n);
	}
	
	public function next($n = false) {
		return $this->getSibling(1,$n);
	}
	
	public function getFolderIndex() {
		foreach ($this->folder->all as $i=>$o) {
			if ($o["path"] == $this->xmlFile) return $i;
		}
		return -1;
	}
	
	public function getSibling($off, $n = false) {
		$i = ($off + count($this->folder->all) + $this->getFolderIndex()) % count($this->folder->all);
		if ($n === false) return $this->folder->all[$i];
		if ($n !== true && isset($this->folder->all[$i][$n])) return $this->folder->all[$i][$n];
		
		$sibling = new pDataPoint($this->folder->all[$i]["path"],$this->folder);
		if ($n === true) return $sibling;
		return $sibling->get($n);
	}
}
