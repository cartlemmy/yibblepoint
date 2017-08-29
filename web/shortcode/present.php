<?php
//params: source, fields, sort by

require_once(dirname(__FILE__)."/inc/sort-norm.php");

if (!trim($inner)) return;

$source = $params[0];
$fields = isset($params[1]) ? explode(";",$params[1]) : array();
$sortBy = isset($params[2]) ? explode(";",$params[2]) : false;

$echo = array();

if (isset($this->repData[$source])) {
	foreach ($this->repData[$source] as $num=>$row) {
		ob_start();
		echo '<a name="'.$source.'.'.$num.'"></a>';
		foreach ($fields as $field) {
			if (!isset($row[$field])) $row[$field] = false;
		}
				
		$replace = array('$num'=>$num);
		foreach ($row as $n=>$v) {
			if (preg_match_all('/\{'.$n.'\}(.*?)\{\/'.$n.'\}/',$inner,$match)) {
				for ($i = 0; $i < count($match[0]); $i++) {
					$io = array();
					$v2 = is_array($v) ? $v : array($v);
					foreach ($v2 as $iv) {
						if ($iv !== false) {
							if (!is_array($iv) && isset($this->repData["format"][$n])) {
								eval('$iv='.str_replace('$'.$n,'$iv',$this->repData["format"][$n]).';');
							}
							$rep2 = is_array($iv) ? $iv : array('$'.$n=>$iv);
							if (!is_array($iv)) $rep2['$id'] = safeName($iv);
							$io[] = str_replace(array_keys($rep2),array_values($rep2),$match[1][$i]);
						}
					}
					$replace[$match[0][$i]] = implode($io);
				}
			}
			if (isset($this->repData["format"][$n])) {
				eval('$v='.str_replace('$'.$n,'$v',$this->repData["format"][$n]).';');
			}
			$replace['$'.$n] = is_string($v) || is_numeric($v) ? $v : "";
		}

		$l = ob_get_clean().str_replace(array_keys($replace),array_values($replace),$inner);
		if ($sortBy !== false) {
			$echo[] = array(sortNorm($row[$sortBy[0]],isset($sortBy[1])?$sortBy[1]:''),$l);
		} else {
			$echo[] = $l;
		}
	}
}

echoSorted($echo);


