<?php

class slJavaScript extends slClass {
	private $reserved = array(
		"break","do","instanceof","typeof","case","else","new","var",
		"catch","finally","return","void","continue","for","switch","while",
		"debugger","function","this","with","default","if","throw","delete",
		"in","try",
		
		"class","enum","extends","super","const","export","import",
		
		"implements","let","private","public","yield","interface","package",
		"protected","static",
		
		"null","true","false","undefined"
	);
	
	private $typeMap = array(
		"whitespace","lineterm","terminator","string","number","boolean",
		"null","reserved","undefined","identifier","operator","parenthesis",
		"sqbrack","crbrack","regex","children","function","format"
	);
	
	private $pos = 0;
	private $js = "";
	private $parsed = array();
	
	function __construct() {		
	
	}
	
	function parse($js,$children = false) {
		if (!$children) {
			$this->js = $js;
			$this->pos = 0;
		} else $js = $this->js;

		$parsed = array();
		$lastIdent = 0;
		
		while ($this->pos < strlen($js)) {
			switch (ord($js{$this->pos})) {
				case 0x09; case 0x0B; case 0x0C; case 0x20; case 0xA0; case 0xFEFF; //White space
					$parsed[] = $this->add("whitespace",$this->match('/^[\x09\x0B\x0C\xA0\x20\x{FEFF}]+/u'));
					break;
				
				case 0x0A: case 0x0D: case 0x2028: case 0x2029: // Line Terminator
					$parsed[] = $this->add("lineterm",$this->match('/^[\x0A\x0D\x{2028}\x{2029}]+/u'));
					break;
					
				case 0x3B:
					$lastIdent = 0;
					$parsed[] = $this->add("terminator",";");
					break;
					
				case 0x22: case 0x27: // String
					if ($str = $this->matchTo('/[^\\\\]\\'.$js{$this->pos}.'/',true)) {
						$parsed[] = $this->add("string",json_decode($str),strlen($str));
					} else $this->pos ++;						
					break;
					
				case 0x25: case 0x26: case 0x2A: case 0x2B: case 0x2D:
				case 0x5E: case 0x7C: case 0x21: case 0x3C: case 0x3E:
				case 0x3A: //Operator
					$parsed[] = $this->add("operator",substr($js,$this->pos,$js{$this->pos + 1} == "=" ? 2 : 1));
					break;
				
				case 0x3D:
					$parsed[] = $this->add("operator",$this->match('/^[\=]{1,3}/'));
					break;
					
				case 0x3F: case 0x7E:
					$parsed[] = $this->add("operator",$js{$this->pos});
					break;	
								
				case 0x28: case 0x29: //Parenthesis
					$parsed[] = $this->add("parenthesis",$js{$this->pos});
					break;
					
				case 0x5B: case 0x5D: //Square Brackets
					$parsed[] = $this->add("sqbrack",$js{$this->pos});
					break;
				
				case 0x7B: case 0x7D: //Curly Brackets
					$lastIdent = 0;
					$parsed[] = $this->add("crbrack",$js{$this->pos});
					break;
				
				case 0x2C: // Comma
					$parsed[] = $this->add("format",",");
					break;
					
				case 0x2D: case 0x30: case 0x31: case 0x32: case 0x33:
				case 0x34: case 0x35: case 0x36: case 0x37: case 0x38:
				case 0x39: // Possible number
					if ($num = $this->match('/^\-?0x[0-9A-Fa-f]+/')) { // Hex
						$parsed[] = $this->add("number",$num);
					} elseif ($num = $this->match('/^\-?[0-9]+(\.[0-9]+)?/')) { // Decimal
						$parsed[] = $this->add("number",$num);
					} else $this->pos++;
					break;
					
				case 0x2F: // Comment, division, or regular expression
					$this->pos ++;
					switch ($js{$this->pos}) {
						case "/": // Single line comment
							$this->pos ++;
							$parsed[] = $this->add("comment",$this->matchTo('/[\x0A\x0D\x{2028}\x{2029}]/u'));
							break;
						
						case "*": // Multi line comment
							$this->pos ++;
							$parsed[] = $this->add("comment",$this->matchTo('@\*\/@'));
							break;
						
						case "=": // /=
							$this->pos --;
							$parsed[] = $this->add("operator","/=");
							break;
							
						default: // Division or regular expression
							//TODO: This is not perfect
							$regEx = $this->matchTo('@[^\/]\/[gim]*@',true);
							$lineEnd = $this->matchTo('/[\x0A\x0D\x{2028}\x{2029}]/u',true);
							if (strlen($regEx) < strlen($lineEnd)) {
								$parsed[] = $this->add("regex",$regEx);
							} else {
								$parsed[] = $this->add("operator","/");
							}
							break;
					}
					break;
				
				
				default:
					if (($matched = $this->match('/^[A-Za-z\$\_]+[A-Za-z0-9\$\_\.]*/')) !== false) {
						if (in_array($matched,$this->reserved)) {
							switch ($matched) {
								case "null": case "undefined":
									$parsed[] = $this->add($matched,$matched);
									break;
								
								case "true": case "false":
									$parsed[] = $this->add("boolean",!!$matched,strlen($matched));
									break;
										
								default:
									$parsed[] = $this->add("reserved",$matched);
									break;
							}
						} else {
							$matchedLen = strlen($matched);
							$this->pos += $matchedLen;
							
							if (($m = $this->match('/^[\x09\x0B\x0C\xA0\x20\x{FEFF}]*\(/u')) !== false) {
								//Function
								$matched = explode(".",$matched);
								$func = array_pop($matched);
								if (count($matched)) {
									$parsed[] = $this->add("identifier",implode(".",$matched),0);
								}
								$i = $lastIdent ? $lastIdent : count($parsed) - 1;
								while ($i >= 0 && ($this->type($parsed[$i]) == "identifier" || $this->type($parsed[$i]) == "function")) {
									$i--;
								}
								$lastIdent = $i + 1;
								$this->pos ++;
								array_splice($parsed,$i + 1,0,array($this->add("function",$func,0),$this->add("parenthesis","(",0)));
								if ($this->match('/^[\x09\x0B\x0C\xA0\x20\x{FEFF}]*\)/u') === false) $parsed[] = $this->add("format",",",0);
							} else {
								$this->pos -= $matchedLen;
								$parsed[] = $this->add("identifier",$matched);
							}
						}
					} else {
						$this->pos ++;
					}
					break;
			}
			
		}		
		
		if (!$children) $this->parsed = $parsed;
		
		return $parsed;
	}
		
	function match($exp) {
		$match = array();
		if (preg_match($exp,substr($this->js,$this->pos),$match)) {
			return $match[0];
		}
		return false;
	}
	
	function matchTo($exp,$includeExp = false) {
		$match = array();
		if (preg_match($exp,$this->js,$match,0,$this->pos)) {
			return substr($this->js,$this->pos,strpos($this->js,$match[0],$this->pos) + ($includeExp ? strlen($match[0]) : 0) - $this->pos); exit();
		}
		return false;
	}
	
	function add($type,$js,$custLen = false) {
		$this->pos += $custLen !== false ? $custLen : strlen($js);
		return array(array_search($type,$this->typeMap),$js);
	}
	
	function type($item) {
		return $this->typeMap[$item[0]];
	}
	
	function toPHP($start = 0,$end = false) {
		if ($end === false) $end = count($this->parsed);
		$rv = "";
		for ($i = $start; $i < $end; $i++) {
			$item = $this->parsed[$i];
			$type = $this->typeMap[$item[0]];
			$v = $item[1];
			
			switch ($type) {
				case "identifier":
					$v = explode(".",str_replace('$',"_D_",$v));
					$root = array_shift($v);
					$rv .= '$'.$root.(count($v)?"['".implode("']['",$v)."']":"");
					break;
					
				case "string":
					$rv .= var_export($v,true);
					break;
					
				default:
					$rv .= $v;
					break;
			}
		}
		return $rv;
	}
	
}
