<?php

require_once(SL_INCLUDE_PATH."/class.slRemote.php");

if (isset($_POST["instance"]) && isset($_POST["transactions"])) {

	$res = new slRemote();

	ob_start();

	$transactions = json_decode($_POST["transactions"],true);
	$verified = array();

	foreach ($transactions as $transaction) {
		if ($GLOBALS["slCore"]->db->select("db/reportedTransactions",array("remoteId"=>$transaction["id"],"instance"=>$_POST["instance"]),array("limit"=>"1"))) {
			$verified[] = $transaction["id"];
		} else {
			if ($GLOBALS["slCore"]->db->insert("db/reportedTransactions",array(
				"remoteId"=>$transaction["id"],
				"instance"=>$_POST["instance"],
				"user"=>$transaction["user"],
				"ts"=>$transaction["ts"],
				"amount"=>$transaction["amount"],
				"name"=>$transaction["name"]
			))) $verified[] = $transaction["id"];
		}	
	}

	$res->respond(array(
		"res"=>ob_get_clean(),
		"verified"=>$verified
	));

}
