<?php


class Payeezy {
	private $dbg;
	
	public function __construct() {
	}
	
	public function request($payload = array()) {
		$this->dbg = array();
		
		$nonce = self::NONCE();
		$timestamp = self::TIMESTAMP();

		if (!($payload = $this->getPayload($payload))) return $this->error('Invalid payload');
		
		$this->dbg["payload-parsed"] = $payload;
		
		$headers = self::parseHeaders(array(
			'apikey'=>PAYEEZY_KEY,
			'token'=>PAYEEZY_MERCHANT_TOKEN, // TODO: Make sure I am using the right token.
			'Content-Type'=>'application/json',
			'Authorization'=>$this->HMAC($nonce, $timestamp, $payload),
			'nonce'=>$nonce,
			'timestamp'=>$timestamp
		));		
		
		$ch = curl_init(PAYEEZY_URL);
		
		curl_setopt($ch, CURLOPT_HEADER, false);	
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		//curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
	
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //TODO: Move to a permanent fix

		$json = curl_exec($ch);

		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ( curl_errno($ch) ) return $this->error(curl_error($ch) . ", curl_errno " . $errno, $ch);
		if ( $status != 201 ) return $this->error((($res = json_decode($json, true)) ? $res : "Failed"), $ch);

		curl_close($ch);

		if ($res = json_decode($json, true)) return $res;

		return $this->error("Could not decode response as JSON.\n\n".$json, $ch);
	}
	
	private static function parseHeaders($hArray) {
		$rv = array();
		foreach ($hArray as $n=>$v) {
			$rv[] = $n.': '.$v;			
		}		
		return $rv;
	}
	
	private function HMAC($nonce, $timestamp, $payload) {
		$s = "".PAYEEZY_KEY.$nonce.$timestamp.PAYEEZY_MERCHANT_TOKEN.$payload;
		return base64_encode(hash_hmac('SHA256', $s, PAYEEZY_SECRET, false));
	}
	
	private static function TIMESTAMP() {
		list($usec, $sec) = explode(" ", microtime());
		return ($sec - 5).sprintf("%03d", round($usec * 1000));
	}
	
	private static function NONCE() {
		return hash('sha256',json_encode(
			array($_SERVER, microtime(), rand(0, 0x7FFFFFFF))
		));
	}
	
	private function getPayload($args = array()) {
		$this->dbg["payload"] = $args;
		if (is_string($args)) {
			if (!($args = json_decode($args, true))) return false;
		}
		
		$args["merchant_ref"] = PAYEEZY_MERCHANT_TOKEN;
		if (isset($args["credit_card"]) && !isset($args["credit_card"]["type"])) {
			$args["credit_card"]["type"] = self::getCreditCardType($args["credit_card"]["card_number"]);
		}
		
		return self::JSON($args);
	}
	
	private static function JSON($data) {
		self::cleanJSON($data);
		if (defined('JSON_FORCE_OBJECT')) return json_encode($data, JSON_FORCE_OBJECT);
		return json_encode(self::numToAssoc($data));
	}
	
	private static function cleanJSON(&$data) {
		
		if (is_array($data)) {
			reset($data);
			$firstKey = key($data);
			if (count($data) == 1 && $firstKey == 0) {
				$data = &array_pop($data);
				return;
			}
			foreach ($data as &$v) {
				self::cleanJSON($v);
			}
		}
	}
	
	private static function numToAssoc(&$data) {
		if (is_array($data)) {
			foreach ($data as &$v) {
				self::numToAssoc($v);
			}
			$data = (object)$data;
		}
		return $data;
	}
	
	private static function getCreditCardType($str, $format = 'string') {
        if (empty($str)) {
            return false;
        }

        $matchingPatterns = array(
            'Visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'Mastercard' => '/^5[1-5][0-9]{14}$/',
            'American Express' => '/^3[47][0-9]{13}$/',
            'Diners Club' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
            'Discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            'JCB' => '/^(?:2131|1800|35\d{3})\d{11}$/',
            'any' => '/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/'
        );

        $ctr = 1;
        foreach ($matchingPatterns as $key=>$pattern) {
            if (preg_match($pattern, $str)) {
                return $format == 'string' ? $key : $ctr;
            }
            $ctr++;
        }
        return false;
    }
    
	private function error($message, $related) {
		return array(
			
			"message"=>array(
				"transaction_status"=>"error",
				"error"=>$message
			),
			"related"=>$this->getRelated($related)
		) + $this->dbg;
	}
	
	private function getRelated($ob) {
		if (is_resource($ob)) {
			switch (get_resource_type($ob)) {
				case "curl":
					$res = curl_getinfo($ob);	
					foreach ($res as &$v) {
						if (strpos($v, "\r\n") !== false) $v = explode("\r\n", $v);
					}
					curl_close($ob);
					return $res;
			}
		} else {
		}
	}
}
