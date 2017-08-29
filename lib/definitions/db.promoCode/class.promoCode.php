<?php

require_once(SL_INCLUDE_PATH."/class.slRestAPI.php");

class promoCode {
	
	const CHARS = '346789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	
	public static function generateCode($promoCode, $len = 7) {
		$j = 0;
		do {
			$rv = "";
			for ($i = 0; $i < $len; $i++) {
				$rv .= substr(self::CHARS,mt_rand(0,31),1);
			}
			
			if (!$GLOBALS["slCore"]->db->select("db/promoCodeInstance",array("code"=>$rv,"promoCode"=>$promoCode))) {
				return $rv;
			}
			$j++;
		} while ($j < 1000000);
		return false;
	}
}

class promoCodeAPI extends slRestAPI {
	private function getPromoCode($params) {
		if (!isset($params["id"])) $this->fail("Promo code 'id' not specified");
		if ($r = $GLOBALS["slCore"]->db->select("db/promoCode",array("id"=>(int)$params["id"]))) {
			return $r->fetch();			
		} else $this->fail("Invalid promo code ID");
	}
	
	public function doGenerateCodes($params,$ret = false) {
		if (!(isset($params["count"]) && (int)$params["count"] > 0)) $this->fail("Promo code 'count' not specified");
		$promoCode = $this->getPromoCode($params);
		$codes = array();
		
		$num = 0;
		if ($r = $GLOBALS["slCore"]->db->query("db/promoCodeInstance","SELECT `num` FROM `promoCodeInstance` WHERE `promoCode`=".$promoCode["id"]." ORDER BY `num` DESC LIMIT 1")) {
			$highCode = $r->fetch();
			$num = $highCode['num'] + 1;
		}
				
		for ($i = 0; $i < (int)$params["count"]; $i++) {
			$code = array(
				"promoCode"=>$promoCode["id"],
				"num"=>$num,
				"code"=>promoCode::generateCode($promoCode["id"])
			);
			$code["id"] = $GLOBALS["slCore"]->db->insert("db/promoCodeInstance",$code);
			$codes[] = $code;
			$promoCode["maxUses"] ++;
			$num ++;
		}
		
		if ($ret) return $codes;
		$this->suc($codes);
	}
	
	public function doGenerateAndAssignCodes($params) {
		$this->doAssignCodes($params, true);
	}
	
	public function doAssignCodes($params, $generateIfMissing = false) {
		if (isset($params["count"]) && (int)$params["count"] > 0) {
			$codes = array();
			for ($i = 0; $i < (int)$params["count"]; $i++) {
				$codes[] = array();
			}
		} elseif (isset($params["toAttendees"])) { 
			$attendees = explode(" ",$params["toAttendees"]);
			$codes = array();
			foreach ($attendees as $id) {
				$codes[] = array("attendee"=>$id);
			}
		} elseif (isset($params["toContacts"])) { 
			$contacts = explode(" ",$params["toContacts"]);
			$codes = array();
			foreach ($contacts as $id) {
				$codes[] = array("contact"=>$id);
			}
		} elseif (isset($params["codes"])) {
			if (is_array($params["codes"])) {
				$codes = $params["codes"];
			} else $this->fail("Parameter 'codes' must be of type Array");
		} else $this->fail("Parameter 'codes' not specified");
		
		$cnt = 0;
		$codesCount = count($codes);
		
		if ($r = $GLOBALS["slCore"]->db->select("db/promoCodeInstance",array("promoCode"=>(int)$params["id"],"assigned"=>0),array("limit"=>$codesCount))) {
			while ($code = $r->fetch()) {
				$codes[$cnt] = array_merge($code,$codes[$cnt]);
				$cnt++;
			}
		} 
		
		if ($generateIfMissing && $cnt < $codesCount) {
			$c = $this->doGenerateCodes(array("id"=>(int)$params["id"],"count"=>$codesCount - $cnt), true);
			foreach ($c as $code) {
				$codes[$cnt] = array_merge($code,$codes[$cnt]);
				$cnt++;
			}
		}		
		
		foreach ($codes as &$code) {
			$code["assigned"] = 1;
			$GLOBALS["slCore"]->db->update("db/promoCodeInstance",$code,array("id"=>$code["id"]));
		}
		
		$this->suc($codes);
	}
	
	public function getVerification($code) {
		if ($r = $GLOBALS["slCore"]->db->select("db/promoCode",array("id"=>(int)$code["promoCode"]))) {
			$pc = $r->fetch();
			return sha1("PROCO-VER-".$pc["privateKey"]."-".$code["id"]."-".$code["code"]."-".$code["used"]);
		}
		return false;
	}
	
	public function doCodeUse($params) {
		$status = $this->doCodestatus($params, true);
		if ($status["status"] == "ready") {
			$status["used"] = time();
			
			$GLOBALS["slCore"]->db->update("db/promoCodeInstance",array("used"=>$status["used"]),array("id"=>$status["id"]));
			
			$this->suc(array(
				"promoCode"=>$status["promoCode"],
				"code"=>$status["code"],
				"verification"=>$this->getVerification($status)
			));
		}
		$this->fail("Code status '".$status["status"]."'".(isset($status["message"]) ? "\n\t".$status["message"] : ""),array('status'=>$status["status"]));
	}
	
	public function doCodestatus($params, $ret = false) {
		if (!isset($params["code"])) $this->fail("Parameter 'code' not specified");
		if ($r = $GLOBALS["slCore"]->db->select("db/promoCodeInstance",array("code"=>$params["code"]),array("limit"=>1))) {
			$pci = $r->fetch();
			if ($r2 = $GLOBALS["slCore"]->db->select("db/promoCode",array("id"=>$pci["promoCode"]),array("limit"=>1))) {
				$pc = $r2->fetch();
				
				$pci["expires"] = (int)$pc["expires"];
				if ($pci["expires"] != 0 && time() > $pci["expires"]) {
					$pci["status"] = "expired";
				} elseif ($pci["startTs"] != 0 && time() < $pci["startTs"]) {
					$pci["status"] = "waiting";
				} elseif (!$pc["active"]) {
					$pci["status"] = "inactive";
				} elseif (!$pci["assigned"]) {
					$pci["status"] = "unassigned";
				} elseif ($pci["used"]) {
					$pci["status"] = "used";
				} else {
					$pci["status"] = "ready";
				}				
				
				$pci["active"] = !!$pc["active"];
				$pci["used"] = !!$pci["used"];
				$pci["assigned"] = !!$pci["assigned"];
			} else {
				$pci["status"] = "removed";
				$pci["expires"] = false;
				$pci["active"] = false;
				$pci["message"] = "Parent coupon has been removed";
			}
			
			return $this->suc($pci, $ret);
		} 
		return $this->suc(array(
			"status"=>"invalid",
			"expires"=>false,
			"assigned"=>false,
			"used"=>false,
			"active"=>false,
			"message"=>"Invalid coupon code"
		), $ret);
	}
}
