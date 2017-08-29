<?php

require_once(SL_INCLUDE_PATH.'/class.slName.php');

function db_contacts_setNamePart($part,$v,&$items,$tableInfo){
	if (preg_replace('/[^\w\d]+/','',$v) == "") return;
	//db_contacts_update($items["name"],$items,$tableInfo,false);
				
	$name = new slName();
	
	$name->setName($items["name"], setAndTrue($items,"nameFormat") ? $items["nameFormat"] : false);
	
	$name->set($part, $v);
	
	$items["name"] = $name->getFullName(); 
	$items["nameFormat"] = $name->getFormat(); 
}

function db_contacts_setAddressPart($type,$part,$v,&$items,$tableInfo,$origType = false){
	if (trim($v)) {
		if (!isset($GLOBALS['_int'])) {
			require_once(SL_INCLUDE_PATH.'/class.International.php');
			$GLOBALS['_int'] = new International();
		}
		
		switch ($part) {
			case "state":
				if (isset($items[toCamelCase(($origType?$origType:$type)."-country")])) {
					$v = $GLOBALS['_int']->toStateProvinceCode($v, $items[toCamelCase(($origType?$origType:$type)."-country")]);
				}
				break;
			
			case "country":
				$v = $GLOBALS['_int']->toCountryCode($v);
				break;
		}
				
		$address = isset($items["address"]) && trim($items["address"]) ? explode("\n",$items["address"]) : array();
		
		$exists = false;
		if (count($address)) {
			foreach ($address as &$a) {
				$a = decodeAddress($a);
				if ($a["type"] == $type) {
					$exists = true;
					$a[$part] = $v;
				}
			}
		}
		
		if (!$exists) {
			$address[] = array("type"=>$type,$part=>$v);
		}
		foreach ($address as &$a) {
			$a = encodeAddress($a);
		}
		$items["address"] = implode("\n",$address);
	}
}

function db_contacts_setPhone($type,$v,&$items,$tableInfo){
	if (trim($v)) {
		$phone = isset($items["phone"]) && trim($items["phone"]) ? explode("\n",$items["phone"]) : array();

		$exists = false;
		if (count($phone)) {
			foreach ($phone as &$a) {
				$p = explode(";",$a,2);
				$t = count($p) > 1 ? array_pop($p) : "main";
				$p = $p[0];
				
				$a = array("phone"=>rawurldecode($p),"type"=>$t);
				if ($a["type"] == $type) {
					$exists = true;
					$a["phone"] = $v;
				}
			}
		}
		
		if (!$exists) {
			$phone[] = array("type"=>$type,"phone"=>$v);
		}
		
		foreach ($phone as &$a) {
			$a = str_replace(";","%3B",$a["phone"]).";".$a["type"];
		}

		$items["phone"] = implode("\n",$phone);
	}
}

function db_contacts_update($v,&$items,$tableInfo,$doClear = true) {	
	if (preg_match('/(\<[^\>]+\>)/',$v,$match)) { //Name is an email address
		$items["email"] = substr($match[1],1,-1);
		$v = trim(str_replace($match[1],"",$v)," \t\n\r\0\x0B\"");
	}
	
	if (!trim($v)) {
		$items["namePrefix"] = $items["nameSuffix"] = $items["nameFirst"] = $items["nameMiddle"] = $items["nameLast"] = "";
		return;
	}	
		
	$name = new slName();
	
	$name->setName($v);
	
	$items["name"] = $name->getFullName(); 
	$items["nameFormat"] = $name->getFormat(); 
}

function db_contacts_update_namePrefix($v,&$items,$tableInfo) {
	db_contacts_setNamePart("namePrefix",$v,$items,$tableInfo);
}
	
function db_contacts_update_nameFirst($v,&$items,$tableInfo) {
	db_contacts_setNamePart("nameFirst",$v,$items,$tableInfo);
}		

function db_contacts_update_nameMiddle($v,&$items,$tableInfo) {
	db_contacts_setNamePart("nameMiddle",$v,$items,$tableInfo);
}

function db_contacts_update_nameLast($v,&$items,$tableInfo) {
	db_contacts_setNamePart("nameLast",$v,$items,$tableInfo);
}		

function db_contacts_update_nameSuffix($v,&$items,$tableInfo) {
	db_contacts_setNamePart("nameSuffix",$v,$items,$tableInfo);
}

function db_contacts_update_nameAlias($v,&$items,$tableInfo) {
	db_contacts_setNamePart("alias",$v,$items,$tableInfo);
}

function db_contacts_update_emailPrimary($v,&$items,$tableInfo) {
	$emails = setAndTrue($items,"email") ? explode("\n",trim($items["email"])) : array();
	if (($pos = array_search(strtolower($v),array_map('strtolower',$emails))) !== false) array_splice($emails,$pos,1);
	$v = explode(",",$v);
	array_unshift($emails,formatEmail($v[0]));
	$items["email"] = implode("\n",$emails);
}		

function db_contacts_update_email($v,&$items,$tableInfo) {
	$emails = explode("\n",trim($v));
	for ($i = 0; $i < count($emails); $i++) {
		if (trim($emails[$i])) {
			$emails[$i] = formatEmail($emails[$i]);
		} else {
			array_splice($emails,$i,1);
			$i--;
		}
	}
	if (isset($emails[0])) $items["emailPrimary"] = rawurldecode($emails[0]);
	$items["email"] = implode("\n",$emails);
}

function db_contacts_update_singleEmail($v,&$items,$tableInfo) {
	$v = explode(",",$v);
	for ($i = 0; $i < count($v); $i++) {
		$v[$i] = formatEmail($v[$i]);
		$emailSafe = strtolower($v[$i]);
		$emails = trim($items["email"]) ? explode("\n",$items["email"]) : array();
		foreach ($emails as $email) {
			if ($emailSafe == trim(strtolower($email))) return;
		}
		$emails[] = $v[$i];
	}
	$items["email"] = implode("\n",$emails);
}	

function db_contacts_update_address($v,&$items,$tableInfo) {
	if (trim($v)) {
		$v = explode("\n",$v);

		$v0 = decodeAddress($v[0]);

		if (isset($v0["lat"])) $items["lat"] = $v0["lat"];
		if (isset($v0["lng"])) $items["lng"] = $v0["lng"];
		if (isset($v0["timezone"])) $items["timezone"] = $v0["timezone"];
	}
}


function db_contacts_update_billingStreet($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","street",$v,$items,$tableInfo);
	unset($items["billingStreet"]);
}

function db_contacts_update_billingStreet2($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","street2",$v,$items,$tableInfo);
	unset($items["billingStreet2"]);
}

function db_contacts_update_billingCity($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","city",$v,$items,$tableInfo);
	unset($items["billingCity"]);
}

function db_contacts_update_billingState($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","state",$v,$items,$tableInfo,'billing');
	unset($items["billingState"]);
}

function db_contacts_update_billingPostalCode($v,&$items,$tableInfo) {
	$v = array_shift(explode("-",$v));
	db_contacts_setAddressPart("work","postalCode",$v,$items,$tableInfo);
	unset($items["billingPostalCode"]);
}

function db_contacts_update_billingCountry($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","country",$v,$items,$tableInfo);
	unset($items["billingCountry"]);
}

function db_contacts_update_businessStreet($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","street",$v,$items,$tableInfo);
	unset($items["businessStreet"]);
}

function db_contacts_update_businessStreet2($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","street2",$v,$items,$tableInfo);
	unset($items["businessStreet2"]);
}

function db_contacts_update_businessCity($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","city",$v,$items,$tableInfo);
	unset($items["businessCity"]);
}

function db_contacts_update_businessState($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","state",$v,$items,$tableInfo,'business');
	unset($items["businessState"]);
}

function db_contacts_update_businessPostalCode($v,&$items,$tableInfo) {
	$v = array_shift(explode("-",$v));
	db_contacts_setAddressPart("work","postalCode",$v,$items,$tableInfo);
	unset($items["businessPostalCode"]);
}

function db_contacts_update_businessCountry($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("work","country",$v,$items,$tableInfo);
	unset($items["businessCountry"]);
}

function db_contacts_update_homeStreet($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("home","street",$v,$items,$tableInfo);
	unset($items["homeStreet"]);
}

function db_contacts_update_homeStreet2($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("home","street2",$v,$items,$tableInfo);
	unset($items["homeStreet2"]);
}

function db_contacts_update_homeCity($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("home","city",$v,$items,$tableInfo);
	unset($items["homeCity"]);
}

function db_contacts_update_homeState($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("home","state",$v,$items,$tableInfo);
	unset($items["homeState"]);
}

function db_contacts_update_homePostalCode($v,&$items,$tableInfo) {
	$v = array_shift(explode("-",$v));
	db_contacts_setAddressPart("home","postalCode",$v,$items,$tableInfo);
	unset($items["homePostalCode"]);
}

function db_contacts_update_street($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("unknown","street",$v,$items,$tableInfo);
	unset($items["street"]);
}

function db_contacts_update_street2($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("unknown","street2",$v,$items,$tableInfo);
	unset($items["street2"]);
}

function db_contacts_update_homeCountry($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("home","country",$v,$items,$tableInfo);
	unset($items["homeCountry"]);
}

function db_contacts_update_city($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("unknown","city",$v,$items,$tableInfo);
	unset($items["city"]);
}

function db_contacts_update_state($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("unknown","state",$v,$items,$tableInfo,'');
	unset($items["state"]);
}

function db_contacts_update_postalCode($v,&$items,$tableInfo) {
	$v = array_shift(explode("-",$v));
	db_contacts_setAddressPart("unknown","postalCode",$v,$items,$tableInfo);
	unset($items["postalCode"]);
}

function db_contacts_update_country($v,&$items,$tableInfo) {
	db_contacts_setAddressPart("unknown","country",$v,$items,$tableInfo);
	unset($items["country"]);
}

function db_contacts_update_homePhone($v,&$items,$tableInfo) {
	db_contacts_setPhone("home",$v,$items,$tableInfo);
	unset($items["homePhone"]);
}

function db_contacts_update_workPhone($v,&$items,$tableInfo) {
	db_contacts_setPhone("work",$v,$items,$tableInfo);
	unset($items["workPhone"]);
}

function db_contacts_update_mainPhone($v,&$items,$tableInfo) {
	db_contacts_setPhone("main",$v,$items,$tableInfo);
	unset($items["mainPhone"]);
}

function db_contacts_update_mobilePhone($v,&$items,$tableInfo) {
	db_contacts_setPhone("mobile",$v,$items,$tableInfo);
	unset($items["mobilePhone"]);
}

function db_contacts_update_homeFax($v,&$items,$tableInfo) {
	db_contacts_setPhone("home-fax",$v,$items,$tableInfo);
	unset($items["homeFax"]);
}

function db_contacts_update_workFax($v,&$items,$tableInfo) {
	db_contacts_setPhone("work-fax",$v,$items,$tableInfo);
	unset($items["workFax"]);
}

function db_contacts_update_emergencyPhone($v,&$items,$tableInfo) {
	db_contacts_setPhone("emergency",$v,$items,$tableInfo);
	unset($items["emergencyPhone"]);
}

function db_contacts_update_gender($v,&$items,$tableInfo) {
	$convert = translate(array(
		"en-us|m"=>"male","en-us|man"=>"male",
		"en-us|f"=>"female","en-us|woman"=>"female"
	));
	$items["gender"] = strtolower($v);
	if (isset($convert[$items["gender"]])) $items["gender"] = $convert[$items["gender"]];
}

function db_contacts_update_age($v,&$items,$tableInfo) {
	$items["birthdate"] = strtotime("-".$items["age"]." years");
}

function db_contacts_update_birthMonth($v,&$items,$tableInfo) {
	if (trim($v) == "") return;
	$d = explode("-",date("H-i-s-n-j-Y",$items["birthdate"]));
	$items["birthdate"] = mktime($d[0],$d[1],$d[2],$d[3],$v,$d[5]);
}

function db_contacts_update_birthDay($v,&$items,$tableInfo) {
	if (trim($v) == "") return;
	if (strlen(trim($v)) > 2) {
		$items["birthdate"] = strtotime($v);
	} else {
		$d = explode("-",date("H-i-s-n-j-Y",$items["birthdate"]));
		$items["birthdate"] = mktime($d[0],$d[1],$d[2],$v,$d[4],$d[5]);
	}
}

function db_contacts_update_birthYear($v,&$items,$tableInfo) {
	if (trim($v) == "") return;
	$d = explode("-",date("H-i-s-n-j-Y",$items["birthdate"]));
	$items["birthdate"] = mktime($d[0],$d[1],$d[2],$d[3],$d[4],$v);
}

function db_contacts_update_singleOrganization($v,&$items,$tableInfo) {
	$newSafe = safeName($v);
	$organizations = trim($items["organization"]) ? explode("\n",$items["organization"]) : array();
	foreach ($organizations as $organization) {
		$n = explode(";",$organization,2);
		$safe = count($n) > 2 ? array_shift($n) : "";
		$n = $n[0];
		if ($newSafe == $safe) return;
	}
	if (!in_array($v,$organizations)) $organizations[] = $v;
	$items["organization"] = implode("\n",$organizations);
}

function db_contacts_update_macAddress($v,&$items,$tableInfo) {
	$macAddressList = strlen($items["macAddressList"]) ? explode("\n",$items["macAddressList"]) : array();
	if (!in_array($v,$macAddressList)) $macAddressList[] = $v;
	$items["macAddressList"] = implode("\n",$macAddressList);
}

return array(
	"coreTable"=>true,
	"name"=>"en-us|Contacts",
	"singleName"=>"en-us|Contact",
	"table"=>"db/contacts",
	"key"=>"id",
	"unique"=>"emailPrimary",
	"displayName"=>array("item.name","item.emailPrimary"),
	"orderby"=>"emailPrimary",
	"userField"=>"userId",
	"permissions"=>"crm OR admin",
	"namePrefixes"=>array(
		"ms","miss","mrs","mr","master","rev","reverend","fr","father","dr",
		"doctor","atty","attorney","prof","professor","hon","honorable","pres",
		"president","gov","governor","coach","ofc","officer","msgr","monsignor",
		"sr","sister","br","brother","supt","superintendent","rep","sir","sire",
		"representative","sen","senator","amb","ambassador","treas","treasurer",
		"sec","secretary","pvt","private","cpl","corporal","sgt","sargent",
		"adm","administrative","maj","major","capt","captain","cmdr",
		"commander","lt","lieutenant","lt col","lieutenant colonel","col",
		"colonel","gen","general"
	),
	"nameSuffixes"=>array(
		"jr","sr","phd","md","jd","do","pharmd","ab","ba","bfa","btech","llb",
		"bsc","ma","mfa","llm","mla","mba","msc","kbe","lld","dd","esq",
		"i","ii","iii","iv","v","vi","vii","viii","ix","x","qc","mp"
	),
	"setNamePart"=>"db_contacts_setNamePart",
	"setAddressPart"=>"db_contacts_setAddressPart",
	"setPhone"=>"db_contacts_setPhone",
	"import"=>array(
		"ignore"=>array("en-us|displayname")
	),
	"required"=>"name || email",
	"historyField"=>"history",
	"broadSearchField"=>"broadSearch",
	"duplicateConnections"=>array(
		array("businessStreet","businessCity","businessState","businessPostalCode"),
		array("homeStreet","homeCity","homeState","homePostalCode"),
		array("street","city","state","postalCode")
	),
	"nibbleIndex"=>array("name","email","organization","address","phone","groups"),
	"fields"=>array(
		"name"=>array(
			"label"=>"en-us|Name",
			"dependency"=>"nameFormat",
			"importNames"=>"en-us|name,fullname",
			"searchable"=>1,
			"cleaners"=>"trim,name",
			"viewable"=>false,
			"updateFunction"=>"db_contacts_update"
		),
		"namePrefix"=>array(
			"dependency"=>"name,nameFormat,nameFirst,nameLast",
			"label"=>"en-us|Name Prefix",
			"importNames"=>"en-us|prefix,salutation",
			"editable"=>false,
			"viewable"=>false,
			"updateFunction"=>"db_contacts_update_namePrefix"
		),
		"nameFirst"=>array(
			"label"=>"en-us|First Name",
			"dependency"=>"name,nameFormat,nameFirst,nameLast",
			"importNames"=>"en-us|firstname,namefirst,givenname,fname",
			"editable"=>false,
			"updateFunction"=>"db_contacts_update_nameFirst"
		),
		"nameMiddle"=>array(
			"label"=>"en-us|Middle Name",
			"dependency"=>"name,nameFormat,nameFirst,nameLast",
			"importNames"=>"en-us|middlename,namemiddle,mname,middleinitial",
			"editable"=>false,
			"viewable"=>false,
			"updateFunction"=>"db_contacts_update_nameMiddle"
		),
		"nameLast"=>array(
			"label"=>"en-us|Last Name",
			"dependency"=>"name,nameFormat,nameFirst,nameLast",
			"importNames"=>"en-us|lastname,namelast,surname,lname",
			"editable"=>false,
			"updateFunction"=>"db_contacts_update_nameLast"
		),
		"nameSuffix"=>array(
			"dependency"=>"name,nameFormat,nameFirst,nameLast",
			"importNames"=>"en-us|suffix",
			"label"=>"en-us|Name Suffix",
			"editable"=>false,
			"viewable"=>false,
			"updateFunction"=>"db_contacts_update_nameSuffix"
		),
		"alias"=>array(
			"label"=>"en-us|Alias",
			"dependency"=>"name,nameFormat,nameFirst,nameLast",
			"importNames"=>"en-us|alias,nickname",
			"editable"=>false,
			"import"=>true,
			"updateFunction"=>"db_contacts_update_nameAlias"
		),
		"jobTitle"=>array(
			"label"=>"en-us|Job Title",
			"importNames"=>"en-us|jobtitle,role,title",
			"import"=>true
		),
		"emailPrimary"=>array(
			"label"=>"en-us|Primary E-mail",
			"importNames"=>"en-us|primaryemail,mainemail",
			"dependency"=>"email",
			"searchable"=>true,
			"cleaners"=>"trim",
			"viewable"=>false,
			"editable"=>false,
			"validate"=>"email",
			"updateFunction"=>"db_contacts_update_emailPrimary"
		),
		"email"=>array(
			"label"=>"en-us|E-mail(s)",
			"searchable"=>true,
			"multi"=>true,
			"cleaners"=>"trim",
			"validate"=>"email",
			"updateFunction"=>"db_contacts_update_email"
		),
		"singleEmail"=>array(
			"label"=>"en-us|E-mail",
			"importNames"=>"en-us|secondaryemail,email,emailaddress,businessemail,workemail",
			"import"=>"emailPrimary",
			"dependency"=>"email",
			"updateFunction"=>"db_contacts_update_singleEmail"
		),		
		"address"=>array(
			"label"=>"en-us|Address(es)",
			"singleLabel"=>"en-us|Address",
			"type"=>"address",
			"multi"=>true,
			"searchable"=>true,
			"viewable"=>false,
			"cleaners"=>"trim",
			"updateFunction"=>"db_contacts_update_address"
		),
		
		"billingStreet"=>array(
			"label"=>"en-us|Mailing Street",
			"importNames"=>"en-us|mailingaddress,mailingaddr,mailingstreet,billingaddress,billingaddr,billingstreet",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_billingStreet"
		),
		"billingStreet2"=>array(
			"label"=>"en-us|Mailing Street 2",
			"importNames"=>"en-us|mailingaddress2,mailingaddr2,mailingstreet2,billingaddress2,billingaddr2,billingstreet2",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_billingStreet2"
		),
		"billingCity"=>array(
			"label"=>"en-us|Mailing City",
			"importNames"=>"en-us|mailingcity,billingcity",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_billingCity"
		),
		"billingState"=>array(
			"label"=>"en-us|Mailing State",
			"importNames"=>"en-us|mailingstate,mailingprovince,mailingstateprovince,billingstate,billingprovince,billingstateprovince",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_billingState"
		),
		"billingPostalCode"=>array(
			"label"=>"en-us|Mailing Postal Code",
			"importNames"=>"en-us|mailingpostalcode,mailingzip,mailingzippostalcode,billingpostalcode,billingzip,billingzippostalcode",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_billingPostalCode"
		),
		"billingCountry"=>array(
			"label"=>"en-us|Mailing Postal Country",
			"importNames"=>"en-us|mailingcountry,mailingcountryregion,billingcountry,billingcountryregion",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_billingCountry"
		),	
			
		"businessStreet"=>array(
			"label"=>"en-us|Business Street",
			"importNames"=>"en-us|businessaddress,businessaddr,businessstreet,workstreet,companystreet",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_businessStreet"
		),
		"businessStreet2"=>array(
			"label"=>"en-us|Business Street 2",
			"importNames"=>"en-us|businessaddress2,businessaddr2,businessstreet2,workstreet2,companystreet2",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_businessStreet2"
		),
		"businessCity"=>array(
			"label"=>"en-us|Business City",
			"importNames"=>"en-us|businesscity,workcity,companycity",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_businessCity"
		),
		"businessState"=>array(
			"label"=>"en-us|Business State",
			"importNames"=>"en-us|businessstate,workstate,companystate,businessprovince,workprovince,companyprovince,businessstateprovince,workstateprovince",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_businessState"
		),
		"businessPostalCode"=>array(
			"label"=>"en-us|Business Postal Code",
			"importNames"=>"en-us|businesspostalcode,workpostalcode,companypostalcode,businesszip,businesszippostalcode,workzip,workzippostalcode,companyzip,companyzippostalcode",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_businessPostalCode"
		),
		"businessCountry"=>array(
			"label"=>"en-us|Business Postal Country",
			"importNames"=>"en-us|businesscountry,workcountry,companycountry,businesscountryregion,workcountryregion,companycountryregion",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_businessCountry"
		),
		"homeStreet"=>array(
			"label"=>"en-us|Home Street",
			"importNames"=>"en-us|homestreet,homeaddress,homeaddr",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_homeStreet"
		),
		"homeStreet2"=>array(
			"label"=>"en-us|Home Street 2",
			"importNames"=>"en-us|homestreet2,homeaddress2,homeaddr2",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_homeStreet2"
		),
		"homeCity"=>array(
			"label"=>"en-us|Home City",
			"importNames"=>"en-us|homecity",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_homeCity"
		),
		"homeState"=>array(
			"label"=>"en-us|Home State",
			"importNames"=>"en-us|homestate,homeprovince,homestateprovince",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_homeState"
		),
		"homePostalCode"=>array(
			"label"=>"en-us|Home Postal Code",
			"importNames"=>"en-us|homepostalcode,homezip,homezippostalcode",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_homePostalCode"
		),
		"homeCountry"=>array(
			"label"=>"en-us|Home Country",
			"importNames"=>"en-us|homecountry,homecountryregion",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_homeCountry"
		),
		"street"=>array(
			"label"=>"en-us|Street",
			"importNames"=>"en-us|street,address,address1,addressline1",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_street"
		),
		"street2"=>array(
			"label"=>"en-us|Street 2",
			"importNames"=>"en-us|street2,address2,addressline2",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_street2"
		),
		"city"=>array(
			"label"=>"en-us|City",
			"importNames"=>"en-us|city",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_city"
		),
		"state"=>array(
			"label"=>"en-us|State",
			"importNames"=>"en-us|state,province,stateprovince,companystateprovince,usstatecaprovince",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_state"
		),
		"postalCode"=>array(
			"label"=>"en-us|Postal Code",
			"importNames"=>"en-us|postalcode,zip,zippostalcode",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_postalCode"
		),
		"country"=>array(
			"label"=>"en-us|Country",
			"importNames"=>"en-us|country,countryregion",
			"import"=>true,
			"dependency"=>"address",
			"updateFunction"=>"db_contacts_update_country"
		),
		"phone"=>array(
			"label"=>"en-us|Phone Number(s)",
			"singleLabel"=>"en-us|Phone Number",
			"type"=>"phone",
			"multi"=>true,
			"searchable"=>true,
			"viewable"=>false,
			"cleaners"=>"trim"
		),
		"homePhone"=>array(
			"label"=>"en-us|Home Phone",
			"importNames"=>"en-us|homephone",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_homePhone"
		),
		"workPhone"=>array(
			"label"=>"en-us|Work Phone",
			"importNames"=>"en-us|workphone,companyphone,businessphone",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_workPhone"
		),
		"mainPhone"=>array(
			"label"=>"en-us|Primary Phone",
			"importNames"=>"en-us|phone,mainphone,primaryphone",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_mainPhone"
		),
		"mobilePhone"=>array(
			"label"=>"en-us|Mobile Phone",
			"importNames"=>"en-us|mobilephone,mobilenumber,cell,cellnumber,cellphone,homecell",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_mobilePhone"
		),
		"homeFax"=>array(
			"label"=>"en-us|Home Fax",
			"importNames"=>"en-us|homefax",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_homeFax"
		),
		"workFax"=>array(
			"label"=>"en-us|Work Fax",
			"importNames"=>"en-us|fax,faxnumber,workfax,businessfax,companyfax",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_workFax"
		),
		"emergencyPhone"=>array(
			"label"=>"en-us|Emergency Phone",
			"importNames"=>"en-us|emergencyphone,emergencynumber,emergencycontactnumber",
			"import"=>true,
			"dependency"=>"phone",
			"updateFunction"=>"db_contacts_update_emergencyPhone"
		),
		"gender"=>array(
			"label"=>"en-us|Gender",
			"importNames"=>"en-us|gender,sex",
			"type"=>"select",
			"viewable"=>false,
			"options"=>array(
				""=>"en-us|Unspecified",
				"male"=>"en-us|Male",
				"female"=>"en-us|Female",
			),
			"updateFunction"=>"db_contacts_update_gender"
		),
		"birthdate"=>array(
			"label"=>"en-us|Birthdate",
			"type"=>"date",
			"viewable"=>false,
			"format"=>"date",
			"sectionType"=>"date"
		),
		"age"=>array(
			"label"=>"en-us|Age",
			"importNames"=>"en-us|age,aged",
			"import"=>true,
			"updateFunction"=>"db_contacts_update_age"
		),
		"birthYear"=>array(
			"importNames"=>"en-us|birthyear,yearofbirth",
			"import"=>true,
			"updateFunction"=>"db_contacts_update_birthYear"
		),
		"birthMonth"=>array(
			"label"=>"en-us|Birth Month",
			"importNames"=>"en-us|birthmonth,yearofbirth",
			"import"=>true,
			"updateFunction"=>"db_contacts_update_birthMonth"
		),
		"birthDay"=>array(
			"label"=>"en-us|Birth Day",
			"importNames"=>"en-us|birthdate,dob,dateofbirth,birthday,dayofbirth",
			"import"=>true,
			"updateFunction"=>"db_contacts_update_birthDay"
		),
		"webPage"=>array(
			"label"=>"en-us|Web Page",
			"importNames"=>"en-us|webpage,website,url,businessurl",
			"import"=>true,
		),
		"organization"=>array(
			"label"=>"en-us|Organization(s)",
			"singleLabel"=>"en-us|Organization",
			"multi"=>true,
			"type"=>"object",
			"ref"=>"db/organizations"
		),
		"singleOrganization"=>array(
			"label"=>"en-us|Organization / Company",
			"importNames"=>"en-us|businessname,companyname,business,company,organization",
			"import"=>true,
			"dependency"=>"organization",
			"updateFunction"=>"db_contacts_update_singleOrganization"
		),
		"groups"=>array(
			"label"=>"en-us|Groups",
			"singleLabel"=>"en-us|Group",
			"ref"=>"db/contacts",
			"type"=>"group"
		),
		"timezone"=>array(
			"label"=>"en-us|Timezone",
			"type"=>"timezone",
			"viewable"=>false,
		),
		"localTime"=>array(
			"label"=>"en-us|Local Time",
			"type"=>"localTime",
			"source"=>"timezone",
			"editable"=>false,
		),
		"created"=>array(
			"label"=>"en-us|Created",
			"type"=>"date",
			"format"=>"date",
			"sectionType"=>"date",
			"viewable"=>false,
			"default"=>"=sl.unixTS()"
		),
		"creationType"=>array(
			"label"=>"en-us|Creation Type",
			"type"=>"select",
			"default"=>"user-new",
			"viewable"=>false,
			"options"=>array(
				"user-new"=>"en-us|User New",
				"user-import"=>"en-us|User Import",
				"web-chat"=>"en-us|Web Chat",
				"web-form"=>"en-us|Web Form"
			)
		),
		"flags"=>array(
			"label"=>"en-us|Flags",
			"type"=>"bitMask",
			"default"=>1,
			"viewable"=>false,
			"options"=>array(
				"en-us|E-mail Subscriber",			// 1
				"en-us|Opted In",								// 2
				"en-us|Unsubscribed",						// 4
				"en-us|Potential Bad Address"		// 8
			)
		),
		"macAddress"=>array(
			"label"=>"en-us|Mac Address",
			"updateFunction"=>"db_contacts_update_macAddress",
			"searchable"=>true,
			"dependency"=>"macAddressList",
			"readOnlyField"=>true
		),
		"macAddressList"=>array(
			"label"=>"en-us|Mac Address List",
			"multi"=>true,
			"readOnlyField"=>true
		)
	)
);
