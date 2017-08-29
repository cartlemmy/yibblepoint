<?php

class slCreditManager extends slClass {
	private $user = null; 
		
	function __construct($user) {
		if (!is_array($user)) {
			if ($res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],is_numeric($user) ? array("id"=>$user) : array("user"=>$user))) {
				$user = $res->fetch_assoc();
			}
		}
		$this->user = $user;
	}
	
	function transaction($name,$amount,$relatedId = "") {
		$ts = time();
		
		if ($GLOBALS["slCore"]->db->insert("db/creditTransactions",array(
			"user"=>$this->user["user"],
			"ts"=>$ts,
			"amount"=>$amount,
			"fingerprint"=>sha1($this->user["privateKey"]."-".$ts."-".$amount."-".$relatedId),
			"name"=>$name,
			"relatedId"=>$relatedId
		))) {
			$this->calculateCredits();
			return true;
		}		
		return false;
	}
	
	function calculateCredits() {
		if ($res = $GLOBALS["slCore"]->db->select("db/creditTransactions",array("user"=>$this->user["user"]))) {
			$this->user["credits"] = 0;
			while ($transaction = $res->fetch_assoc()) {
				$this->user["credits"] += $transaction["amount"];
			}
			$GLOBALS["slCore"]->db->update($GLOBALS["slConfig"]["user"]["table"],array("credits"=>$this->user["credits"]),array("id"=>$this->user["id"]));
			$GLOBALS["slCore"]->dispatch($this->user["user"].":user-credits",$this->user["credits"]);
		}
	}
}
