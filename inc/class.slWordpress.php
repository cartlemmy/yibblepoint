<?php

class slWordpressQuery {
	private $config;
	private $db;
	
	function __construct($config = false) {
		$this->config = $config;
		$this->db = new mysqli($config["DB_HOST"], $config["DB_USER"], $config["DB_PASSWORD"], $config["DB_NAME"]);
		
		if ($this->db->connect_error) {
			die('Connect Error (' . $this->db->connect_errno . ') '. $this->db->connect_error);
		}
		
		if ($config["DB_CHARSET"]) $this->db->set_charset($config["DB_CHARSET"]);
		if ($config["DB_COLLATE"]) $this->db->query("SET collation_connection = ".$config["DB_COLLATE"]);
		
		$GLOBALS["WP_DB"] = $this->db;
	}
	
	function query($query) {
		return $this->db->query($query);
	}
	
	function __destruct() {
		//$this->db->close();
	}
	
	function fetchPost($where, $options = "") {
		$where = array_merge(array(
			"post_type"=>"page",
			"post_status"=>"publish"
		),$where);
		
		if ($res = $this->db->query("SELECT * FROM `".$this->_T("posts")."` WHERE ".$this->buildWhere($where)." ".$options)) {
			if ($post = $res->fetch_assoc()) {
				$post["meta"] = array();
				if ($r2 = $this->db->query("SELECT * FROM `".$this->_T("postmeta")."` WHERE `post_id`=".$post["ID"])) {
					while ($meta = $r2->fetch_assoc()) {
						$post["meta"][$meta["meta_key"]] = $meta["meta_value"];
					}
				}
				return new slWordpressPost($post);
			}
		}
		return false;
	}
	
	function _T($n) {
		return $this->config["table_prefix"].$n;
	}
	
	function buildWhere($where, $delim = " AND ") {
		$rv = array();
		foreach ($where as $n=>$v) {
			if ($v !== null) {
				if (is_string($v)) {
					$rv[] = "`$n`='".$this->db->real_escape_string($v)."'";
				} elseif (is_numeric($v)) {
					$rv[] = "`$n`=".$v;
				}
			} 
		}
		return implode($delim, $rv);
	}
}

class slWordpressPost {
	private $data;
	function __construct($data) {
		$this->data = $data;
	}
	
	function get($n) {
		return $this->data[$n];
	}
	
	function getAll() {
		return $this->data;
	}
}
