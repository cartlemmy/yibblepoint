<?php

require_once(dirname(__FILE__)."/class.promoCode.php");

class db_promoCode extends slDBDefinition {	
	public function update_maxUses($v,&$items,$tableInfo) {
		$this->updatePromoCodeInstances($items);
	}
	
	public function updatePromoCodeInstances(&$item) {
		$curCnt = 0;
		if (isset($item["id"]) && $item["type"] == 1) {
			if ($r = $GLOBALS["slCore"]->db->query("db/promoCodeInstance","SELECT COUNT(*) AS 'cnt' FROM `promoCodeInstance` WHERE `promoCode`=".$item["id"])) {
				$pci = $r->fetch();
				$curCnt = $pci["cnt"];
			}
			$item["maxUses"] = max($item["maxUses"],$curCnt);
			for ($i = $curCnt; $i < $item["maxUses"]; $i++) {
				$GLOBALS["slCore"]->db->insert("db/promoCodeInstance",array(
					"promoCode"=>$item["id"],
					"code"=>promoCode::generateCode($item["id"]),
					"num"=>$i					
				));
			}
		}
	}
	
	public function update_code($v,&$items,$tableInfo) {
		$items["codeNorm"] = self::normalizeCode($items["code"]);
		$items["privateKey"] = sha1(json_encode($items)."-".mt_rand(0,0x7FFFFFFF)."-".microtime(true));
	}
	
	public static function normalizeCode($code) {
		return preg_replace('/[^A-Z0-9]/','',strtoupper(strtr($code,"l1502","IISOZ")));
	}
	
	public function getDefinition() {
		return array(
			"coreTable"=>true,
			"name"=>"en-us|Promo Codes",
			"singleName"=>"en-us|Promo Code",
			"table"=>"db/promoCode",
			"key"=>"id",
			"displayName"=>array("item.code"),
			"nameField"=>"code",
			"orderby"=>"created",
			"userField"=>"userId",
			"required"=>"code",
			"fields"=>array(
				"type"=>array(
					"label"=>"en-us|Type",
					"type"=>"select",
					"indexIsValue"=>true,
					"options"=>array(
						"en-us|Reusable Code",
						"en-us|Unique Codes"
					)
				),
				"code"=>array(
					"label"=>"en-us|Code",
					"searchable"=>true,
					"cleaners"=>"trim"
				),
				"redirect"=>array(
					"label"=>"en-us|Redirect to URL",
					"type"=>"url"
				),
				"expiration"=>array(
					"label"=>"en-us|Expiration",
					"type"=>"date"
				),
				"maxUses"=>array(
					"label"=>"en-us|Max Uses",
					"type"=>"number",
					"dependency"=>array("id","type")
				),
				"uses"=>array(
					"label"=>"en-us|Uses",
					"type"=>"number",
					"readOnlyField"=>true
				),
				"active"=>array(
					"label"=>"en-us|Active",
					"type"=>"checkbox",
					"default"=>1
				)
			)
		);
	}
}
