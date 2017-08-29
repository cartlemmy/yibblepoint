<?php

class marketo extends slWebModule {
	private $marketoId;
	private $restEndPoint;
	private $clientId;
	private $secret;
	private $token;
	private $nameSpace = "http://www.marketo.com/mktows/";
	private $accessToken = false;
	private $tokenFile;
	private $cookie = false;
	public $leadDefaults = false;
	public $leadSet = false;
	
	public $allowedFields = array(
		"address","address2","address3","adminCell","adminEmail1",
		"adminEmail2","adminName1","adminName2","annualRevenue",
		"anonymousIP","billingCity","billingCountry",
		"billingPostalCode","billingState","billingStreet","city",
		"company","company4","coordinator","country","county",
		"dateOfBirth","department","district","doNotCall",
		"doNotCallReason","email","email2","ext","fax","firstName",
		"fundingType","gSOffered","gSserved","industry",
		"inferredCompany","inferredCountry","lastName","leadRole",
		"leadScore","leadSource","leadStatus","mailingAddress",
		"mailingCity","mailingState","mailingZip","mainPhone",
		"facebookDisplayName","facebookId","facebookPhotoURL",
		"facebookProfileURL","facebookReach",
		"facebookReferredEnrollments","facebookReferredVisits","gender",
		"lastReferredEnrollment","lastReferredVisit",
		"linkedInDisplayName","linkedInId","linkedInPhotoURL",
		"linkedInProfileURL","linkedInReach",
		"linkedInReferredEnrollments","linkedInReferredVisits",
		"syndicationId","totalReferredEnrollments",
		"totalReferredVisits","twitterDisplayName","twitterId",
		"twitterPhotoURL","twitterProfileURL","twitterReach",
		"twitterReferredEnrollments","twitterReferredVisits",
		"middleName","mobilePhone","numberOfEmployees","paliAdventures",
		"paliCompany","paliCompany2","paliCompany3","paliCompany4",
		"paliInstitute","paliRetreat","panicMountain","phone",
		"postalCode","previouslyEnrolled","rating","salutation",
		"school","sicCode","site","state","statusType","street",
		"title","type","unsubscribed","unsubscribedReason","website",
		"weekend3","workPhone","year1","createdAt","updatedAt",
		"emailInvalid","emailInvalidCause","externalCompanyId",
		"externalSalesPersonId","inferredCity",
		"inferredMetropolitanArea","inferredPhoneAreaCode",
		"inferredPostalCode","inferredStateRegion","isAnonymous",
		"priority","relativeScore","urgency"
	);
	
	public function __construct() {
		$this->tokenFile = SL_DATA_PATH."/tmp/marketo-access-token.json";
	}
	
	public function init($marketoId = false, $restEndPoint = false, $clientId = false, $secret = false, $token = false) {
		$this->marketoId =  $marketoId ? $marketoId : MARKETO_ID;
		$this->restEndPoint = $restEndPoint ? $restEndPoint : MARKETO_REST_ENDPOINT;
		$this->clientId = $clientId ? $clientId : MARKETO_CLIENT_ID;
		$this->secret = $secret ? $secret : MARKETO_SECRET;
		$this->token = $token ? $token : MARKETO_TOKEN;	
		if (isset($_COOKIE["_mkto_trk"])) $this->cookie = $_COOKIE["_mkto_trk"];
	}
	
	public function getAlias() {
		return "marketo";
	}
	
	private function request($req,$params = array(),$post = false) {
		
		if ($post !== 'json') $params["access_token"] = $this->getToken();
		
		$url = $this->restEndPoint."/rest/v1/".$req."?".$this->buildParams($post === 'json' ? array("access_token"=>$this->getToken()) : $params);
		
		$ch = curl_init($url);
		curl_setopt($ch,  CURLOPT_RETURNTRANSFER, 1);
		
		$header = array('accept: application/json');
		if ($post) $header[] = 'Content-Type: application/json';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($post === 'json') curl_setopt($ch, CURLOPT_POSTFIELDS, $this->jsonEncodeParams($params));
		}
		
		$raw = curl_exec($ch);
		
		if (curl_errno($ch)) return $this->marketoError(curl_error($ch));
		
		if (($rv = json_decode($raw,true)) === false) return $this->marketoError("Couldn't decode JSON");
		
		return $rv;
	}
	
	private function marketoError($message) {
		return array("success"=>false,"errors"=>array("message"=>$message));
	}
	
	private function jsonEncodeParams($params) {
		foreach ($params as $n=>$v) {
			if ($v === false) unset($params[$n]);
		}
		return json_encode($params);
	}
		
	private function buildParams($params) {
		$rv = array();
		foreach ($params as $n=>$v) {
			if (is_array($v)) {
				switch ($v[0]) {
					case "csv":
						if ($v[1] !== false) $rv[] = $n."=".$this::csvString($v[1]);
						break;
				}
			} elseif ($v !== false) {
				$rv[] = $n."=".urlencode($v);
			}
		}
		return implode("&",$rv);
	}
	
	private function getToken(){
		if (!$this->accessToken && is_file($this->tokenFile)) $this->accessToken = json_decode(file_get_contents($this->tokenFile),true);
		
		if ($this->accessToken && time() < $this->accessToken["expires"]) return $this->accessToken['access_token'];
		
		$ch = curl_init($this->restEndPoint."/identity/oauth/token?grant_type=client_credentials&client_id=".$this->clientId."&client_secret=".$this->secret);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json'));
		$response = json_decode(curl_exec($ch),true);
		
		if ($response['access_token']) {
			$this->accessToken = $response;
			$this->accessToken["expires"] = time() + $this->accessToken["expires_in"] - 10;
			file_put_contents($this->tokenFile,json_encode($this->accessToken));
		}
		
		curl_close($ch);

		return $response['access_token'];
	}
	
	private function parseLeads($leads) {
		foreach ($leads as &$lead) {
			foreach ($lead as $n=>$v) {
				switch ($n) {
					case "name":
						$name = explode(" ",trim($v));
						$lead["firstName"] = array_shift($name);
						if (count($name)) $lead["lastName"] = implode(" ",$name);
						break;
				}
				if (!in_array($n,$this->allowedFields)) unset($lead[$n]);
			}
			if ($this->leadDefaults) {
				foreach ($this->leadDefaults as $n=>$v) {
					if (!setAndTrue($lead,$n)) $lead[$n] = $v;
				}
			}
			if ($this->leadSet) {
				foreach ($this->leadSet as $n=>$v) {
					if (!setAndTrue($lead,$n)) $lead[$n] = $v;
				}
			}				
		}
		return $leads;
	}
	
	public function getMultipleLeadsByFilterType($filterType, $filterValues, $batchSize = false, $nextPageToken = false, $fields = false) {
		return $this->request('leads.json',array(
			"filterType"=>$filterType,
			"filterValues"=>array("csv",$filterValues),
			"batchSize"=>$batchSize,
			"nextPageToken"=>$nextPageToken,
			"fields"=>array("csv",$fields),
		));	
	}
	
	public function createUpdateLeads($leads, $lookupField = false, $action = false, $asyncProcessing = false, $partitionName = false, $tryAgain = true) {
		if (!isset($leads[0])) $leads = array($leads);
		
		$res = $this->request('leads.json',array(
			"input"=>$this->parseLeads($leads),
			"lookupField"=>$lookupField,
			"action"=>$action,
			"asyncProcessing"=>$asyncProcessing,
			"partitionName"=>$partitionName
		),'json');	
		
		if ($tryAgain) {
			$leadsToTry = array();
			foreach ($res["result"] as $n=>$lead) {
				if ($lead["status"] == "skipped") {
					foreach ($lead["reasons"] as $reason) {
						if (substr($reason["message"],0,25) == "Invalid value for field '") {
							unset($leads[$n][substr($reason["message"],25,-1)]);
						}
					}
					$leadsToTry[] = $leads[$n];
				}
			}
			if ($leadsToTry) {
				$this->createUpdateLeads($leads, $lookupField, $action, $asyncProcessing, $partitionName, false);
			}
		}
		return $res;
	}
	
	public function associateLead($id,$cookie = false) {
		$id = $this->leadToID($id);
		if (!$id) return $this->marketoError("Failed to associate lead data");
		
		if (!$cookie) $cookie = $this->cookie;
		if (!$cookie) return $this->marketoError("No cookie specified");
		return $this->request('leads/'.(int)$id.'/associate.json',array(
			"cookie"=>$cookie
		),'post');	
	}
	
	public function leadToID($lead) {
		if (is_array($lead)) {
			if (!setAndTrue($lead,"email")) return false;
			$res = $this->getMultipleLeadsByFilterType('email', array($lead["email"]), false, false, array('leadStatus'));
			
			$update = array();
			if ($res["success"] && count($res["result"])) {
				if ($this->leadDefaults) {
					foreach ($this->leadDefaults as $n=>$v) {
						if (!$res["result"][0][$n]) $update[$n] = $v;
					}
				}
				if ($update) $this->createUpdateLeads(array_merge(array("email"=>$lead["email"]),$update));

				return $res["result"][0]["id"];
			}
			
			$res = $this->createUpdateLeads($lead);
			return $res["result"][0]["id"];
		}
		return $lead;
	}
	
	private static function csvString($fields){
		$csvString = "";
		$i = 0;
		foreach($fields as $field){
			if ($i > 0){
				$csvString = $csvString . "," . $field;
			} elseif ($i === 0){
				$csvString = $field;
			}	
		}
		return $csvString;
	}
	
	public function renderMunchkin() {
		?><script type="text/javascript"> $.ajax({ url: '//munchkin.marketo.net/munchkin.js', dataType: 'script', cache: true, success: function() { Munchkin.init(<?=json_encode($this->marketoId);?>); } }); </script><?php
	}
}


