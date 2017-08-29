<?php

class KanboardAPI {
	private $RPCVersion = "2.0";
	
	private $taskDefaults = array(
        "title"=>"Support Request",
        "color_id"=>"purple",
        "column_id"=>1
	);
	
	private $taskToSupport = array(
		"column_name"=>array(
			"Done"=>"Resolved"
		)
	);
	
	public function request($method, $paramsIn = false) {
		$id = hexdec(substr(sha1(microtime(true).rand(0,0x7FFFFFFF).json_encode($_SERVER)), 0, 7));
		$params = array(
			"jsonrpc"=>$this->RPCVersion,
			"method"=>$method,
			"id"=>$id
		);
		
		if ($paramsIn) $params["params"] = $paramsIn;
	
		$json = json_encode($params);
		$user = KANBOARD_USER ? KANBOARD_USER : 'jsonrpc';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, KANBOARD_ENDPOINT);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, "$user:".KANBOARD_TOKEN);
		//curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$raw = curl_exec($ch);
		$info = curl_getinfo($ch);
		$rv = json_decode($raw, true);
		
		curl_close($ch);
			
		return $rv;
	}
	
	//Task col to support col conversion
	public function taskFieldToSupport($col, $n = "column_name") {
		if (!defined("KANBOARD_AS_SUPPORT")) return $col;
		if (is_array($col)) {
			if (isset($col["result"])) { 
				foreach ($col["result"] as &$tr) {
					foreach ($this->taskToSupport as $n=>$ig) {
						if (isset($tr[$n])) $tr[$n.'_support'] = $this->taskFieldToSupport($tr[$n], $n);
						
					}
				}
			}
			return $col;
		}
		
		if (isset($this->taskToSupport[$n][$col])) $col = $this->taskToSupport[$n][$col];
		return $col;
	}
	
	//Projects
	public function getAllProjects() {
		return $this->request('getAllProjects');
	}
	
	public function getProjectById($id) {
		return $this->request(
			'getProjectById',
			array("project_id"=>$id)
		);
	}
	
	public function getProjectId($project) {
		if ($project === false && defined('KANBOARD_DEFAULT_PROJECT_ID')) return KANBOARD_DEFAULT_PROJECT_ID;
		return $project;
	}
	
	//Tasks
	public function getTaskId($task, $project = false) {
		/*if (is_string($task) && (substr($task, ':') !== false)) {
			if ($res = $this->searchTasks($query, $project)) {
				print_r($res); exit();
			}
		}*/
		return (int)$task;
	}
	
	
	public function createTask($params = array()) {
		if (!setAndTrue($params,"project_id")) $params["project_id"] = $this->getProjectId(isset($params["project_id"]) ? $params["project_id"] : false);
		foreach ($this->taskDefaults as $n=>$v) {
			if (!isset($params[$n])) $params[$n] = $v;
		}
		//print_r($params);
		return $this->request(
			'createTask',
			$params
		);
	}
	
	public function getTaskByReference($reference, $project = false) {
		$project = $this->getProjectId($project);
		
		return $this->taskFieldToSupport($this->request(
			'getTaskByReference',
			array(
				"project_id"=>$project,
				"reference"=>$reference
			)
		));
	}
	
	public function getAllTasks($project = false, $status_id = 1) {
		$project = $this->getProjectId($project);
		return $this->taskFieldToSupport($this->request(
			'getAllTasks',
			array(
				"project_id"=>$project,
				"status_id"=>$status_id
			)
		));
	}
		
	public function searchTasks($query, $project = false) {
		$project = $this->getProjectId($project);
		return $this->taskFieldToSupport($this->request(
			'searchTasks',
			array(
				"project_id"=>$project,
				"query"=>$query
			)
		));
	}
	
	public function searchTaskByTag($tags, $project = false) {
		$project = $this->getProjectId($project);
		if (!is_array($tags)) $tags = array($tags);
		$res = array(
			"jsonrpc"=>$this->RPCVersion,
			"result"=>array(),
			"id"=>array()
		);
		
		$taskIds = array();
		foreach ($tags as $tag) {
			if ($trs = $this->searchTasks('tag:"'.$tag.'"', $project)) {
				foreach ($trs["result"] as $tr) {
					if (!in_array($tr["id"], $taskIds)) {
						$taskIds[] = $tr["id"];
						$res["result"][] = $tr;
						$res["id"][] = $trs["id"];
					}
				}
			}
		}
		return $res;		
	}
	
	//Comments 
	public function createComment($task, $user, $content) {
		$user_id = $this->getUserId($user);
		return $this->request(
			'createComment',
			array(
				"task_id"=> $this->getTaskId($task),
				"user_id"=> $user_id,
				"content"=> $content.($user_id == KANBOARD_SUPPORT_USER_ID ? "\n(".$user.")" : "")
			)
		);
	}	
}
