<?php

class slReminder extends slClass {
	private $type;
	private $dir;
	private $reminderPoints = array();
	
	public function __construct($type) {
		$this->type = $type;
		$this->dir = SL_DATA_PATH."/reminders/".safeFile($type);
	}
	
	public function add($param, $to) {
		$param = safeFile($param);
		if (!isset($this->reminderPoints[$param])) $this->reminderPoints[$param] = new slReminderPoint($this->type, $param);
		$point = $this->reminderPoints[$param];
		
		return $point->add($to);
	}
}

class slReminderPoint extends slClass {
	private $type;
	private $file;
	private $reminderPoints = array();
	
	public function __construct($type, $param) {
		$this->type = $type;
		$this->file = SL_DATA_PATH."/reminders/".safeFile($type)."/".safeFile($param);
	}
	
	public function toSet($to) {
		if (is_file($this->file)) {
			$fp = fopen($this->file, "r");
			while (!feof($fp)) {
				$check = explode("->",trim(fgets($fp)),2);
				if ($check[0] == $to) return true;
			}
			fclose($fp);
		}
		return false;
	}
	
	public function add($to) {
		if (!$this->toSet($to)) {
			file_put_contents($this->file,$to."\n",FILE_APPEND);
			return true;
		}
		return false;
	}
}
