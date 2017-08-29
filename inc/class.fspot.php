<?php

require_once(SL_INCLUDE_PATH."/gdkPixDataToGD.php");
require_once(SL_INCLUDE_PATH."/class.slCache.php");
require_once(SL_INCLUDE_PATH."/class.slImage.php");

class fspot {
	public $conn;
	private $file;
	public $tagMap = array();
	
	private $whereMap = array(
		"has similar"=>"similar is not null and similar != '' and similar != '[]'",
		"has duplicate"=>"dup = 1"
	);
	
	function __construct($file) {
		if ($this->conn = $GLOBALS["slCore"]->db->connect(array("type"=>"sqlite","file"=>$file,"flags"=>SQLITE3_OPEN_READWRITE))) {
			$this->file = $file;
			$this->init();
		}
	}
	
	function init() {
		$schema = $this->conn->getSchema();
		
		$custom = array(
			"fp"=>"TEXT",
			"fp0"=>"TINYINT",
			"fp1"=>"TINYINT",
			"fp2"=>"TINYINT",
			"fp3"=>"TINYINT",
			"similar"=>"TEXT",
			"dup"=>"BOOLEAN DEFAULT FALSE",
			"lastSimScan"=>"BIGINT DEFAULT 0",
			"inGroup"=>"BOOLEAN DEFAULT FALSE",
		);

		foreach ($custom as $field=>$def) {
			if (!isset($schema["photos"]["fields"][$field])) {
				$this->conn->query("ALTER TABLE photos ADD COLUMN $field $def");
			}
		}
		
		if ($res = $this->conn->select("tags")) {
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				$this->tagMap[$row["name"]] = $row["id"];
			}
		}
	}
	
	function getTagId($name,$parentId = 0) {
		if (isset($this->tagMap[$name])) return $this->tagMap[$name];
		return $this->addTag($name,$parentId);
	}
	
	function getTagNameFromId($id) {
		if ($name = array_search($id,$this->tagMap)) return $name;
		return false;
	}
	
	function addTag($name,$parentId = 0) {
		return $this->tagMap[$name] = $this->conn->upsert("tags",array("name"=>$name,"category_id"=>$parentId),array("name"=>$name));
	}
	
	function removeTag($name) {
		if ((int)$name) {
			$id = (int)$name;
			$name = array_search($id,$this->tagMap);
		} elseif (isset($this->tagMap[$name])) {
			$id = $this->tagMap[$name];
		} else return;
		unset($this->tagMap[$name]);
		$this->conn->query("DELETE FROM photo_tags where tag_id=".$id);
		$this->conn->query("DELETE FROM tags where id=".$id);
	}
	
	function getPhoto($id) {
		if ($res = $this->conn->select("photos",array("id"=>$id),array("limit"=>"1"))) {
			$row = $res->fetchArray(SQLITE3_ASSOC);
			return new fspotPhoto($row,$this);
		}
		return false;
	}
	
	function getPhotos($where,$limit = false) {
		if (isset($this->whereMap[$where])) $where = $this->whereMap[$where];
		$options = array();
		if ($limit) $options["limit"] = $limit;
		$rv = array();

		if ($res = $this->conn->select("photos",$where,$options)) {
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				$rv[] = new fspotPhoto($row,$this);
			}
		}
		return $rv;
	}
	
	function toTagId(&$tags) {
		foreach ($tags as &$tag) {
			if (is_string($tag)) {
				if (preg_replace("/\d+/","",$tag) === "") {
					$tag = (int)$tag;
				} else {
					$tag = $this->getTagId($tag);
				}
			} else if (is_array($tag)) $this->toTagId($tag);
		}
		return $tags;
	}
	
	function getPhotosByTag($tags,$start = 0, $length = 0) {
		$rv = array();
		if ($tags == "ALL") {
			if ($res = $this->conn->select("photos")) {
					while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
						$rv[] = $row["id"];
					}
				}
		} else {
			if (!is_array($tags)) $tags = explode(",",$tags);

			$tags = $this->toTagId($tags);
						
			foreach ($tags as $orTags) {
				if (!is_array($orTags)) $orTags = array($orTags);
				foreach ($orTags as &$andTag) {
					$andTag = "tag_id=".$andTag;
				}
				
				if ($res = $this->conn->select("photo_tags",implode(" AND ", $orTags))) {
					while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
						if (!in_array($row["photo_id"],$rv)) $rv[] = $row["photo_id"];
					}
				}
			}
		}
		return $rv;
	}
	
	function getTags($getAsTree = true) {
		if ($getAsTree !== true && $getAsTree !== false) {
			$parentTag = $this->getTagId($getAsTree);
			$getAsTree = true;
		} else $parentTag = 0;
				
		if ($res = $this->conn->select("tags")) {
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				if (substr($row["icon"],0,11) == "stock_icon:") {
					$row["icon"] = webPath(realpath(dirname(__FILE__))."/handlers/photo/".substr($row["icon"],11).".png");
				} else {
					$file = SL_DATA_PATH."/f-spot";
					makePath($file);
					$file .= "/thumb-".md5($row["icon"]).".png";
					if (is_file($file)) {
						$row["icon"] = webPath($file);
					} else {
						if ($im = gdkPixDataToGD($row["icon"])) {
							imagepng($im, $file);
							imagedestroy($im);
							$row["icon"] = webPath($file);
						} else {
							$row["icon"] = webPath(realpath(dirname(__FILE__))."/handlers/photo/emblem-photos.png");
						}
					}
					
				}
				$rv[] = $row;
			}
		}
		if ($getAsTree) $rv = $this->tagTree($rv,$parentTag);
		return $rv;
	}
	
	function tagTree($tags, $parentId = 0) {
		$rv = array();
		foreach ($tags as $tag) {
			if ($tag["category_id"] == $parentId) {
				if ($children = $this->tagTree($tags,$tag["id"])) $tag["children"] = $children;
				$rv[] = $tag;
			}
		}
		return $rv;
	}
	
	function getParentTag($tag) {
		if ($res = $this->conn->select("tags",array("id"=>$this->getTagId($tag)))) {
			if ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				return $this->getTagNameFromId($row["category_id"]);
			}
		}
		return false;
	}
	
	function getSimilarPhotoGroups($start = 0, $limit = 0) {
		$cache = new slCache("fspot-similar-groups");
		
		if ($cache->isNewerThan(time()-3600)) {
			$gc = json_decode($cache->get(),true);
			if ($start == -1) return count($gc);
			$groups = array();
			$cnt = 0;
			foreach ($gc as &$group) {
				if ($cnt >= $start && (!$limit || $cnt < $start + $limit)) {
					$o = array();
					foreach ($group as $id=>$photo) {
						$o[$id] = $this->getPhoto((int)$id);
						if (!$o[$id]) unset($o[$id]);
					}
					$groups[] = $o;
				}
				$cnt++;
			}
		} else {
			$this->conn->query("UPDATE photos SET inGroup=0 WHERE 1");
			
			$idMap = array();
			$groups = array();
			$photos = $this->getPhotos("has similar");
			
			foreach ($photos as $photo) {
				$i = null;
				//Find existing group:
				$found = false;
				$ids = array($photo->data["id"]);
				
				$similar = json_decode($photo->data["similar"],true);
				foreach ($similar as $o) {
					$ids[] = $o[0];
				}
				
				foreach ($groups as $i=>$group) {
					foreach ($ids as $id) {
						if (isset($group[$id])) {
							$found = true;
							break;
						}
					}
					if ($found) break;
				}
				if (!$found) {
					$i = count($groups);
					$groups[] = array($photo->data["id"]=>$photo);
				} else {
					$groups[$i][$photo->data["id"]] = $photo;
				}
				
				
				foreach ($similar as $o) {
					$groups[$i][$o[0]] = null;
				}			
			}
			
			$rvGroups = array();
			
			$groupsCache = array();
			$cnt = 0;
			foreach ($groups as &$group) {
				$gc = array();
				foreach ($group as $id=>$photo) {
					$this->conn->query("UPDATE photos SET inGroup=1 WHERE id=".$id);
					$gc[$id] = null;
					if (!$photo) $group[$id] = $this->getPhoto((int)$id);
					if (!$group[$id]) unset($group[$id]);
					//Remove tag 'Single' from photos that are in a group
					//$group[$id]->clearTag("Single");
				}
				$groupsCache[] = $gc;
				if ($cnt >= $start && (!$limit || $cnt < $start + $limit)) $rvGroups[] = $gc;
			}
			
			//Add tag 'Single' to photos that are not in a group
			/*if ($res = $this->conn->select("photos",array("inGroup"=>0))) {
				while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
					$photo = $this->getPhoto($row);
					if ($photo) {
						$photo->setTag("Single");
						$photo->__destruct();
					}
				}
			}*/
					
			$cache->set(json_encode($groupsCache));
			if ($start == -1) return count($groups);
			
			$groups = $rvGroups;
		}
		return $groups;
	}


	function deletePhoto($photo) {
		if (is_numeric($photo)) $photo = $this->getPhoto((int)$photo);
		
		if (!$photo) return false;
		
		// Delete photo files (all versions) from db
		if ($res = $this->conn->select("photo_versions",array("photo_id"=>$photo->data["id"]))) {
			$cnt = 0;
			if ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				unlink($this->getAbsolutePath($row));
			}
		}
		
		// Remove references from photo_versions
		$this->conn->query("DELETE FROM photo_versions where photo_id=".$photo->data["id"]);
		
		// Remove references from photo_tags
		$this->conn->query("DELETE FROM photo_tags where photo_id=".$photo->data["id"]);
		
		// Delete photo from db
		$this->conn->query("DELETE FROM photos where id=".$photo->data["id"]);
		
		// Update similarity and dup references
		$similar = json_decode($photo->data["similar"],true);
		if ($similar) {
			foreach ($similar as $o) {
				$this->updateSimilarity($o[0]);
			}
		}
		return true;
	}
	
	function findSimilarPhotos($id, $threshold = 0.07) {
		$rv = array();
		if ($res = $this->conn->select("photos",array("id"=>$id),array("limit"=>1))) {
			$cnt = 0;
			if ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				$photo = new fspotPhoto($row,$this);
				if ($res = $this->conn->select("photos","id!=$id and ABS(fp0 - ".$row["fp0"].") + ABS(fp1 - ".$row["fp1"].") + ABS(fp2 - ".$row["fp2"].") + ABS(fp3 - ".$row["fp3"].") < 14")) {
					while ($test = $res->fetchArray(SQLITE3_ASSOC)) {
						if ($test["fp"]) {
							$dist = $this->getFingerprintDistance($row["fp"],$test["fp"]);
							
							if ($dist < $threshold) {
								$test["dist"] = $dist;
								$test["similarity"] = ($threshold - $dist) / $threshold;
								$rv[] = $test;
							}
						}
					}
					return $rv;
				}
			}
		}
		return false;
	}
	
	function getFingerprintDistance($p1,$p2) {
		if (!$p1 || !$p2) return 100000;
		$dist = 0;
		for ($i = 0, $len = strlen($p1); $i < $len; $i++) {
			$dist += abs(hexdec($p1{$i}) - hexdec($p2{$i}));
		}
		return $dist / (strlen($p1) * 16);
	}
	
	function similarityScan($count = 100) {
		$rv = array();
		if ($res = $this->conn->select("photos","(fp is not null and fp != '') and lastSimScan<".(time()-86400),array("limit"=>$count))) {
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				if ($similar = $this->updateSimilarity($row)) $rv[] = $similar;
			}
		}
		return $rv;
	}
	
	function updateSimilarity($row) {
		if (is_numeric($row)) {
			if ($res = $this->conn->select("photos",array("id"=>(int)$row),array("limit"=>"1"))) {
				$row = $res->fetchArray(SQLITE3_ASSOC);
			} else return false;
		}
		
		$similar = array();
		$dup = false;
		$r = $row;
		$r["similar"] = array();

		if ($s = $this->findSimilarPhotos($row["id"])) {
			foreach ($s as $photo) {
				if ($photo["similarity"] > 0.96) $dup = true;
				$r["similar"][] = $photo;
				$similar[] = array($photo["id"],$photo["similarity"]);
			}
		}
		
		$this->conn->update("photos",array("lastSimScan"=>time(),"similar"=>json_encode($similar),"dup"=>$dup?1:0),array("id"=>$row["id"]));
		return $similar ? $r : false;
	}
	
	function removeDuplicates($count = 100) {
		//$this->conn->query("UPDATE photos SET lastSimScan=0, similar='', dup=0 WHERE 1");exit();
		$dups = $this->getPhotos("has duplicate");
		
		$needMore = max(0, $count - count($dups));

		if ($needMore) $this->similarityScan($needMore);
		
		$cnt = 0;
		foreach ($dups as $dup) {
			$similar = json_decode($dup->data["similar"],true);

			$check = array($dup);
			foreach ($similar as $o) {
				if ($o[1] > 0.96) {
					if ($photo = $this->getPhoto((int)$o[0]))	$check[] = $photo;
				}
			}
			
			if (count($check) > 1) {
				
				$keep = null;
				$best = 0;
				$n = array();
				foreach ($check as $photo) {
					$n[] = $photo->data["filename"];
					if ($photo->getAmountOfDetail() > $best) {
						$best = $photo->getAmountOfDetail();
						$keep = $photo;
					}
				}
				echo "<div style=\"float:left;padding:20px;margin:10px;border:1px solid #DDD\"><b>".implode(", ",$n)."</b><br />";
				foreach ($check as $photo) {
					if ($photo->data["id"] != $keep->data["id"]) {
						echo "DELETING Duplicate: ".$photo->data["filename"]." (".$photo->getDimensions().")\n";
						$this->deletePhoto($photo);
					}
				}
				echo "\nKept:\n<div><a href=\"".$keep->getURL()."\" target=\"_BLANK\"><img src=\"".$keep->getURL()."&height=150\"><br />".$keep->getDimensions()."</a><br /></div></div>";
				$cnt++;
				if ($cnt >= $count) break;
			}
		}
		$needMore = max(0, $count - $cnt);
		if ($needMore) $this->similarityScan($needMore);
		return $cnt;
	}
	
	function resetAll() {
		$this->conn->query("UPDATE photos SET fp='', similar='', dup=0, lastSimScan=0, inGroup=0");
	}
	
	function fingerprintPhotos($count = 100) {
		if ($res = $this->conn->select("photos","fp is null or fp = ''",array("limit"=>$count))) {
			$cnt = 0;
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				$photo = new fspotPhoto($row,$this);
				$fp = $photo->getFingerprint();
				if ($fp) {
					$fp = explode(".",$fp);
				
					//echo $photo->data["id"]." <a href=\"".$photo->data["base_uri"]."/".$photo->data["filename"]."\">".implode(".",$fp)."</a><br />";
				
					$this->conn->update("photos",array("fp"=>$fp[0],"fp0"=>$fp[1],"fp1"=>$fp[2],"fp2"=>$fp[3],"fp3"=>$fp[4]),array("id"=>$row["id"]));
					$cnt++;
				} 
			}
		}
		return $cnt;
	}
	
	function getAbsolutePath($row) {
		return urldecode(str_replace("file:///","/",$row["base_uri"]."/".$row["filename"]));
	}
	
	function fixIncorrectExtensionCase($checkForBetterVersion = false) {
		$results = $this->conn->select("photos");
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
			$file = $this->getAbsolutePath($row);
			if ($checkForBetterVersion || !is_file($file)) {
				list($newFile,$ext) = explode(".",$file);
				
				$check = array();
				$oppFile = $newFile.".".($ext == strtolower($ext) ? strtoupper($ext) : strtolower($ext));
				if (is_file($oppFile)) $check[] = $oppFile;
				$i = 1;
				while (1) {
					if (($f = $file." (Case Conflict ".$i.")") && is_file($f)) {
						$check[] = $f;
						$i++;
						continue;
					}
					if (($f = $oppFile." (Case Conflict ".$i.")") && is_file($f)) {
						$check[] = $f;
						$i++;
						continue;
					}
					break;
				}
					
				if ($check) {
					$keep = "";
					$best = 0;
					foreach ($check as $newFile) {
						if (filesize($newFile) > $best) {
							$keep = $newFile;
							$best = filesize($newFile);
						}
					}
					echo "Renaming\n\t".$keep." to\n\t".$file."\n";
					rename($keep,$file);
					foreach ($check as $newFile) {
						if ($newFile != $keep) {
							echo "Deleting ".$newFile."\n";
							unlink($newFile);
						}
					}
					echo "\n";
				}			
				/*if (is_file($newFile.".".$ext)) {
					rename($newFile.".".$ext,$file);
				}*/
			}
		}
	}
}

class fspotPhoto {
	public $data;
	private $fspot;
	
	function __construct($data,$fspot = false) {
		$this->fspot = $fspot;
		$data["absolutePath"] = urldecode(str_replace("file:///","/",$data["base_uri"]."/".$data["filename"]));
		$this->data = $data;
	}
	
	function clearTag($tag) {
		if (!$this->fspot) return false;
		if ($tag_id = $this->fspot->getTagId($tag)) {
			$this->fspot->conn->query("DELETE FROM photo_tags where photo_id=".$this->data["id"]." and tag_id=".$tag_id);
			return true;
		}
		return false;
	}
	
	function setTag($tag) {
		if ($this->hasTag($tag)) return;
		if ($tag_id = $this->fspot->getTagId($tag)) {
			//echo "INSERT INTO photo_tags (photo_id, tag_id) values (".$this->data["id"].", ".$tag_id.")"; exit();
			$this->fspot->conn->query("INSERT INTO photo_tags (photo_id, tag_id) values (".$this->data["id"].", ".$tag_id.")");
		}
	}
	
	function hasTag($tag) {
		if ($tag_id = $this->fspot->getTagId($tag)) {
			if ($res = $this->fspot->conn->select("photo_tags",array("photo_id"=>$this->data["id"],"tag_id"=>$tag_id),array("limit"=>"1"))) return true; 			
		}		
		return false;
	}
	
	function getURL() {
		return "?ph=".$this->data["id"].".".array_pop(explode(".",$this->data["absolutePath"]));
	}
		
	function toGD() {
		if (!is_file($this->data["absolutePath"])) return false;
		
		$ext = strtolower(array_pop(explode(".",$this->data["absolutePath"])));
		switch ($ext) {
			case "jpg": case "jpeg":
				return imagecreatefromjpeg($this->data["absolutePath"]);
			
			case "png":
				return imagecreatefrompng($this->data["absolutePath"]);
			
			case "gif":
				return imagecreatefromgif($this->data["absolutePath"]);
		}
		return false;
	}
	
	function getFingerprint($size = 8) {
		$im = new slImage();
		$im->fromFile($this->data["absolutePath"]);
		return $im->getFingerprint($size);
	}
	
	function getValueAt($im, $x, $y) {
		$rgb = imagecolorat($im, $x, $y);
		return ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF));
	}
				
	function getDimensions($returnAsArray = false) {
		if ($dims = getQuickCache("photo-".$this->data["id"],$this->data["absolutePath"])) {
			return $returnAsArray ? array($dims[0],$dims[1]) : $dims[0]."x".$dims[1];
		}
		
		$im = $this->toGD();
		if (!$im) return false;
		$w = imagesx($im);
		$h = imagesy($im);
		imagedestroy($im);
		setQuickCache("photo-".$this->data["id"], array($w,$h));
		return $returnAsArray ? array($w,$h) : $w."x".$h;
	}
	
	function getAmountOfDetail($calcSharpness = false) {
		if ($calcSharpness) {
			$cache = new slCache("fspot-photo-sharp-".$this->data["id"]);
			if ($cache->isNewerThan(filemtime($this->data["absolutePath"]))) {
				return (int)$cache->get();
			} else {
				
				$im = $this->toGD();
				if (!$im) return 0;
				$w = imagesx($im);
				$h = imagesy($im);
				
				$step = 10;
				$detail = 0;
				for ($y = 0; $y < $h - 1; $y += $step) {
					for ($x = 0; $x < $w - 1; $x += $step) {
						$v = $this->getValueAt($im, $x, $y);
						$detail += abs($v - $this->getValueAt($im, $x + 1, $y)) + abs($v - $this->getValueAt($im, $x, $y + 1));
					}
				}
				imagedestroy($im);
				$detail = round(($detail / 1530) * $step * $step);
				$cache->set($detail);
				return $detail;
			}
		} else {
			$size = $this->getDimensions(true);
			return $size[0] * $size[1];
		}
	}
}
