<?php

class slCommunicator extends slClass {	
	private $recipients = array();
	private $sender = array();
	private $replyTo = array();
	private $types = array();
	private $failReasons = array();
	private $message;
	private $subject = "";
	private $from;
	public $SMTPDebug = false;
	 
	function __construct($from = false) {
		$this->from = $from ? $from : $GLOBALS["slConfig"]["communication"]["defaultFrom"];
	}
	
	function addRecipient($to,$name = false) {
		$to = explode("/",$to,2);
		
		if (count($to) == 2) {
			$type = $to[0];
			$to = $to[1];
		} else {
			$type = "email";
			$to = $to[0];
			//TODO: Auto detect type
		}
		
		if ($name === false) $name = $to;
		
		$this->recipients[] = array($type,$to,$name);
		
		if (!in_array($type,$this->types)) $this->types[] = $type;
		
		switch ($type) {
			case "user":
				if ($r = $GLOBALS["slCore"]->db->select("db/user",array(array("user"=>$to,"id"=>$to),"_NO_USER"=>1))) {
					$user = $r->fetch();
					//TODO send over proper communication channels
					$this->addRecipient('email/'.$user["email"],$user["name"]);
				}
				return;
			
			case "sms":
				break;
				
			case "email": case "email-cc": case "email-bcc":
				requireThirdParty("PHPMailer");
				if (!isset($this->sender["email"])) {
					$this->sender["email"] = new PHPMailer();
					$this->sender["email"]->SMTPDebug = $this->SMTPDebug;
					$this->sender["email"]->Subject = $this->subject;
					
					foreach ($this->replyTo as $r) {
						$this->sender["email"]->AddReplyTo($r[0], $r[1]);
					}
					
					$this->sender["email"]->SetFrom($this->from["email"],$this->from["name"]);
										
					if (setAndTrue($GLOBALS["slConfig"]["communication"],"smtp")) {
						$this->sender["email"]->IsSMTP();
						foreach ($GLOBALS["slConfig"]["communication"]["smtp"] as $n=>$v) {
							$this->sender["email"]->$n = $v;
						}
					}
					if (isset($GLOBALS["slConfig"]["communication"]["otherEmail"][$this->from["email"]])) {
						foreach ($GLOBALS["slConfig"]["communication"]["otherEmail"][$this->from["email"]] as $n=>$v) {
							$this->sender["email"]->$n = $v;
						}
					}
				}
				switch ($type) {
					case "email-cc":
						$this->sender["email"]->AddCC($to,$name);
						break;
						
					case "email-bcc":
						$this->sender["email"]->AddBCC($to,$name);
						break;
						
					default:
						$this->sender["email"]->AddAddress($to,$name);
						break;
				}
				break;
		}
	}
	
	public function addAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {
		if ($this->sender["email"]) $this->sender["email"]->AddAttachment($path, $name, $encoding, $type);
	}
	
	public function addReplyTo($email, $name = '') {
		if ($this->sender["email"]) $this->sender["email"]->AddReplyTo($email, $name);
		$this->replyTo[] = array($email,$name);
	}
	
	public function setFromName($name) {
		$this->from["name"] = $name;
	}
	
	function getRecipientsReadable($delim = "\n") {
		$rv = array();
		foreach ($this->recipients as $r) {
			if (!isset($rv[$r[0]])) $rv[$r[0]] = array();
			$rv[$r[0]][] = $r[1].(setAndTrue($r,2)?" (".$r[2].")":""); 
		}
		$txt = array();
		foreach ($rv as $n=>$v) {
			$txt[] = $n.": ".implode(", ",$v);
		}
		return implode($delim,$txt);		
	}
	
	function setSubject($subject) {
		foreach ($this->types as $type) {
			switch ($type) {
				case "email":
					if ($this->sender["email"]) $this->sender["email"]->Subject = $subject;
					break;
			}
		}
		$this->subject = $subject;
	}
	
	function setMessage($message, $html = false) {
		foreach ($this->types as $type) {
			switch ($type) {
				case "email":
					if ($html) {
						$this->sender["email"]->AltBody = htmlToText($message);
						$this->sender["email"]->MsgHTML($message);
					} else {
						$this->sender["email"]->Body = $message;
					}
					break;
			}
		}
		$this->message = $message;
	}
	
	function send() {
		$this->clearFailReasons();
		$success = true;
		
		if (setAndTrue($GLOBALS["slConfig"]["dev"],"commDebug")) {
			$msg = "commDebug\nSubject: ".$this->subject."\nRecipients:\n\t".$this->getRecipientsReadable("\n\t")."\n\n".htmlToText($this->message);
			if ($GLOBALS["slCronSession"]) {
				echo $msg."\n";
			} else {
				?><script>(function(){t=<?=json_encode($msg);?>;console.log(t);alert(t);})();</script><?php
			}
			return true;
		}
		
		foreach ($this->types as $type) {
			switch ($type) {
				case "email":
					if (!$this->sender["email"]->Send()) {
						$this->setFailReason("email",$this->sender["email"]->ErrorInfo);
						$this->error($this->sender["email"]->ErrorInfo);
						$success = false;
					}
					break;
					
				case "sms":
					foreach ($this->recipients as $recipient) {
						list($t,$to,$name) = $recipient;
						if ($type == $t && isset($GLOBALS["slConfig"]["communication"][$type])) {
							$info = $GLOBALS["slConfig"]["communication"][$type];
							$file = SL_INCLUDE_PATH."/comm/".safeFile($type)."/".safeFile($info["service"]).".php";
							if (is_file($file)) {
								if (($res = require($file)) !== true) {
									$this->setFailReason($type,$res["message"]);
									$this->error($res["message"]);
									$success = false;
								}
							} else {
								$this->setFailReason($type,"slCommunicator service ".$type."/".$service." not found (".$file.")");
								$this->error("slCommunicator service ".$type."/".$service." not found (".$file.")");
								$success = false;
							}
						}
					}
					break;
			}
		}
		return $success;
	}
	
	function clearFailReasons() {
		$this->failReasons = array();
	}
	
	function setFailReason($type,$info) {
		$this->failReasons = array($type,$info);
	}
}
