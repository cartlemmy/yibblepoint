<?php

class slChat extends slAppClass {
	private $setup = false;
	private $history = null;
	
	function __construct($app) {
		$this->app = $app;
		
		if (isset($this->app->args[0])) {
		}
	}

	function getUserInfo($user) {
		return $GLOBALS["slCore"]->getUserInfo($user);
	}

	function getUserStatus() {
		return $GLOBALS["slSession"]->getUserStatus();
	}
	
	function getRemoteConversations() {
		$fd = $GLOBALS["slSession"]->getParentFileData();
		return $fd->get("remote-chat");
	}
	
	function closed($user) {
		$fd = $GLOBALS["slSession"]->getParentFileData();
		$data = $fd->get("remote-chat",$user);
		if (!$data) return;
		$data["status"] = "closed";
		$fd->set("remote-chat",$user,$data);
		
		$GLOBALS["slCore"]->dispatch("!chat-available:remote-chat-closed",$data);
		return true;
	}
	
	function loadHistory() {
		if ($this->history) return;
		$this->history = new slRecord($GLOBALS["slSession"]->user->dir."/chat-history",array("day","from"));
	}
	
	function historyCnt() {
		$this->loadHistory();
		return $this->history->count();
	}
	
	function history($i) {
		$this->loadHistory();
		return $this->history->get($i);
	}
	
	function sections() {
		$this->loadHistory();
		$thisYmd = explode("-",date("Y-n-j"));
		$currentSection = "";
		$rv = array();
		for ($i = 0, $len = $this->history->count(); $i < $len; $i++) {
			$activity = $this->history->get($i);
			$ymd = explode("-",date("Y-n-j",$activity["ts"]));
			for ($j = 0; $j < 3; $j++) {
				if ($ymd[$j] != $thisYmd[$j]) break;
			}
			switch ($j) {
				case 0:
					$section = date($GLOBALS["slConfig"]["international"]["year"],$activity["ts"]);
					break;
					
				case 1:
					$section = date($GLOBALS["slConfig"]["international"]["month"],$activity["ts"]);
					break;
					
				 default: case 2:
					$section = date($GLOBALS["slConfig"]["international"]["date"],$activity["ts"]);
					break;
			}
			if ($section != $currentSection) {
				$rv[] = array($i,$section);
				$currentSection = $section;
			}
		}
		return $rv;
	}
}
