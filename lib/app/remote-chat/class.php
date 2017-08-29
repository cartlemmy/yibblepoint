<?php

class slChat extends slAppClass {
	private $setup = false;
	private $historyPath;
	
	function __construct($app) {
		$this->app = $app;
		$this->fileData = $GLOBALS["slSession"]->getParentFileData();
		$this->historyFile = realpath(dirname(__FILE__))."/history/".date("Y-m-d");
		
		if (!$GLOBALS["slSession"]->get("remoteChatInit")) {
			$this->logHistory("init");
			$GLOBALS["slSession"]->set("remoteChatInit",1);
		}
		
		if (isset($this->app->args[0])) {
			
		}
	}
	
	function logHistory($action,$o = array()) {
		$o["user"] = $GLOBALS["slSession"]->getParentUser();
		$o["action"] = $action;
		$o["ts"] = time();
		$o["ip"] = $_SERVER["REMOTE_ADDR"];
		file_put_contents($this->historyFile,json_encode($o)."\n",FILE_APPEND);
	}
	
	function chatAvailable() {
		foreach ($GLOBALS["slSession"]->userStatus as $user=>$s) {
			if ($s["active"] && $s["chatAvailable"]) return true;
		}
		return false;
	}
	
	function getInfo() {
		return array(
			"email"=>$GLOBALS["slSession"]->getUserData("email"),
			"name"=>$GLOBALS["slSession"]->getUserData("name"),
			"available"=>!!$this->chatAvailable(),
			"isAdmin"=>!!$GLOBALS["slSession"]->isLoggedIn()
		);
	}
	
	function chatRequest($info) {
		$this->logHistory("request",array(
			"requestedBy"=>$info["name"],
			"requestedByEmail"=>$info["email"]
		));

		if (isset($info["email"])) {
			if ($res = $GLOBALS["slCore"]->db->select("db/contacts",array("email"=>$info["email"]))) {
				$contact = $res->fetch_assoc();
				if (isset($info["name"]) && trim($contact["name"]) == "") {
					$contact["name"] = $info["name"];
					$GLOBALS["slCore"]->db->update("db/contacts",array("name"=>$info["name"]),array("id"=>$contact["id"]));
				}
				$this->init($contact);
				$GLOBALS["slSession"]->set("contactID",$contact["id"]);
				$GLOBALS["slSession"]->setUserData("contactID",$contact["id"]);
			} else {
				$this->init($info);
				$GLOBALS["slSession"]->set("contactID",
					$GLOBALS["slCore"]->db->insert("db/contacts",array("email"=>$info["email"],"name"=>$info["name"],"created"=>time(),"creationType"=>"web-chat"))
				);
			}
		}
		$req = array(
			"user"=>$GLOBALS["slSession"]->getUserName(),
			"contactID"=>$GLOBALS["slSession"]->get("contactID"),
			"email"=>$GLOBALS["slSession"]->getUserData("email"),
			"name"=>$GLOBALS["slSession"]->getUserData("name"),
			"answeredBy"=>false,
			"requested"=>time(),
			"status"=>"unanswered"
		);
		
		$GLOBALS["slCore"]->dispatch("!chat-available:remote-chat-request",$req);
		
		$this->fileData->set("remote-chat",$GLOBALS["slSession"]->getUserName(),$req);
		
		return $req;
	}
	
	function answered($user) {
		$this->logHistory("answered",array("answeredBy"=>$user));
		$data = $this->fileData->get("remote-chat",$GLOBALS["slSession"]->getUserName());
		$data["answeredBy"] = $user;
		$data["answered"] = time();
		$data["status"] = "answered";
		$this->fileData->set("remote-chat",$GLOBALS["slSession"]->getUserName(),$data);
		
		$GLOBALS["slCore"]->dispatch("!chat-available:remote-chat-answered",$data);
	}
	
	function init($info) {
		$GLOBALS["slSession"]->setUserData("email",$info["email"]);
		$GLOBALS["slSession"]->setUserData("name",$info["name"]);
	}
	
	function disconnect() {
		$GLOBALS["slCore"]->dispatch("!chat-available:remote-chat-disconnect",array(
			"user"=>$GLOBALS["slSession"]->getUserName()
		));
		$this->fileData->set("remote-chat",$GLOBALS["slSession"]->getUserName(),NULL);
	}
}
