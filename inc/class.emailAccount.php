<?php

class emailAccount extends slClass {	
	private $data = array();
	private $key = null;
	private $conn = null;
	
	function __construct($id = false) {
		if (is_array($id)) {
			$this->setData($id);
		} elseif ($id !== false) {
			if ($res = $GLOBALS["slCore"]->db->select("db/mailAccounts",array("id"=>(int)$id,"_NO_USER"=>1))) {
				$data = $GLOBALS["slCore"]->db->fetch("db/mailAccounts",$res);
				$this->setData($data);
			}
		}
	}
	
	function __destruct() {
		$this->closeAndExpunge();
	}
	
	function getPassword() {
		return substr(base64_encode(sha1($this->key."-".$this->data["user"]."-".$this->data["domain"],true)),0,20);
	}
	
	function create($setup) {
		if (isset($GLOBALS["slConfig"]["cpanel"])) {
			$setup["name"] = $setup["user"]."@".$setup["domain"];
			
			require_once(SL_INCLUDE_PATH."/class.cPanel.php");
			
			$cpanel = new cPanel(1);

			$this->setData($setup);

			$res = $cpanel->query("Email", "addpop", array($setup["user"], $this->getPassword(), "20", $setup["domain"]));
			
			if ($res && (!isset($res["error"]) || (isset($res["data"]["result"]) && strpos($res["data"]["result"],"already exists") !== false))) {
				$setup["id"] = $GLOBALS["slCore"]->db->insert("db/mailAccounts",$setup);
			
				$this->setData($setup);
			}
		}
	}
	
	function get($n) {
		return isset($this->data[$n]) ? $this->data[$n] : null;
	}
	
	function setData($data) {
		if (!$this->key) {
			if (isset($data["key"])) {
				$this->key = $data["key"];
				unset($data["key"]);
			} else {
				if ($res = $GLOBALS["slCore"]->db->select("db/user",array("id"=>$data["childUserId"]))) {
					$user = $res->fetch_assoc();
					$this->key = $user["childUserId"];
				}
			}
		}
		$this->data = $data;
	}
	
	function isDataSet($n) {
		return isset($this->data[$n]) && $this->data[$n];
	}
	
	function open($mailbox = false) {
		$this->closeAndExpunge();
		if ($this->conn = imap_open(
			$this->getRef(true).($mailbox?$mailbox:""),
			$this->data["user"]."+".$this->data["domain"],
			$this->getPassword()
		)) return true;
		return false;		
	}
	
	function closeAndExpunge() {
		if ($this->conn) {
			imap_expunge($this->conn);
			imap_close($this->conn);
		}
	}
	
	function getRef($includeFlags = false) {
		return "{".$this->data["inboundServer"].($this->isDataSet("inboundPort")?":".$this->data["inboundPort"]:"").
		($includeFlags && $this->isDataSet("flags")?"/".$this->data["flags"]:"")."}";
	}
	
	function getMailBoxes($pattern = "*") {
		return imap_getmailboxes($this->conn,$this->getRef(true),$pattern = "*");
	}
	
	function numMsg() {
		return imap_num_msg($this->conn);
	}
	
	function mailMove($msglist, $mailbox, $options = 0) {
		return imap_mail_move($this->conn, $msglist, $mailbox, $options);
	}
	
	function mailDelete ($msg_no, $options = 0) {
		return imap_delete($this->conn, $msg_no, $options);
	}
	
	function headerInfo($msg_no, $fromlength = 0, $subjectlength = 0, $defaulthost = NULL) {
		return imap_headerinfo($this->conn, $msg_no, $fromlength, $subjectlength, $defaulthost);
	}
	
	function getTextBody($msg_no) {
		$structure = imap_fetchstructure($this->conn,$msg_no);
		$body = "";
		foreach ($structure->parts as $section=>$part) {
			if ($part->subtype == "PLAIN") {
				return imap_qprint(imap_fetchbody($this->conn,$msg_no,$section+1));
			} elseif ($part->subtype == "HTML") {
				$body = strip_tags(preg_replace("/[\s]+/"," ",imap_qprint(imap_fetchbody($this->conn,$msg_no,$section+1))));
			}
		}
		return $body;
	}
}
