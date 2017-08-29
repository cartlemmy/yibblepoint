<?php

class International {
	private $countryCodes = array();
	private $countryLookup = array();
	private $countrysubdivisions = array();
	private $fullsubdivisions = array();
	
	public function __construct() {
		if ($fp = fopen(SL_INCLUDE_PATH.'/data/iso_3166_2_countries.csv', 'r')) {
			$labels = fgetcsv($fp);
			while (!feof($fp)) {
				if (($line = fgetcsv($fp)) && $line[10]) {
					$this->countryCodes[$line[10]] = $line[1];
					$names = array($line[1]);
					if (preg_match('/\((.*?)\)/', $line[1], $match)) {
						$names[] = $match[1];
					}
					
					foreach ($names as $n) {
						$this->countryLookup[searchify($n,'')] = $line[10];
					}
				}
			}
			fclose($fp);
		}
		
		if ($fp = fopen(SL_INCLUDE_PATH.'/data/iso_3166_2_country_subdivisions.csv', 'r')) {
			$labels = fgetcsv($fp);
			while (!feof($fp)) {
				if (($line = fgetcsv($fp))) {
					if ($line[2] == '-') continue;
					$this->countrysubdivisions[$line[0].".".searchify($line[1],'')] = $line[2];
					$this->fullsubdivisions[$line[2]] = $line[1];
				}
			}
			fclose($fp);
		}
	}	
	
	public function toStateProvinceCode($state, $country) {
		$country = $this->toCountryCode($country);
		$state = trim($state);
		$ss = searchify($state,'');
		if (isset($this->countrysubdivisions[$country.".".$ss])) return array_pop(explode('-',$this->countrysubdivisions[$country.".".$ss]));
		if (in_array($country.'-'.strtoupper($state), $this->countrysubdivisions)) return strtoupper($state);
		return $state;
	}
	
	public function toStateProvinceFull($state, $country) {
		$country = $this->toCountryCode($country);
		$state = trim($state);
		$ss = searchify($state,'');

		if (isset($this->countrysubdivisions[$country.".".$ss])) return $this->fullsubdivisions[$this->countrysubdivisions[$country.".".$ss]];
		if (in_array($country.'-'.strtoupper($state), $this->countrysubdivisions)) return $this->fullsubdivisions[$country.'-'.strtoupper($state)];
		
		return $state;
	}
	
	public function toCountryCode($country) {
		$country = trim($country);
		if (strlen($country) == 2 && isset($this->countryCodes[strtoupper($country)])) return strtoupper($country);
		$c = searchify($country,'');
		if (isset($this->countryLookup[$c])) return $this->countryLookup[$c];
		return $country;
	}
	
	public function toCountryFull($country) {
		$country = trim($country);
		if (strlen($country) == 2 && isset($this->countryCodes[strtoupper($country)])) return $this->countryCodes[strtoupper($country)];
		$c = searchify($country,'');
		if (isset($this->countryLookup[$c])) return $this->countryCodes[$this->countryLookup[$c]];
		return $country;
	}
}
