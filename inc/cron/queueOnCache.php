<?php

//Run:every 2 minutes

$cfg = $GLOBALS["slConfig"]["db"];

$dir = SL_DATA_PATH.'/queue-on-cache';
if ($dp = opendir($dir)) {
	while (($file = readdir($dp)) !== false) {
		if (is_file($dir.'/'.$file)) {
			if (substr($file,-4) == '.out') {
				ob_start();
				$cmd = 'mysql '.
					'-u '.escapeshellarg($cfg["user"]).' '.
					'-p '.escapeshellarg($cfg["password"]).' '.
					escapeshellarg($cfg["db"]).
					' < '.escapeshellarg($dir.'/'.$file);
				
				echo $cmd."\n";
				system($cmd);
				$c = ob_get_clean();
				file_put_contents(SL_DATA_PATH.'/queue-on-cache.log', $c);
				continue;
			}
			if ($fp = fopen($dir.'/'.$file, 'r')) {
				switch ($file) {
					case "mysql-increment":
						$inc = array();
						while (!feof($fp)) {
							$line = trim(fgets($fp));
							if ($line &&
								($dec = json_decode($line, true))
							) {
								$key = $dec["table"].':'.json_encode($dec["where"]);
								if (!isset($inc[$key])) {
									$inc[$key] = $dec;									
								} else {
									foreach ($dec["fields"] as $n=>$v) {
										$inc[$key]["fields"][$n] += $v;
									}
								}								
							}
						}
						
						$fpout = fopen($dir.'/'.$file.'.out','w');
						foreach ($inc as $o) {
							$upd = array();
							foreach ($o["fields"] as $n=>$v) {
								$upd[] = '`'.$n.'`=(`'.$n.'`+'.$v.')';
							}
							fputs($fpout,"UPDATE ".array_pop(explode('/', $o["table"]))." SET ".implode(", ",$upd)." WHERE ".$o["where"].";\n");
						}
						fclose($fpout);						
						break;
				}
				fclose($fp);
				unlink($dir.'/'.$file);
			}
		}
	}
	closedir($dp);
}
 
