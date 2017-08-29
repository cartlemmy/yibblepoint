<?php

class slSupport {
	public static function add($data) {
		$info = $GLOBALS["slConfig"]["support"];
		
		$id = $GLOBALS["slCore"]->db->insert("db/supportTicket",array(
			"userId"=>$info["user"],
			"status"=>0,
			"assignedTo"=>$info["user"],
			"_NO_USER"=>1
		));
		
		$ticket = new slSupportTicket($id);
		$ticket->set($data);
		return $ticket;
	}
	
	public static function ticketFromID($id) {
		$ticket = new slSupportTicket($id);
		return $ticket;
	}
}

class slSupportTicket {
	private $data;
	private $updated = array();
	private $dbInfo;
	private $info;
	private $statuses = array();
	
	public function __construct($id) {
		$this->info = $GLOBALS["slConfig"]["support"];
		
		$this->dbInfo = $GLOBALS["slCore"]->db->getTableInfo("db/supportTicket");
		foreach ($this->dbInfo["fields"]["status"]["options"] as $n=>$v) {
			$this->statuses[$n] = translate($v);
		}

		if ($res = $GLOBALS["slCore"]->db->select("db/supportTicket",$id)) {
			$this->data = $res->fetch();	
		}
		if (!isset($this->data["parties"])) {
			$this->data["parties"] = array();
			$this->addParty(array(
				"name"=>$this->info["name"],
				"email"=>$this->info["email"]["address"],
				"user"=>$this->info["user"],
				"role"=>"support"
			));
		}
	}
	
	public function addParty($party) {
		if (!setAndTrue($party,"user")) {
			require_once(SL_INCLUDE_PATH."/class.slContact.php");
			$contact = new slContact($party["email"],true,"support-ticket");
			$update = $party;
			unset($update["role"]);
			$contact->update($update);
			$party["contact"] = $contact->data["id"];
			$this->set("contact",$contact->data["id"]);
		}
		$this->updated["parties"] = true;
		$this->data["parties"][] = $party;
		return count($this->data["parties"]) - 1;
	}
	
	public function updateParty($party) {
		if (!($pos = $this->findParty($party["email"]))) {
			$pos = $this->addParty($party);
		}
		$this->data["parties"][$pos] = array_merge(
			$this->data["parties"][$pos],
			$party
		);
		$this->updated["parties"] = true;
	}
	
	public function findParty($email,$returnAsIndex = false) {
		foreach ($this->data["parties"] as $n=>$party) {
			$party["i"] = $n;
			if ($party["email"] == $email) return $returnAsIndex ? $n : $party;
		}
		return $returnAsIndex ? -1 : null;
	}
	
	
	public function get($n,$def = null) {
		switch ($n) {
			case "from":
				return array(
					"name"=>$this->data["from"],
					"email"=>$this->data["fromEmail"]
				);
		}
		return isset($this->data[$n]) ? $this->data[$n] : $def;
	}
	
	public function set($n,$v = null) {
		if (is_array($n)) {
			foreach ($n as $n2=>$v2) {
				$this->set($n2,$v2);
			}
			return;
		}
		
		switch ($n) {
			case "from":
				if (is_array($v)) {
					$this->data["from"] = $v["name"];
					$this->data["fromEmail"] = $v["email"];
					$this->updated["from"] = $this->updated["fromEmail"] = true;
				} else {
					$this->data["from"] = $v;
					$this->updated["from"] = true;
				}
				$this->updateParty(array("email"=>$this->data["fromEmail"],"name"=>$this->data["from"],"role"=>"requester"));
				break;
			
			default:
				if (!isset($this->data[$n]) || $this->data[$n] != $v) $this->updated[$n] = true;
				$this->data[$n] = $v;
				break;
		}
	}
	
	public function update($data) {
		if (!isset($data["from"])) return;
		
		if (!($from = $this->findParty($data["from"]))) return;
				
		$data["from"] = $from["i"];
		$data["role"] = $from["role"];	
		
		if (!setAndTrue($data,"role")) $data["role"] = "requester";
		if (!setAndTrue($data,"to")) $data["to"] = $data["role"] == "requester" ? "support" : "requester";
		
		$oldStatus = $this->get("status");
		$updates = $this->get("updates",array());
		foreach ($updates as $n=>&$v) {
			switch ($n) {
				case "status":
				if (($status = array_search($v,$this->statuses)) !== false) $v = $status;	
				$this->set("status",$v);
				break;
			}
		}
		if (!isset($data["ts"])) $data["ts"] = time();
		$updates[] = $data;
		$this->set("updates",$updates);

		$body = array();
		for ($i = count($updates) -1; $i >= 0; $i--) {
			$update = $updates[$i];
			$party = $this->data["parties"][$update["from"]];
			$body[] = "<b>".$party["name"]."</b><br><i>".date("n/j/Y g:ia",$update["ts"])."</i><br><quote>".$update["text"]."</quote>";
		}
		
		$this->message(
			array("role"=>$data["to"] == "support" ? "support" : "requester"),
			"TICKET ".$this->info["systemName"]." #".$this->data["_KEY"]."-".count($updates)." - ".$this->data["name"],
			implode("<hr>\n",$body)
		);
	}
	
	public function message($to,$subject,$body) {
		require_once(SL_INCLUDE_PATH."/class.slCommunicator.php");
		$com = new slCommunicator();

		foreach ($this->data["parties"] as $party) {
			foreach ($to as $n=>$v) {
				if (!isset($party[$n]) || $party[$n] != $v) continue(2);
			}
			$com->addRecipient($party["email"],$party["name"]);
		}
		
		$com->setSubject($subject);
		$com->setMessage($body,true);
		
		$com->send();
	}
	
	public function attach($file,$name = false) {
		if (!$name) $name = array_pop(explode("/",$file));
		$GLOBALS["slCore"]->db->attach("db/supportTicket",array($name=>$file),array("_KEY"=>$this->data["_KEY"]));
	}
	
	public function commit() {
		if (count($this->updated)) {
			$upd = array();
			foreach ($this->updated as $n=>$y) {
				$upd[$n] = $this->data[$n];
			}
			$GLOBALS["slCore"]->db->update("db/supportTicket",$upd,array("_KEY"=>$this->data["_KEY"]));
			$this->updated = array();
		}
	}
}
