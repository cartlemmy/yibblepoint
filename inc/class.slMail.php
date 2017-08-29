<?php

class slMail extends slClass {
	public $conn = false; 
	private $connParams = array();
	
	public function __construct($params = false) {
		if ($params) {
			$this->connect($params);
		}		
	}
	
	public function __destruct() {
		imap_close($this->conn);
	}
	
	public function getBox() {
		return $this->connParams["box"];
	}
	
	public function numMsg() {
		return imap_num_msg($this->conn);
	}
	
	public function connect($params) {	
		//resource imap_open ( string $mailbox , string $username , string $password [, int $options = 0 [, int $n_retries = 0 [, array $params = NULL ]]] )
		
		$s = array(isset($params["type"])?$params["type"]:"imap");
		
		if (setAndTrue($params,"ssl")) $s[] = "ssl";
		
		if ($this->conn = imap_open("{".$params["server"].(isset($params["port"])?":".$params["port"]:"")."/".implode("/",$s)."}".$params["box"], $params["user"], $params["password"])) {
			$this->connParams = $params;
			return true;
		}
		return false;
	}
	
	public function getMessage($numOrUid, $asUid = false) {
		return new slMailMessage($this,$numOrUid,$asUid);
	}
}

class slMailMessage {
	private $mail;
	public $num;
	public $box;
	public $uid;
	public $header;
	public $attachments;
	public $partMap = false;
	
	public function __construct($mail, $numOrUid, $asUid = false) {
		$this->mail = $mail;
		$this->box = $mail->getBox();
		
		if ($asUid) {
			$this->uid = $numOrUid;
			$this->num = imap_msgno($mail->conn, $numOrUid);
		} else {	
			$this->num = $numOrUid;
			$this->uid = imap_uid($mail->conn, $numOrUid);
		}
		$this->header = imap_header($this->mail->conn,$this->num);
	}
	
	private function parseStructure() {
		if ($this->partMap) return;		
		
		$this->partMap = array();
		$this->_parseStructure(imap_fetchstructure($this->mail->conn,$this->num));
	}
		
	private function _parseStructure($structure, $path = "") {
		$path = $path == "" ? array() : explode(".",$path);
		if (isset($structure->parts)) {
			foreach ($structure->parts as $n=>$part) {
				$path[] = (string)($n + 1);
				$part->partNum = $n + 1;
				$this->partMap[implode(".",$path)] = $part;
				$this->_parseStructure($part, implode(".",$path));
				array_pop($path);
			}
		}
	}
	
	public function getBody($html = false) {
		$this->parseStructure();
		if ($html) {
			if ($path = $this->findPart(array("type"=>TYPETEXT,"subtype"=>'HTML'), true)) {
				return $this->fetchAndCovertBody($path);
			}
		}
		if ($path = $this->findPart(array("type"=>TYPETEXT,"subtype"=>'PLAIN'), true)) {
			return $this->fetchAndCovertBody($path);
		}
		return false;		
	}
	
	public function getAsZip($path = false) {
		$file = $path ? $path."/".safeFile($this->header->subject)."-".$this->uid.".zip" : SL_DATA_PATH."/tmp/".md5(json_encode($this->header)."-".$this->uid).".zip";
		
		$za = new ZipArchive();
		if ($za->open($file, ZipArchive::CREATE) === TRUE) {
			$attachments = $this->getAttachments(true);

			foreach ($attachments as $attachment) {
				if (!trim($attachment["file"])) $attachment["file"] = "main";
				$za->addFile($attachment["file"],array_pop(explode("/",$attachment["file"])));
			}
			$za->close();
			
			return $file;
		}
		return false;
	}
	
	public function getAttachments($getAll = false) {
		$this->parseStructure();
		
		$attachments = array();
		foreach ($this->partMap as $path=>$part) {
			if ($getAll || array_shift(explode(".",$path)) > 1) {
				$attachment = array(
					"path"=>$path
				);
				
				foreach ($part->parameters as $o) {
					$attachment[$o->attribute] = $o->value;
				}				
				
				$attachment["file"] = $this->fetchAndCovertBody($path, isset($attachment["name"]) ? $attachment["name"] : true);				
				
				$attachments[] = $attachment;
			}
		}
		
		return $attachments;
	}
	
	public function findPart($search, $returnAsOb = false) {
		$this->parseStructure();
		
		foreach ($this->partMap as $path=>$part) {
			foreach ($search as $n=>$v) {
				if (!(isset($part->$n) && $part->$n == $v)) continue(2);
			}
			if ($returnAsOb) {
				$part;
			} 
			return $path;			
		}
		return false;
	}
	
	public static function removeQuotes($body) {
		$s = preg_split('/on[^\n]+\,[^\n]+wrote\:/i',$body);
		if (count($s) > 1) {
			return trim(array_shift($s));
		}
		return trim(preg_replace('/(^\w.+:\n)?(^>.*(\n|$))+/mi', '', $body));
	}
	
	public function fetchAndCovertBody($path, $returnAsFile = false) {
		$this->parseStructure();
		
		$part = $this->partMap[$path];
		
		$ext = strtolower($part->subtype);
		switch ($ext) {
			case "plain":
				$ext = "txt";
				break;
		}
		if (trim($ext)) $ext = ".".$ext;
		
		if ($returnAsFile) {
			if ($returnAsFile !== true) {
				$returnAsFile = explode(".",$returnAsFile);
				if (count($returnAsFile) > 1) $ext = ".".array_pop($returnAsFile);
				$returnAsFile = implode(".",$returnAsFile);				
			}
			$file = SL_DATA_PATH."/tmp/".safeFile($this->box)."-".$this->uid."-".($returnAsFile === true ? $path : safeFile($returnAsFile)).$ext;
			if (is_file($file)) return $file;
		}
		
		$body = imap_fetchbody($this->mail->conn, $this->num, $path);
		
		switch ($part->encoding) {
			case ENC7BIT:
				$body = imap_utf7_encode($body);
			
			case ENCBINARY:
				$body = preg_split('/\&([A-Za-z0-9\+\/]+)\-/',$body,NULL,PREG_SPLIT_DELIM_CAPTURE);
				for ($i = 1; $i < count($body); $i+=2) {
					$body[$i] = $body[$i].str_repeat("=",4 - (count($body[$i]) % 4));
					$body[$i] = base64_decode($body[$i]);
				}
				$body = implode("",$body);
				break;
			
			case ENCBASE64:
				$body = base64_decode($body);
				break;
			
			case ENCQUOTEDPRINTABLE:
				$body = quoted_printable_decode($body);
				break;
				
			case ENC8BIT: default:
				break;
		}
		
		if ($returnAsFile) {
			file_put_contents($file,$body);
			return $file;
		}
		
		return $body;
	}
}
