<?php

class U {
	const ARG_STRING = 0x0001;
	const ARG_STRING_LENGTH = 0x0002;
	//cont ARG_??? = 0x0004;
	//cont ARG_??? = 0x0008;
	//cont ARG_??? = 0x0010;
	//cont ARG_??? = 0x0020;
	//cont ARG_??? = 0x0040;
	//cont ARG_??? = 0x0080;
	//cont ARG_??? = 0x0100;
	//cont ARG_??? = 0x0200;
	//cont ARG_??? = 0x0400;
	//cont ARG_??? = 0x0800;
	//cont ARG_??? = 0x1000;
	//cont ARG_??? = 0x2000;
	//cont ARG_??? = 0x4000;
	const ARG_OPTIONAL = 0x8000;
	
	public static function enc() {
		$rv = "";
		$args = func_get_args();
		foreach ($args as $code) {
			if (is_numeric($code)) {
				$code = '\u'.str_pad(dechex($code), 4, '0', STR_PAD_LEFT);
			}
			$rv .= json_decode('"'.$code.'"');
		}
	
		return $rv;
	}
	
	public static function strLen($str) {
		return strlen(self::toPseudoUni($str));
	}
	
	
	public static function subStr() {
		return self::PHP('substr', self::ARG_STRING, array(self::ARG_STRING, self::ARG_STRING_LENGTH, self::ARG_STRING_LENGTH | self::ARG_OPTIONAL), func_get_args());
	}
	
	private static function PHP($phpFunc, $returnDef, $argDef, $args) {
		foreach ($args as $n=>$arg) {
			$def = $argDef[$n];

			$optional = $def & self::ARG_OPTIONAL;
			$def = $def & ~self::ARG_OPTIONAL;

			switch ($def) {
				case self::ARG_STRING:
					$args[$n] = self::to5U($args[$n]);
					break;
				
				case self::ARG_STRING_LENGTH;
					$args[$n] *= 5;
					break;
			}
		}
		
		/*echo "\n\n".$phpFunc."(\n\t".implode(",\n\t", array_map(function($v){
			ob_start(); debug_zval_dump($v); $zval = ob_get_clean();
			
			return '('.gettype($v).')'.array_pop(explode(' ',trim($zval))).var_export($v, true);
		},$args))."\n) = ";*/
		

		$res = call_user_func_array("".$phpFunc, $args);
		
		//echo var_export($res)."\n\n";
		 
		switch ($returnDef) {
			case self::ARG_STRING:
				$res = self::from5U($res);
				break;
			
			case self::ARG_STRING_LENGTH;
				$res /= 5;
				break;
		}
		
		return $res;		
	}
	
	public static function to5U($str) {
		$rv = "";
		$json = substr(json_encode("".$str),1,-1);
		if (preg_match_all('/(\\\\(["\\\\\\/bfnrt]|u[0-9a-fA-F]{4})|.)/', $json, $matches)) {
			foreach ($matches[0] as $m) {
				if (substr($m, 0, 2) === '\\u') {
					$rv .= '|'.substr($m, 2, 4);
				} else {
					$rv .= '|'.($m === '|' ? '..\\P' : str_pad($m, 4, '.', STR_PAD_LEFT));
				}
			}
		}
		return $rv;
	}
	
	public static function from5U($str5U) {
		$json = '';
		$len = floor(strlen($str5U) / 5);
		for ($i = 0; $i < $len; $i ++) {
			$s = substr($str5U, $i * 5 + 1, 4);
			if (substr($s, 0, 1) == '.') {
				$s = ltrim($s, '.');
				switch ($s) {
					case '': $json .= '.'; break;
					case '\\P': $json .= '|'; break;
					default: $json .= $s; break;
				}				
			} else {
				$json .= '\\u'.$s;
			}
		}
		return json_decode('"'.$json.'"');
	}	
	
	public static function toPseudoUni($str) {
		return preg_replace(
			'/\\\\(["\\\\\\/bfnrt]|u[0-9a-fA-F]{4})/', '.',
			substr(json_encode("".$str),1,-1)
		);
	}
	
	public static function strPad($input, $padLen, $padString = " ", $padType = STR_PAD_RIGHT) {
		$padLen = $padLen - self::strLen($input);

		if ($padLen <= 0) return $input;
		
		$padString = self::subStr(str_repeat($padString, ceil($padLen / self::strLen($padString))), 0, $padLen);
		
		switch ($padType) {
			case STR_PAD_LEFT:
				return $padString.$input;
				
			case STR_PAD_BOTH:
			
				break;
			
			default: case STR_PAD_RIGHT:
				return $input.$padString;
		}
	}
	
	public static function arrayToTextTable($a, $colPad = 4) {
		$rv = array();
		$colWidth = array();
		
		//Find colWidths
		foreach ($a as $row) {
			foreach ($row as $x=>$col) {
				while (count($colWidth) < $x + 1) {
					$colWidth[] = 0;
				}
				$colWidth[$x] = max($colWidth[$x], self::strLen("".$col));
			}
		}
		
		$cols = count($colWidth);
		foreach ($a as $row) {
			$out = array();
			for ($x = 0; $x < $cols; $x ++) {
				$out[] = self::strPad(isset($row[$x]) ? "".$row[$x] : "", $colWidth[$x] + $colPad);
			}
			$rv[] = implode("", $out);
		}
		return implode("\n",$rv);
	}
}
