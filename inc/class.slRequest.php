<?php

class slRequest extends slClass {
	private $core;
	private $response = null;
	private $format = "json";	
	private $subType = "";
	private $table = "";
	private $row = "";
	private $request;
	
	function __construct($request, $core = false) {	
		$this->core = $core ? $core : $GLOBALS["slCore"];
		$this->request($request);
	}
	
	function setRef($request) {
		$this->request = $request;
		if (typeof($request) == "string") $request = array("path"=>$request);
		list($this->subType,$this->table,$this->row) = explode("/",$request["path"]);
		$this->row = explode(".",$this->row, 2);
		$this->format = count($this->row) > 1 ? array_pop($this->row) : "html";
	}
	
	function request($request) {
		$this->setRef($request);
				
		$permissions = new slPermissions($this->subType."/".$this->table, $this->core);

		if (!$permissions->can("read")) {
			$this->setResponse(false, $permissions->getLastResponse());
			return false;
		}
		
		if ($res = $this->core->db->selectByKey($this->subType."/".$this->table,$this->row[0])) {
			$response = array();
			while ($r = $res->fetch_assoc()) {
				$response[] = $permissions->processRow($r);
			}
			$this->setResponse(true, $response);
			return true;
		}
		
		$this->setResponse(false, "No results.");
		return false;
	}
	
	function setResponse($success, $response) {
		if ($success) {
			$this->response = array(
				"success"=>1,
				"response"=>$response
			);
		} else {
			$this->response = array(
				"success"=>0,
				"error"=>$response
			);
		}
	}
	
	function getResponse() {
		return $this->response;
	}
	
	function output() {
		switch ($this->format) {
			case "json":
				echo json_encode($this->response);
				break;
			
			case "ser":
				echo serialize($this->response);
				break;
				
			case "html":
				require_once(SL_INCLUDE_PATH."/class.slWeb.php");
				//It's a web page, render it
				$web = new slWeb(null, $GLOBALS["slConfig"]["web"]);
				
				$web->setContent("<pre>".print_r($this->response,true)."</pre>");
				
				$web->render();
				break;
				
			case "qr.png":
				requireThirdParty("phpqrcode");
				$link = "http://".$_SERVER["SERVER_NAME"].$this->request["docParent"].array_shift(explode(".qr.png",$this->request["uri"]));
				QRcode::png($link, false, 'L', 6, 4);
				break;
		}
	}
	
	function set($i,$n,$v) {
		if (is_array($i)) $i = $this->getItemIndexWhere($i);
		if ($i == -1) return false;
		
		$this->response["response"][$i][$n] = $v;
		
		$this->core->db->updateByKey($this->subType."/".$this->table,$this->response["response"][$i][$this->core->db->getTableKey($this->subType."/".$this->table)],array($n=>$v));
		
		return true;
	}
	
	function getItemIndexWhere($a) {
		$res = -1;
		foreach ($a as $n=>$v) {
			foreach ($this->response["response"] as $i=>$data) {
				if ($data[$n] == $v) {
					if ($res != -1 && $res != $i) return -1;
					$res = $i;
				}
			}
		}
		return $res;
	}
}
