<?php

class slReportOut extends slClass {
	private $title;
	private $type = "text/html";
	private $file;
	private $cols = array();
	private $rows = array();
	private $extraHead = array();
	private $orderBy = -1;
	private $groupSeparators = array();
	public $groupSeparatorColumn = -1;
	
	function __construct() {
		
	}

	function setTitle($title) {
		$this->title = $title;
	}
	
	function getTitle() {
		return $this->title;
	}
	
	function setType($type) {
		$this->type = $type;
	}
	
	function setCols($cols) {
		$this->cols = array();
		foreach ($cols as $col) {
			if (!is_array($col)) $col = array("label"=>$col);
			if (!isset($col["labelRaw"])) $col["labelRaw"] = noTranslation($col["label"]);
			if (!isset($col["n"])) $col["n"] = safeName($col["labelRaw"]);
			$this->cols[] = $col;
		}
	}
	
	function addGroupSeparator() {
		$i = count($this->rows);
		
		if (!in_array($i, $this->groupSeparators)) $this->groupSeparators[] = $i;
	}
	
	function addRow($cols,$options = false) {
		$row = array(
			"cols"=>$cols
		);
		if ($options) $row = array_merge($row,$options);
		$this->rows[] = $row;
	}
	
	function addInfoRow($text, $class = false) {
		$this->rows[] = array(
			"type"=>"info",
			"text"=>$text,
			"class"=>$class
		);
	}
	
	function addTotalRow($label,$value) {
		if (!is_array($value)) {
			$pos = count($this->cols)-1;
			$value = array($pos=>$value);
		}
		
		$this->rows[] = array(
			"type"=>"total",
			"label"=>$label,
			"value"=>$value
		);
	}
	
	function generateFile() {
		switch ($this->type) {
			case "text/html":
				return $this->generateHTML();
				
			case "text/csv":
				return $this->generateCSV();
		}
	}
	
	function getColPos($n) {
		foreach ($this->cols as $pos=>$col) {
			if ($col["n"] == $n || $col["labelRaw"] == $n) return $pos;
		}
		if (is_numeric($n)) return $n;
		return -1;
	}
	
	public function setOrderBy($orderBy) {
		$this->orderBy = $orderBy;
	}
	
	function sortFunc($a,$b) {
		if (isset($a["cols"]) && isset($a["cols"])) return strcmp($a["cols"][$this->orderBy],$b["cols"][$this->orderBy]);
		return (isset($a["cols"]) ? 0 : 1) - (isset($b["cols"]) ? 0 : 1);
	}
	
	
	public function sortResults() {
		if ($this->orderBy == -1) return;
		usort($this->rows,array($this,"sortFunc"));
	}
	
	private function getGroupInfo($rowI) {
		$seps = $this->groupSeparators;
		if (count($seps)) {
			if ($seps[0] !== 0) array_unshift($seps, 0);
			array_push($seps, count($this->rows));
			
			for ($i = 0; $i < count($seps) + 1; $i++) {
				$sepI = $seps[$i];
				$nextSepI = $seps[$i + 1];
				if ($rowI >= $sepI && $rowI < $nextSepI) {
					$rv = array(
						"group"=>$i,
						"groupPos"=>$rowI - $sepI,
						"groupSize"=>$nextSepI - $sepI,
						"firstInGroup"=>$rowI == $sepI,
						"lastInGroup"=>$rowI == $nextSepI - 1
					);
					
					if ($rv["groupSize"] == 1) break;
					
					$css = array("group");
					if ($rv["firstInGroup"]) $css[] = "first-in-group";
					if ($rv["lastInGroup"]) $css[] = "last-in-group";
					if ($i & 1) $css[] = "odd-group";
					
					$rv["css"] = implode(" ", $css);
					return $rv;
				}
			}
		}
		
		return array("css"=>"");
	}
	
	function generateHTML() {
		$this->sortResults();
		$this->beginPrintableHTML();
		?><h3><?=$this->title;?></h3>
<table style="width:100%">
	<thead>
		<tr>
			<?php
				foreach ($this->cols as $n=>$col) {
					echo "<th><a href=\"javascript:;\" onclick=\"report.sortBy('$n')\">".$col["label"]."</a></th>\n";
				}
			?>
		</tr>
	</thead>
	<tbody>
		<?php
			foreach ($this->rows as $rowI=>$row) {
				$group = $this->getGroupInfo($rowI);
				$tdExtra = isset($row["click"]) ? " onclick=\"".$row["click"]."\" style=\"cursor:pointer\"" : false;
				
				echo "<tr".($group["css"] ? ' class="'.$group["css"].'"' : '').">";
				switch (isset($row["type"]) ? $row["type"] : "") {
					case "total":
						$cols = array();
						$colSpan = count($this->cols) - 1;
						foreach ($row["value"] as $n=>$v) {
							if (($p = $this->getColPos($n)) != -1) {
								$colSpan = min($p,$colSpan);
								$cols[$p] = $v;
							}
						}
						if ($colSpan) echo "<td".($colSpan > 1 ? " colspan=\"".$colSpan."\"" : "").">".$row["label"]."</td>";
						for ($i = $colSpan; $i < count($this->cols); $i++) {
							echo "<td>".(isset($cols[$i]) ? $cols[$i] : "")."</td>";
						}
						break;
						
					case "info":
						echo "<td colspan=\"".count($this->cols)."\"".($row["class"]?" class=\"".$row["class"]."\"":"").">".$row["text"]."</td>";
						break;	
						
					default:
						$cnt = 0;
						foreach ($row["cols"] as $colI=>$col) {
							$rowSpan = 1;
							if ($this->groupSeparatorColumn == $colI) {
								if (isset($group["groupPos"])) {
									if ($group["groupPos"] == 0) {
										$rowSpan = $group["groupSize"];
									} else continue;
								}
							}
							echo "<td".($rowSpan > 1 ? " class=\"spanned\"" : "").($tdExtra && strpos($col,"<input") === false ? $tdExtra : "").(setAndTrue($row,"style")?" style=\"".$row["style"]."\"":"").($rowSpan > 1 ? " rowspan=\"".$rowSpan."\"" : "").">".$col."</td>";
							$cnt++;
						}
						break;
				}
				echo "</tr>\n";
			}
		?>
	</tbody>
</table><?php
		return $this->endPrintableHTML();	
	}
	
	function putCSV($fp,$row) {
		foreach ($row as &$v) {
			$v = $v ? htmlToText(strpos($v,"<") !== false ? translateHTML($v) : translate($v)) : "";
		}
		fputcsv($fp,$row);
	}
	
	function generateCSV() {		
		$this->sortResults();
		$file = $this->getTmpFile();
		if ($fp = fopen($file,"w")) {
		
			$c = array();
			foreach ($this->cols as $col) {
				$c[] = $col["label"];
			}
			$this->putCSV($fp,$c);
		
			foreach ($this->rows as $row) {
				$c = array();
				switch (isset($row["type"]) ? $row["type"] : "") {
					case "total":
						$cols = array();
						$colSpan = count($this->cols) - 1;
						foreach ($row["value"] as $n=>$v) {
							if (($p = $this->getColPos($n)) != -1) {
								$colSpan = min($p,$colSpan);
								$cols[$p] = $v;
							}
						}
						if ($colSpan) for ($i = 0; $i < $colSpan - 1; $i++) { $c[] = ""; }
						
						$c[] = $row["label"];
						
						for ($i = $colSpan; $i <= count($this->cols); $i++) {
							$c[] = isset($cols[$i]) ? $cols[$i] : "";
						}
						break;
						
					case "info":
						$c[] = $row["text"];
						break;	
						
					default:
						foreach ($row["cols"] as $col) {
							$c[] = $col;
						}
						break;
				}
				$this->putCSV($fp,$c);
			}
			fclose($fp);
		}
		return $this->webPath($file);
	}
	
	function getType() {
		return $this->type;
	}
	
	function addHead($txt) {
		$this->extraHead[] = $txt;
	}
	
	function beginPrintableHTML($extraHead = "") {
		ob_start();
		?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	<title><?=$this->title;?></title>
	<link type="text/css" rel="stylesheet" href="/css/report-out.css">	
	<script type="text/javascript">
		if (window.location.href.split("?").pop().substr(0,5) == "print") window.print();
	</script>
	<?=$extraHead."\n".implode("\n",$this->extraHead);?>
</head>
<body><div style="width:8in" id="paper"><?php
	}
	
	function endPrintableHTML() {
		?></div></body></html><?php
		$file = $this->getTmpFile();
		file_put_contents($file,translateHTML(ob_get_clean()));
		return $this->webPath($file);
	}
	
	function isDownload() {
		switch ($this->type) {
			case "text/csv": return true;
		}
		return false;
	}
	
	function webPath($file) {
		return ($this->isDownload() ? "dl?" : "").webPath($file,$this->isDownload()).($this->isDownload()?"&".safeFile($this->title).".".array_pop(explode("/",$this->type)):"");
	}
	
	function getTmpFile() {
		return tmpFilePath($this->title.".".array_pop(explode("/",$this->type)));
	}
}
