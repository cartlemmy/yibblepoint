<?php

class slContact extends slClass {
	public $data;
	private $updated = array();
	public $notFound = true;
	public $inserted = false;
	public $creationType = "unknown";
	private $fieldRels = array(
		"sfID"=>array("relationship"=>"sf","table"=>"rem.contacts"),
		"assignedTo"=>array("relationship"=>"assignment","table"=>"user"),
		"createdBy"=>array("relationship"=>"creator","table"=>"user","readOnly"=>true)		
	);
	
	private $searchable;
	private $addressParts = array("address","type","lat","lng","timezone");
	private $addressSubParts = array("street","city","state","postalCode","country","address2");
	private $phoneParts = array("phone","type");
	private $logger = false;
	
	function __construct($contact = false, $insertIfNonExistant = true, $creationType = false, $forceUser = false) {
		if ($contact !== false) $this->findContact($contact, $insertIfNonExistant, $creationType, $forceUser);
	}
	
	function findContact($contact, $insertIfNonExistant = true, $creationType = false, $forceUser = false) {
		$this->searchable = array("emailPrimary","name","nameFirst","nameLast","organization","gender","creationType","macAddress");
		if ($creationType) $this->creationType = $creationType;
		
		if ($forceUser) {
			if (!isset($GLOBALS["_USER_MAP"])) $GLOBALS["_USER_MAP"] = array();
			if (isset($GLOBALS["_USER_MAP"][$forceUser])) {
				$GLOBALS["FORCE_USER"] = $GLOBALS["_USER_MAP"][$forceUser];
			} else {
				if ($res = $GLOBALS["slCore"]->db->select($GLOBALS["slConfig"]["user"]["table"],array("user"=>$forceUser,"_NO_USER"=>1))) {
					$user = $res->fetch();
					$GLOBALS["_USER_MAP"][$forceUser] = $GLOBALS["FORCE_USER"] = $user["id"];
				} else return $this->setNotFound();
			}
		}
		
		$this->selectContact($contact,$insertIfNonExistant);
		return true;
	}
	
	function __destruct() {
		$this->apply();
	}
	
	function selectContact($contact, $insertIfNonExistant = true) {
		$this->notFound = false;
		$where = false;
		$update = false;
		if (is_array($contact)) {
			if (setAndTrue($contact,"sfID")) {
				$this->log('sfID specified, searching for contact sfID:'.$contact["sfID"]);
				if ($res = $GLOBALS["slCore"]->db->select("db/contacts",array("sfID"=>$contact["sfID"],"_NO_USER"=>1))) {
										
					$this->data = $this->decodeRow($res->fetch());
					
					$this->log("\tFound via sfID:", self::NAME($this->data));
					
					if ($update) $this->update($contact);
					return true;
				}		
			}
			
			if (isset($contact["id"])) {
				$this->log('id specified, searching for contact id:'.$contact["id"]);
				$where = array("id"=>(int)$contact["id"]);
			} else {
				$update = true;
				$contact = $this->convertFields($contact, true);
				
				$where = array();
				foreach ($this->searchable as $n) {
					if (isset($contact[$n])) {
						$this->log($n.' specified');
						$where[$n] = $contact[$n];
					}
				}
				
				if (!$where) $this->setNotFound();
			}
		} elseif (is_numeric($contact))	{
			$where = array("id"=>(int)$contact);
		} elseif (is_string($contact) && trim($contact) != "") {
			$update = true;
			$contact = $where = array("emailPrimary"=>$contact);
		} else $this->setNotFound();	
		
		if ($where) {
			$where["_NO_USER"] = 1;
			if ($res = $GLOBALS["slCore"]->db->select("db/contacts",$where)) {	
				$this->data = $this->decodeRow($res->fetch());
				
				$this->log("\tFound:", self::NAME($this->data));
				
				if ($update) $this->update($contact);
			} elseif ($insertIfNonExistant && $update) {
				$contact["creationType"] = $this->creationType;
				
				$this->log("\tNot found, inserting");
				
				$id = $GLOBALS["slCore"]->db->insert("db/contacts",$contact);
				$this->log("\t\tInserted contact ID ".$id);
				$this->inserted = true;
				$res = $GLOBALS["slCore"]->db->select("db/contacts",array("id"=>$id));
				$this->data = $this->decodeRow($res->fetch());
			} else $this->setNotFound();	
		}
		return !$this->notFound;
	}
	
	private static function NAME($o) {
		return $o["name"]."(".$o["id"].")";
	}
	
	private function decodeRow($row) {
		foreach ($row as $n=>&$v) {
			switch ("".$n) {
				case "id":
					break;
					
				case "address":
					require_once(SL_INCLUDE_PATH.'/class.International.php');
					$int = new International();
					
					unset($row["address"]);
					$v = explode("\n",$v);
					foreach ($v as &$address) {
						$address = delimToObject($address,$this->addressParts);
						$address = array_merge($address, delimToObject($address["address"],$this->addressSubParts,','));
					
						$type = $address["type"];
						
						unset($address["type"]);
						unset($address["address"]);
							
						foreach ($address as $n2=>$v2) {
							switch ($n2) {
								case "state":
									$v2 = $int->toStateProvinceCode($v2, $address["country"]);
									break;
								
								case "country":
									$v2 = $int->toCountryCode($v2);
									break;
							}
							$n2 = toCamelCase($type.' '.$n2);
							if (!$row[$n2]) $row[$n2] = $v2;
						}
						unset($address);
					}
					break;
				
				case "phone":
					unset($row["phone"]);
					$v = explode("\n",$v);
					foreach ($v as &$phone) {
						$phone = delimToObject($phone,$this->phoneParts);
						$type = $phone["type"];
						
						unset($phone["type"]);
						foreach ($phone as $n2=>$v2) {
							$n2 = toCamelCase($type.' '.$n2);
							if (!$row[$n2]) $row[$n2] = $v2;
						}
						unset($phone);
					}
					break;
				
				case "name":
					require_once(SL_INCLUDE_PATH.'/class.slName.php');
					
					$name = new slName();
					$name->setName($row["name"], setAndTrue($row,"nameFormat") ? $row["nameFormat"] : false);
					
					foreach ($name->parts as $n2=>$v2) {					
						$row[$n2] = $v2;
					}					
					break;
					
			}
			unset($v);
		}
		return $row;
	}
	
	public function get($n = "*",$def = false) {
		if ($n == "*") return $this->data;
		return isset($this->data[$n]) ? $this->data[$n] : $def;
	}

	public function set($n, $v) {
		if (isset($this->data[$n]) && $this->data[$n] === $v) return false; // Nothing to update
		$this->data[$n] = $v;
		$this->updated[$n] = $v;
		return true;
	}
	
	public function apply() {
		if (count($this->updated)) {
			$this->update($this->updated);
			$this->updated = array();
		}
	}
	
	function setNotFound() { 
		$this->notFound = true;
		$this->data = array("id"=>0);
		return false;
	}
	
	function update($data, $noConvert = false) {
		if (isset($data["id"])) unset($data["id"]);

		if (setAndTrue($this->data,"id")) {
			if (!$noConvert) $data = $this->convertFields($data,true);
			
			foreach ($data as $n=>$v) {
				$this->data[$n] = $v;
			}
			
			$GLOBALS["slCore"]->db->update("db/contacts",$data,array("id"=>$this->data["id"],"_NO_USER"=>1));
			return true;
		}
		return false;
	}
	
	function convertFields($data,$transferNonCorrelated = false) {
		return $GLOBALS["slCore"]->db->convertFields("db/contacts",$data,$transferNonCorrelated);
	}
	
	//Organizations
	public function addOrganization($name) {
		$nameSafe = safeName($name);
		$orgs = $this->getOrganizations();
		if (!isset($orgs[$nameSafe])) {
			$orgs[$nameSafe] = array(
				"name"=>$name,
				"nameSafe"=>$nameSafe
			);
			$this->applyOrganizations($orgs);
		}
	}
	
	public function removeOrganization($name) {
		$nameSafe = safeName($name);
		$orgs = $this->getOrganizations();
		foreach ($orgs as $n=>$org) {
			if ($org["name"] == $name || $org["nameSafe"] == $nameSafe) {
				unset($orgs[$n]);
				$this->applyOrganizations($orgs);
			}
		}
	}
	
	private function applyOrganizations($orgs) {
		$a = array();
		foreach ($orgs as $org) {
			$a[] = $org["nameSafe"].";".$org["name"];
		}
		$this->data["organization"] = implode("\n",$a);
		$this->update(array("organization"=>$this->data["organization"]),true);
	}
	
	public function getOrganizations() {
		$rv = array();
		$orgs = trim($this->data["organization"]) ? explode("\n",$this->data["organization"]) : array();
		foreach ($orgs as $org) {
			list($nameSafe,$name) = explode(";",$org);
			if ($res = $GLOBALS["slCore"]->db->select("db/organizations",array("nameSafe"=>$nameSafe))) {
				$org = $res->fetch();
			} else $org = array(
				"_KEY"=>false,
				"name"=>$name,
				"nameSafe"=>$nameSafe
			);
			$rv[$nameSafe] = $org;
		}
		return $rv;
	}
	
	//User
	public function getUserId($user, $addUser = false) {
		if (is_numeric($user)) return $user;
		if (!is_array($user)) $user = strpos($user,'@') !== false ? array("email"=>$user) : array('user'=>$user);
		
		$where = array("_NO_USER"=>1);
		if (setAndTrue($user,"email")) {
			$where["email"] = $user["email"];
		} elseif (setAndTrue($user,"user")) {
			$where["user"] = $user["user"];
		} else return 0;
		
		
		if ($res = $GLOBALS["slCore"]->db->select("db/user",$where,array("select"=>"_KEY"))) {
			$user = $res->fetch();
			return $user["_KEY"];
		}
		
		if ($addUser && setAndTrue($user,"email")) {
			if (setAndTrue($user,"name")) {
				if (preg_match('/\"(.*?)\"/',$user["name"],$match)) {
					$user["user"] = $userStart = strtolower(preg_replace('/[^A-Za-z0-9]+/','.',$match[1]));
				} else {
					$name = explode(" ",$user["name"]);
					$user["user"] = $userStart = strtolower(array_shift($name).".".substr(array_pop($name),0,1));
				}
			} else {
				$user["user"] = $userStart = array_shift(explode("@",$user["email"]));
			}
			
			$cnt = 1;
			while ($GLOBALS["slCore"]->db->select("db/user",array('user'=>$user["user"],"_NO_USER"=>1))) {
				$user["user"] = $userStart.$cnt;
				$cnt++;
			}
			$token = slUser::generateToken(10,strtolower($user["email"]));
			
			$user["_NO_CONF_EMAIL"] = 1;
			$user["parentId"] = 1;
			$user["password"] = $token;
			$user["permissions"] = "user,session,office";
			
			return $GLOBALS["slCore"]->db->insert("db/user",$user);			
		}		
		
		return 0;
	}
	
	//Activity
	public function addActivity($type, $activity, $user = false, $addUser = false) {
		if (!is_array($activity)) $activity = array("text"=>$activity);
		$activity["type"] = $type;
		if (!setAndTrue($activity,"ts")) $activity["ts"] = time();
		$activity["contactId"] = $this->data["_KEY"];
		$activity["userId"] = $user ? $this->getUserId($user, $addUser) : 0;
		
		return $GLOBALS["slCore"]->db->insert("db/contactActivity",$activity);	
	}
	
	public function getActivity($where = array()) {
		$where["contactId"] = $this->data["_KEY"];
		$options = array("orderby"=>"ts");
		
		if (isset($where["i"])) {
			$options["limit"] = $where["i"].", 1"; unset($where["i"]);
		}

		if ($res = $GLOBALS["slCore"]->db->select("db/contactActivity",$where,$options)) {
			return $res->fetchAll();
		}
		return array();
	}
	
	public function updateActivity($where = array(), $activity, $user = false, $addUser = false) {
		$where["contactId"] = $this->data["_KEY"];
		$options = array("orderby"=>"ts");
		
		if (isset($where["i"])) {
			$options["limit"] = $where["i"].", 1"; unset($where["i"]);
			if ($res = $GLOBALS["slCore"]->db->select("db/contactActivity",$where,$options)) {
				$row = $res->fetch();
				$where = array("id"=>$row["id"]);
			} else return false;
		}
		
		if (!is_array($activity)) $activity = array("text"=>$activity);
		
		return $GLOBALS["slCore"]->db->update("db/contactActivity",$activity,$where);	
	}
	
	public function removeActivity($where = array()) {
		$where["contactId"] = $this->data["_KEY"];
		$GLOBALS["slCore"]->db->delete("db/contactActivity",$where);
	}
		
	//Relationships
	public function createRelationship($relationship,$where,$table = false,$updateExisting = false) {
		$this->log('createRelationship', array("relationship"=>$relationship, "where"=>$where, "table"=>$table, "updateExisting"=>$updateExisting));
		if ($relationship == "organization") return "ORG.".$this->addOrganization($where);
		
		if (substr($table,0,4) == "rem.") { //remote
			$data = array(
				"contactId"=>$this->data["_KEY"],
				"relationship"=>$relationship,
				"otherTable"=>$table,
				"otherId"=>$where
			);
		} else {
			foreach ($this->fieldRels as $field=>$rel) {
				if ($rel["relationship"] == $relationship) {
					if (setAndTrue($rel,"readOnly")) return false;
					$this->set($field,$where);
					return $field;
				}
			}
		
			if (!is_array($where)) $where["_KEY"] = $where;
			$where["_NO_USER"] = 1;
			if ($res = $GLOBALS["slCore"]->db->select("db/".$table,$where)) {
				$row = $res->fetch();
				
				$data = array(
					"contactId"=>$this->data["_KEY"],
					"relationship"=>$relationship,
					"otherTable"=>$table ? $table : "none",
					"otherId"=>$row["_KEY"]
				);
					
			} else return false;
		}
		if ($res = $GLOBALS["slCore"]->db->select("db/contactRelationship",$data)) {
			$row = $res->fetch();
			return $row["_KEY"];
		} else {
			if ($updateExisting) {
				$where = $data;
				unset($where["otherId"]);
				if ($res = $GLOBALS["slCore"]->db->select("db/contactRelationship",$where)) {
					$row = $res->fetch();
					$GLOBALS["slCore"]->db->update("db/contactRelationship", array("otherId"=>$data["otherId"]), array("id"=>$row["_KEY"]));
					return $row["_KEY"];
				}	
				
			}
			return $GLOBALS["slCore"]->db->insert("db/contactRelationship",$data);
		}
	}
	
	public function removeRelationship($relationship, $where = false, $table = false) {
		if ($relationship == "organization") return $this->removeOrganization($where);
		
		if (!$table || !$where) {
			if (substr($relationship,0,4) == "ORG.") return $this->removeOrganization(substr($relationship,4));
			$removeWhere = array("_KEY"=>(int)$relationship);
		} elseif ($table) {
			if (substr($table,0,4) == "rem.") { //remote
				$removeWhere = array(
					"relationship"=>$relationship,
					"otherTable"=>$table,
					"otherId"=>$where
				);
			} else {		
				if ($res = $GLOBALS["slCore"]->db->select("db/".$table,$where)) {
					$row = $res->fetch();
					$removeWhere = array(
						"relationship"=>$relationship,
						"otherTable"=>$table ? $table : "none",
						"otherId"=>$row["_KEY"]
					);
				}
			}
		}
		$removeWhere["_NO_USER"] = 1;
		$removeWhere["contactId"] = $this->data["_KEY"];
		
		$GLOBALS["slCore"]->db->delete("db/contactRelationship",$removeWhere);
		return true;
	}
	
	private function orgDecode($org) {
		$org = explode(";",$org);
		$name = array_pop($org);
		return array(
			"name"=>$name,
			"nameSafe"=>count($org) ? array_pop($org) : safeName($name)
		);
	}
	
	public function getRelationships($type = "*", $table = "*") {
		$rv = array();
		
		if ($type == "*" || $type == "organization") {
			$orgs = trim($this->data["organization"]) ? explode("\n",$this->data["organization"]) : array();
			foreach ($orgs as $org) {
				
				$org = $this->orgDecode($org);
				
				if ($res = $GLOBALS["slCore"]->db->select("db/organizations",array("nameSafe"=>$org["nameSafe"],"_NO_USER"=>1))) {
					$to = $res->fetch();
					
					if (!$to["parent"]) {
						$to["children"] = array();
						if ($res = $GLOBALS["slCore"]->db->select("db/organizations",array("parent"=>$to["id"],"_NO_USER"=>1))) {
							while ($child = $res->fetch()) {
								$to["children"][] = $child;
							}
						}
					}
					
					$rv["ORG.".$org["nameSafe"]] = array(
						"relationship"=>"organization",
						"to"=>$to
					);
				} else {
					$rv["ORG.".$org["nameSafe"]] = array(
						"relationship"=>"organization",
						"to"=>array(
							"_KEY"=>false,
							"table"=>"organization",
							"name"=>$name
						)
					);
				}
			}
		}
	
		foreach ($this->fieldRels as $field=>$rel) {
			if ($id = $this->get($field)) {
				if ($r2 = $GLOBALS["slCore"]->db->select("db/".$rel["table"],$id)) {
					$to = $r2->fetch();
					if (isset($to["privateKey"])) unset($to["privateKey"]);
				} else $to = $id;
				if ($type == "*" || $type == $rel["relationship"]) {
					$rv[$field] = array(
						"relationship"=>$rel["relationship"],
						"table"=>$rel["table"],
						"to"=>$to
					);
				}
			}
		}
		
		if ($res = $GLOBALS["slCore"]->db->select("db/contactRelationship",array("contactId"=>$this->data["_KEY"]),array("orderby"=>"id DESC"))) {
			while ($row = $res->fetch()) {				
				if (substr($row["otherTable"],0,4) == "rem.") { //remote
					$to = $row["otherId"];
				} else {
					if ($r2 = $GLOBALS["slCore"]->db->select("db/".$row["otherTable"],$row["otherId"])) {
						$to = $r2->fetch();
						if (isset($to["privateKey"])) unset($to["privateKey"]);
					} else $to = null;
				}
				
				if (($type == "*" || $type == $row["relationship"]) && ($table == "*" || $table == $row["otherTable"])) {
					$rv[$row["id"]] = array(
						"relationship"=>$row["relationship"],
						"table"=>$row["otherTable"],
						"to"=>$to
					);
				}
			}
		}
		return $rv;
	}
	
	public function log() {
		if ($this->logger) {
			$args = func_get_args();
			return call_user_func_array($this->logger, $args);
		}
		return false;
	}
	
	public function setLogger($cb) {
		$this->logger = $cb;
	}
}
