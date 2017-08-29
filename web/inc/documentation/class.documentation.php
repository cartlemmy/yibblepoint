<?php

class documentation extends slWebModule {	
	private $title;
	
	public function init($title = "Documentation") {
		$this->title = $title;
	}
	
	public function getAlias() {
		return "doc";
	}
	
	public function showClassDocumentation($file, $showClass = false, $showMethod = false) {
		require_once($file);
		$classes = array();
		$fileContents = file_get_contents($file);
		if (preg_match_all('/class\s+([\w\d]+)/',$fileContents,$match)) {
			if ($showClass !== false) $match = array(array($showClass),array($showClass));
			for ($i = 0; $i < count($match[0]); $i++) {
			
				$methods = $showMethod !== false && $showMethod !== "ALL" ? array($showMethod) : get_class_methods($match[1][$i]);
				$classes[$match[1][$i]] = $methods;
				
				if (!$showMethod) {
					echo '<div class="class">';
					echo '<h3><span class="keyword">class</span> '.$match[1][$i].'</h3><pre>';
					if ($this->web) $this->web->setTitle($this->title.SEP."class ".$match[1][$i]);
				}
				$_GET["class"] = $match[1][$i];
				
				foreach ($methods as $method) {
					
					$str = "/* ".$match[1][$i]."::".$method;
					$infoTxt = array();
					
					if (($pos = strpos($fileContents,$str)) !== false) {
						$pos += strlen($str);
						if (($end = strpos($fileContents,"*/",$pos)) !== false) {
							$txt = trim(preg_replace('/\n\s*\*\s+/',"\n","\n".substr($fileContents,$pos,$end-$pos)));
							if (strpos($txt,"!HIDE") !== false) continue;
							$txt = preg_replace('/(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/','[!d:$1]',$txt);
							$infoTxt = explode("\n",$txt);
						}
					}
					
					$info = array("description"=>array("text"=>""));
					$curSec = "description";
					foreach ($infoTxt as $line) {
						if (preg_match('/^(\w+)\:(\[.*?\])\:(.*)/',$line,$m2)) {
							$curSec = $m2[1];
							$info[$curSec] = array("text"=>$m2[3],"params"=>json_decode($m2[2],true));
						} elseif (preg_match('/^(\w+)\:(\{.*?\})\:(.*)/',$line,$m2)) {
							$curSec = $m2[1];
							$info[$curSec] = json_decode($m2[2],true);
							if (trim($m2[3])) $info[$curSec]["text"] = $m2[3];
						} else {
							if (trim($line)) $info[$curSec]["text"] = $line."\n";
						}
					}
					
					if (!isset($info["returns"])) $info["returns"] = array("text"=>"","params"=>array("void"));
					
					$m = new ReflectionMethod($match[1][$i],$method);
					if ($m->isPublic()) {
						if ($showMethod) echo '<div div="method">';
						$args = array();
						$parameters = array();
						foreach ($m->getParameters() as $param) {
							$tmparg = '';
							$pn = $param->getName();
							
							$parameters[$pn] = array(
								"type"=>false,
								"isPassedByReference"=>$param->isPassedByReference(),
								"isOptional"=>$param->isOptional()
							);
							
							if (isset($info["params"][$pn])) $parameters[$pn] = array_merge($parameters[$pn],$info["params"][$pn]);
							
							if ($param->isPassedByReference()) $tmparg = '&';
							if ($param->isOptional()) {
								$t = '[!d:' . shortCodeParams($param->getDefaultValue()) . ']';
								$parameters[$pn]["defaultValue"] = $param->getDefaultValue();
								if ($parameters[$pn]["type"] === false) $parameters[$pn]["type"] = gettype($param->getDefaultValue());
								$tmparg = '[' . $tmparg . '[!d:$' . $pn . '] = '.$t.']';
							} else {
								$tmparg .= '[!d:$'.$pn.']';
							}
							$args[] = $tmparg;
							unset ($tmparg);
						}
						if ($showMethod) {
							if ($this->web) $this->web->setTitle($this->title.SEP.$match[1][$i]."::".$method);
							echo '<h3>'.$match[1][$i]."::".$method.'</h3>';
							echo "<pre>";
						} else {
							$_GET["method"] = $method;
							echo '<a href="?'.http_build_query($_GET).'">';
						}
						
						echo "<span class=\"".$info["returns"]["params"][0]."\">".$info["returns"]["params"][0]."</span> <span class=\"method\">".$match[1][$i]."::".$method."</span> (".implode(", ",$args).")\n";
						
						if ($showMethod) {
							echo '</pre>';
							
							if (isset($info["description"]["text"])) echo "<p>".$info["description"]["text"]."</p>";
							
							echo '<div><h3>Parameters</h3>';
							if ($parameters) {
								foreach ($parameters as $param=>$o) {
									if ($o["type"] === false) $o["type"] = "mixed";
									echo '<p class="param"><span class="'.$o["type"].'">'.$o["type"].'</span> <span class="var">$'.$param.'</span></p>';
									if ($o["description"]) {
										echo '<p class="param-desc">'.$o["description"].'</p>';
									}
								}
							} else {
								echo '<p class="param-desc">'.$match[1][$i]."::".$method.' accepts no parameters</p>';
							}
							
							echo '</div>';
							
							echo '<div><h3>Returns</h3>';
							echo '<p class="param"><span class="'.$info["returns"]["params"][0].'">'.$info["returns"]["params"][0].'</span></p>';
							if ($info["returns"]["text"]) echo '<p class="returns-desc">'.$info["returns"]["text"].'</p>';
							echo '</div>';
						} else {
							echo '</a>';
						}
					}
				}
				if (!$showMethod) echo '</pre></section>';
			}
		}
		return $classes;	
	}
}
