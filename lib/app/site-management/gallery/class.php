<?php

require_once(SL_LIB_PATH."/app/view/class.php");

class slGalleryManage extends slView {
	function __construct(&$app) {
		$app->args[0] = "db/galleryImages";
		parent::__construct($app);
	}
	
	function getGalleries($parent = 0) {
		$rv = array();
		if ($res = $GLOBALS["slCore"]->db->select("db/gallery",array("parentGallery"=>$parent),array("select"=>array("_KEY","_NAME")))) {
			while ($row = $res->fetch()) {
				if ($children = $this->getGalleries($row["_KEY"])) $row["_CHILDREN"] = $children;
				$rv["G".$row["_KEY"]] = $row;
			}
		}
		return $rv;
	}
	
	function setGalleryId($id) {
		$where = array("galleryId"=>$id);
		$this->app->set("where",$where);
		$this->setup["where"] = $where;
		
		if ($res = $GLOBALS["slCore"]->db->select("db/gallery",array("id"=>$id),array("limit"=>1,"select"=>array("_NAME")))) {
			$row = $res->fetch();
			$this->dbi = getSlDbIndexer($this->setup);
			return array("cnt"=>$this->dbi->count(),"name"=>$row["_NAME"]);
		}
		return false;
	}
	
	function deleteGallery($id) {
		return $GLOBALS["slCore"]->db->delete("db/gallery",array("id"=>$id),array("limit"=>1));
	}
	
	function addToGallery($galleryId,$image) {
		//TODO: check for duplicates
		return $GLOBALS["slCore"]->db->insert("db/galleryImages",array("galleryId"=>$galleryId,"image"=>$image));
	}
	
	function updateGalleryImage($id,$data) {
		return $GLOBALS["slCore"]->db->update("db/galleryImages",$data,array("id"=>(int)$id));
	}
	
	
}
