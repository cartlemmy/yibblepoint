<?php

class scheduler {
	private $roles;
	private $items = array();
	private $startTs = 0;
	private $planningFallacyCorrection = 1;
	
	public function __construct() {
		$this->startTs = strtotime("00:00") + 86400;
	}
	
	public function setPlanningFallacyCorrection($amt) {
		$this->planningFallacyCorrection = $amt;
	}
	
	public function setRoles($roles) {
		$this->roles = array();
		foreach ($roles as $role) {
			$this->roles[] = new schedulerRole($role);
		}
	}
	
	public function findMatchingRoles($match = "*") {
		$rv = array();
		foreach ($this->roles as $role) {
			if ($role->matches($match)) $rv[] = $role;
		}
		return $rv;
	}
	
	public function getItemWhere($n,$v) {
		foreach ($this->items as &$item) {
			if (isset($item[$n]) && $item[$n] == $v) return $item;
		}
		return null;
	}
	
	public function add($item) {
		//echo "\n== ".$item["name"]." ==\n";
		$roles = $this->findMatchingRoles($item["role"]);
		
		$remaining = $item["duration"] * $this->planningFallacyCorrection;

		if (setAndTrue($item,"prerequisite")) {
			if ($pre = $this->getItemWhere("name",$item["prerequisite"])) {
				$item["desiredStart"] = max(isset($item["desiredStart"]) ? $item["desiredStart"] : 0, $pre["end"]);
			}
		}
		
		$start = $item["desiredStart"] ? $item["desiredStart"] : $this->startTs;
		
		foreach ($roles as $role) {
			$role->nextBlockStart = $item["desiredStart"] ? $item["desiredStart"] : $role->getEndOfFirstTask($this->startTs);
			$role->days = 0;
			$role->skip = 0;
		}
		
		$item["start"] = false;
		
		if (isset($item["take"]) && strpos($item["take"],'/') !== false) {
			$t = explode('/',$item["take"]);
			$skipDays = (int)$t[0];
			$doDays = (int)$t[1] - $skipDays;
		} else $skipDays = 0;
					
		$cnt = 0;
		while ($remaining > 0) {
			$found = false;
			
			$minSched = strtotime("9999-01-01");
			foreach ($roles as $role) {
				$minSched = min($minSched,$role->nextBlockStart);				
			}
			
			foreach ($roles as $role) {
				if ($role->nextBlockStart < $minSched + 86400) {
					if ($block = $role->nextAvailableBlock($role->nextBlockStart,$remaining)) {
						if ($role->skip) {
							$role->skip --;
						} else {
							$addBurdenAtEnd = 0;
							
							$start = $role->dayStart($block[0]);
							
							if ($block[1] > $block[0] && $role->getBurden()) {
								if ($block[1] - $block[0] >= $remaining) {
									$addBurderAtEnd = round(($block[1] - $block[0]) * $role->getBurden());
								} else {
									$mp = $block[0] + round(($block[1] - $block[0]) * $role->getBurden());
									$role->addTask($block[0],$mp,"Other",$role);
									$block[0] = $mp;
								}
							}

							if ($item["start"] === false) $item["start"] = $block[0];
							$role->addTask($block[0],$block[1],$item["name"],$role,$item);
							$remaining -= $block[1] - $block[0];
						}
						
						$role->nextBlockStart = $block[1];					
						
						$found = true;
						
						if ($addBurdenAtEnd) {
							//TODO: Add burden
						}
						
						if ($remaining == 0) break;
						
						$role->days ++;
						if ($skipDays && $role->days > $doDays) {
							$role->days = 0;
							$role->skip = $skipDays;
						}
					} else $role->nextBlockStart += 86400;
				}
			}
			if (!$found) {
				//echo "NOT FOUND FOR ".date("n/j/Y g:ia",$start);
				$start += 86400;
			}
			$cnt++;
		}
		
		while ($item["start"] === false) {
			foreach ($roles as $role) {
				if ($block = $role->nextAvailableBlock($role->nextBlockStart,$remaining)) {
					$item["start"] = $block[0];
					break;
				}
			}
			if ($item["start"] === false) $roles = $this->findMatchingRoles('*');
		}
	
		
		$this->items[] = $item;
		
		return $item;
	}
	
	public function showMapHead() {
		echo '<thead><tr>';
		$width = (100 / (count($this->roles) + 1))."%";
		echo '<th style="width:'.$width.'">Day</th>';
		foreach ($this->roles as $role) {
			echo '<th style="width:'.$width.'">'.$role->get("name").'</th>';
		}
		echo '</tr></thead>';
	}
	
	public function getStartOfFirstTask() {
		$ts = strtotime("9999-01-01");
		foreach ($this->roles as $role) {
			$ts = min($ts,$role->getStartOfFirstTask());
		}
		return $ts;
	}
	

	public function getEndOfLastTask() {
		$ts = 0;
		foreach ($this->roles as $role) {
			$ts = max($ts,$role->getEndOfLastTask());
		}
		return $ts;
	}
	
	public function showMap() {
		echo '<table class="table table-striped scheduler-map">';
		$this->showMapHead();
		
		$ts = $this->getStartOfFirstTask();
		$ts = mktime(0,0,0,date("n",$ts),date("j",$ts),date("Y",$ts));
		
		$endTs = $this->getEndOfLastTask();
		
		echo '<tbody>';
		
		//TODO: calculate the below based on roles
		$dayStartsAt = strtotime("8:00") - strtotime("today");
		$dayDuration = 8 * 3600;
		$dayEndsAt = $dayStartsAt + $dayDuration;
		$dayDuration100 = $dayDuration / 100;
		
		while ($ts < $endTs) {
			ob_start();
			$found = false;
			foreach ($this->roles as $role) {
				echo '<td class="day"><div>';
				if ($tasks = $role->getTasksAt($ts,$ts + 86400)) {
					foreach ($tasks as $task) {
						if ($task->endTime() > $task->startTime()) {
							echo '<div class="task'.($task->isBurden()?' burden':'').'" style="top:'.(($task->startTime() - ($ts + $dayStartsAt)) / $dayDuration100).'%;height:'.($task->getDuration() / $dayDuration100).'%">';
							echo $task->getName()."<br>";
							//echo date("g:ia",$task->startTime())."-".date("g:ia",$task->endTime());
							echo '</div>';
							$found = true;
						}
					}
				}
				echo '</div></td>';
			}
			$c = ob_get_clean();
			if ($found) echo '<tr><td>'.date("D, M j, Y",$ts).'</td>'.$c.'</tr>';
			$ts = strtotime("+1 day",$ts);
		}
		echo '</tbody>';
		echo '</table>';
	}
}

class schedulerRole {
	private $info = array();
	private $schedule = array();
	private $dayStartsAt = "08:00";
	public $nextBlockStart = 0;
	public $setNextBlockStart = 0;
	public $days = 0;
	public $skip = 0;
	private $firstTask = null;
	
	public function __construct($info) {
		if (!isset($info["daysOff"])) $info["daysOff"] = array("Sat","Sun");
		
		$this->info = $info;
		$this->dayStartsAt = strtotime($this->dayStartsAt) - strtotime("today");
		$this->dayEndsAt = $this->dayStartsAt + $this->info["available"]["hoursPerDay"] * 3600;
	}
	
	public function addTask($start,$end,$name,$role,&$item = false) {
		$burden = false;
		if (!$item) {
			$item = array();
			$burden = true;
		}
		
		if (!isset($item["workload"])) $item["workload"] = array();
		$rn = $role->get("name");
		if (!isset($item["workload"][$rn])) $item["workload"][$rn] = array("start"=>$start,"end"=>0,"duration"=>0);
		
		$item["workload"][$rn]["end"] = max($item["workload"][$rn]["end"],$end);
		$item["workload"][$rn]["duration"] += $end - $start;
		
		if (!isset($item["end"])) $item["end"] = 0;
		$item["end"] = max($item["end"],$end);
		
		$task = new schedulerTask($start,$end,$name,$role,$burden);
		
		if (!$this->firstTask || $start < $this->firstTask->startTime()) $this->firstTask = $task;
		
		$this->schedule[] = $task;
	}
	
	public function matches($match) {
		return $match == "*" || $match == $this->info["role"]  || $match == $this->info["name"];
	}
	
	public function get($n,$def = null) {
		return isset($this->info[$n]) ? $this->info[$n] : $def;
	}
	
	public static function getSecondsOfTS($ts) {
		return $ts - mktime(0,0,0,date("n",$ts),date("j",$ts),date("Y",$ts));
	}
	
	public function pastDayEnd($ts) {
		return self::getSecondsOfTS($ts) >= $this->dayEndsAt;
	}
	
	public function getDayEnd($ts) {
		return mktime(0,0,0,date("n",$ts),date("j",$ts),date("Y",$ts)) + $this->dayEndsAt;
	}
	
	public function getBurden() {
		return isset($this->info["available"]["burden"]) ? $this->info["available"]["burden"] : 0;
	}
	
	public function getStartOfFirstTask() {
		$ts = strtotime("9999-01-01");
		foreach ($this->schedule as $task) {
			$ts = min($ts,$task->startTime());
		}
		return $ts;
	}
	
	public function getEndOfLastTask() {
		$ts = 0;
		foreach ($this->schedule as $task) {
			$ts = max($ts,$task->endTime());
		}
		return $ts;
	}
	
	public function getEndOfFirstTask($def = false) {
		if (!$this->firstTask) return $def;
		return $this->firstTask->endTime();
	}
	
	public function nextDayStart($ts) {
		do {
			$ts = mktime(0,0,0,date("n",$ts),date("j",$ts),date("Y",$ts)) + 86400 + $this->dayStartsAt;
		} while (!$this->avaiableDay($ts));
		return $ts;
	}
	
	public function dayStart($ts) {
		return mktime(0,0,0,date("n",$ts),date("j",$ts),date("Y",$ts)) + $this->dayStartsAt;
	}
	
	
	public function avaiableDay($ts) {
		return !in_array(date("D",$ts),$this->info["daysOff"]);
	}
	
	public function nextAvailableBlock($starting,$length) {
		if ($starting < $this->info["available"]["starting"]) return false;

		$starting = max($starting,$this->dayStart($starting));		
		
		if ($this->pastDayEnd($starting)) $starting = $this->nextDayStart($starting);

		do {	
			if ($tasks = $this->getTasksAt($starting,$this->getDayEnd($starting))) {
				foreach ($tasks as $task) {
					if ($task->startTime() > $starting) {
						$ending = $task->startTime();
						break(2);
					}
					$starting = $task->endTime();
				}
				if ($this->getDayEnd($starting) > $starting) {
					
					$ending = $this->getDayEnd($starting);
					break;
				}
				$starting = $this->nextDayStart($starting);
			} else {
				$ending = $this->getDayEnd($starting);
				break;
			}
		} while(1);
		
		
		if ($starting >= $ending) {
			echo "getTasksAt ".date("n/j/Y g:ia",$starting)." - ".date("n/j/Y g:ia",$this->getDayEnd($starting))."\n";
			foreach ($tasks as $task) {
				echo "\t".date("n/j/Y g:ia",$task->startTime())." - ".date("n/j/Y g:ia",$task->endTime())."\n";
			}
			echo "HMMM"; exit();
		}
		
		return array($starting,min($starting+$length,$ending));
	}
	
	private function sortTasks($a,$b) {
		return $a->startTime() - $b->startTime();
	}
	
	public function getTasksAt($tsStart, $tsEnd = false) {
		$rv = array();
		foreach ($this->schedule as $task) {
			if ($tsEnd === false) {
				if ($tsStart >= $task->startTime() && $tsStart < $task->endTime()) $rv[] = $task;
			} else {
				if (
					($task->startTime() >= $tsStart && $task->startTime() < $tsEnd) || 
					($task->endTime() >= $tsStart && $task->endTime() < $tsEnd) 
				) $rv[] = $task;
			}
		}
		uasort($rv,array($this,"sortTasks"));
		return $rv ? $rv : null;
	}
}


class schedulerTask {
	private $start = 0;
	private $duration = 0;
	private $name;
	private $role;
	private $burden;
	
	public function __construct($start,$end,$name,$role,$burden) {
		//echo date("n/j/Y g:ia",$start)." - ".date("n/j/Y g:ia",$end)." - ".$role->get("name")." ".($burden?"***":"")."\n";
		$this->start = $start;
		$this->duration = $end - $start;
		$this->name = $name;
		$this->role = $role;
		$this->burden = $burden;
	}
	
	public function isBurden() {
		return !!$this->burden;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function startTime() {
		return $this->start;
	}
	
	public function endTime() {
		return $this->start + $this->duration;
	}
	
	public function getDuration() {
		return $this->duration;
	}
}
