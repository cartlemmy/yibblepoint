<?php

require_once(SL_INCLUDE_PATH."/class.slValue.php");

class slTableView extends slClass {	
	private $desc;
	private $table;
	private $totalRow = false;
	private $rowLink = true;
	private $rowCheckbox = true;
	private $ri;
	
	function __construct($table,$where = false) {
		$this->table = $table;
		$this->where = $where;
		$this->desc = $GLOBALS["slCore"]->db->getTableInfo($table);
		$this->ri = new slRequestInfo();
	}
	
	function show($cols = false) {
		if (!$cols) {
			$cols = array();
			$colTotal = array();
			
			foreach ($this->desc["fields"] as $n=>$field) {
				$col = $this->desc["fields"][$n];
				$cols[] = $n;
				$colTotal[$n] = isset($col["total"]) ? new slValue($col,false,0) : null;
			}
		}
		
		if ($res = $GLOBALS["slCore"]->db->select($this->table,$this->where)) {
			?><table>
				<thead><tr><?php
			
			foreach ($cols as $n) {
				$col = $this->desc["fields"][$n];
				echo "<th>".$col["label"]."</th>";
			}
			
			?></tr></thead>
			<tbody><?php
			$num = 0;
			while ($row = $GLOBALS["slCore"]->db->fetch($this->table,$res)) {
				$link = $this->rowLink ? $this->ri->getLink(array("id"=>$row["_KEY"])) : false;
				echo "<tr".($num&1?" class=\"odd\"":"").($link?" style=\"cursor:pointer\"":"").">";
				
				foreach ($cols as $n) {
					$value = new slValue($this->table,$n,$row[$n]);
					if ($colTotal[$n]) $colTotal[$n]->add($value);
					echo "<td>".($link ? "<a href=\"$link\">".$value->toString()."</a>" : $value->toString())."</td>";
				}
				echo "</tr>";
				$num ++;
			}
			
			if ($this->totalRow) {
				echo "<tr>";
				$tot = false; $labelSpan = 0;
				foreach ($cols as $n) {
					if ($colTotal[$n]) {
						if ($labelSpan && !$tot) {
							echo "<td colspan=\"".$labelSpan."\"><b>Total</b></td>";
						}
						$tot = true;
						echo "<td>".$colTotal[$n]->toString()."</td>";
					} else {
						if ($tot) {
							echo "<td></td>";
						} else $labelSpan ++;
					}
				}
				echo "</tr>";
			}
					
			?></tbody></table><?php
		}
	}
	
	function showTotalRow($show) {
		$this->totalRow = !!$show;
	}
}
