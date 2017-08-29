<?php

class slTaskWidget extends slAppClass {
	function __construct($app) {
		
	}
	
	function getTasks() {
		$today = strtotime("00:00:00");

		$rv = array("tasks"=>array());
		
		if ($res = $GLOBALS["slCore"]->db->select("db/userEvent","`assignedTo`=".(int)$GLOBALS["slSession"]->get("userID")." AND (`type`='task' OR `type`='todo') AND `status`<2 AND (`startTs`<".time()." OR `dueTs`<".time().")")) {
			while ($row = $res->fetch_assoc()) {
				$rv["tasks"][] = $row;
			}
		}
				
		return $rv;
	}
	
	function newTask() {
		return $GLOBALS["slCore"]->db->insert("db/userEvent",array(
			"status"=>1, // Open
			"type"=>"task",
			"assignedTo"=>(int)$GLOBALS["slSession"]->get("userID"),
			"startTs"=>time()
		));
	}
	
	function newTodo() {
		return $GLOBALS["slCore"]->db->insert("db/userEvent",array(
			"status"=>0, // New
			"type"=>"todo",
			"assignedTo"=>(int)$GLOBALS["slSession"]->get("userID")
		));
	}
	
	function newToday() {
		return $GLOBALS["slCore"]->db->insert("db/userEvent",array(
			"status"=>0, // New
			"type"=>"task",
			"dueTs"=>strtotime("today")+86399,
			"assignedTo"=>(int)$GLOBALS["slSession"]->get("userID")
		));
	}
	
	function completeTask($id) {
		$GLOBALS["slCore"]->db->update("db/userEvent",array(
			"status"=>2, // Complete
			"endTs"=>time()
		),array("id"=>$id));
		return $id;
	}
	
	function delTask($id) {
		return $GLOBALS["slCore"]->db->delete("db/userEvent",array("id"=>$id));
	}
	
	function pauseTask($id) {
		if ($res = $GLOBALS["slCore"]->db->select("db/userEvent",array("id"=>$id))) {
			$prev = $res->fetch();

			$siblingNext = $GLOBALS["slCore"]->db->insert("db/userEvent",array(
				"status"=>0, // New
				"siblingPrev"=>$id,
				"type"=>$prev["type"],
				"assignedTo"=>$prev["assignedTo"],
				"description"=>$prev["description"],
				"estDuration"=>$prev["estDuration"],
				"client"=>$prev["client"],
				"project"=>$prev["project"],
				"categories"=>$prev["categories"],
			));
			
			$GLOBALS["slCore"]->db->update("db/userEvent",array(
				"status"=>2, // Complete
				"endTs"=>time(),
				"siblingNext"=>$siblingNext
			),array("id"=>$id));
			return $siblingNext;
		}
		return false;
	}
	
	
	function startTask($id) {
		$GLOBALS["slCore"]->db->update("db/userEvent",array(
			"status"=>1, // Open
			"type"=>"task",
			"startTs"=>time()
		),array("id"=>$id));
		return $id;
	}	
}
