<?php

class slGeocoder extends slClass {
	private $replace = array (
		'alabama' => 'al',
		'alaska' => 'ak',
		'arizona' => 'az',
		'arkansas' => 'ar',
		'california' => 'ca',
		'colorado' => 'co',
		'connecticut' => 'ct',
		'delaware' => 'de',
		'district of columbia' => 'dc',
		'florida' => 'fl',
		'georgia' => 'ga',
		'hawaii' => 'hi',
		'idaho' => 'id',
		'illinois' => 'il',
		'indiana' => 'in',
		'iowa' => 'ia',
		'kansas' => 'ks',
		'kentucky' => 'ky',
		'louisiana' => 'la',
		'maine' => 'me',
		'montana' => 'mt',
		'nebraska' => 'ne',
		'nevada' => 'nv',
		'new hampshire' => 'nh',
		'new jersey' => 'nj',
		'new mexico' => 'nm',
		'new york' => 'ny',
		'north carolina' => 'nc',
		'north dakota' => 'nd',
		'ohio' => 'oh',
		'oklahoma' => 'ok',
		'oregon' => 'or',
		'maryland' => 'md',
		'massachusetts' => 'ma',
		'michigan' => 'mi',
		'minnesota' => 'mn',
		'mississippi' => 'ms',
		'missouri' => 'mo',
		'pennsylvania' => 'pa',
		'rhode island' => 'ri',
		'south carolina' => 'sc',
		'south dakota' => 'sd',
		'tennessee' => 'tn',
		'texas' => 'tx',
		'utah' => 'ut',
		'vermont' => 'vt',
		'virginia' => 'va',
		'washington' => 'wa',
		'west virginia' => 'wv',
		'wisconsin' => 'wi',
		'wyoming' => 'wy',
		'of' => '',
		'the' => '',
		'alley' => 'aly',
		'estates' => 'est',
		'lakes' => 'lks',
		'ridge' => 'rdg',
		'annex' => 'anx',
		'expressway' => 'expy',
		'landing' => 'lndg',
		'river' => 'riv',
		'arcade' => 'arc',
		'extension' => 'ext',
		'lane' => 'ln',
		'road' => 'rd',
		'avenue' => 'ave',
		'fall' => 'fall',
		'light' => 'lgt',
		'row' => 'row',
		'bayou' => 'yu',
		'falls' => 'fls',
		'loaf' => 'lf',
		'run' => 'run',
		'beach' => 'bch',
		'ferry' => 'fry',
		'locks' => 'lcks',
		'shoal' => 'shl',
		'bend' => 'bnd',
		'field' => 'fld',
		'lodge' => 'ldg',
		'shoals' => 'shls',
		'bluff' => 'blf',
		'fields' => 'flds',
		'loop' => 'loop',
		'shore' => 'shr',
		'bottom' => 'btm',
		'flats' => 'flt',
		'mall' => 'mall',
		'shores' => 'shrs',
		'boulevard' => 'blvd',
		'ford' => 'for',
		'manor' => 'mnr',
		'spring' => 'spg',
		'branch' => 'br',
		'forest' => 'frst',
		'meadows' => 'mdws',
		'springs' => 'spgs',
		'bridge' => 'brg',
		'forge' => 'fgr',
		'mill' => 'ml',
		'spur' => 'spur',
		'brook' => 'brk',
		'fork' => 'fork',
		'mills' => 'mls',
		'square' => 'sq',
		'burg' => 'bg',
		'forks' => 'frks',
		'mission' => 'msn',
		'station' => 'sta',
		'bypass' => 'byp',
		'fort' => 'ft',
		'mount' => 'mt',
		'stravenues' => 'stra',
		'camp' => 'cp',
		'freeway' => 'fwy',
		'mountain' => 'mtn',
		'stream' => 'strm',
		'canyon' => 'cyn',
		'gardens' => 'gdns',
		'neck' => 'nck',
		'street' => 'st',
		'cape' => 'cpe',
		'gateway' => 'gtwy',
		'orchard' => 'orch',
		'summit' => 'smt',
		'causeway' => 'cswy',
		'glen' => 'gln',
		'oval' => 'oval',
		'terrace' => 'ter',
		'center' => 'ctr',
		'green' => 'gn',
		'park' => 'park',
		'trace' => 'trce',
		'circle' => 'cir',
		'grove' => 'grv',
		'parkway' => 'pky',
		'track' => 'trak',
		'cliffs' => 'clfs',
		'harbor' => 'hbr',
		'pass' => 'pass',
		'trail' => 'trl',
		'club' => 'clb',
		'haven' => 'hvn',
		'path' => 'path',
		'trailer' => 'trlr',
		'corner' => 'cor',
		'heights' => 'hts',
		'pike' => 'pike',
		'tunnel' => 'tunl',
		'corners' => 'cors',
		'highway' => 'hwy',
		'pines' => 'pnes',
		'turnpike' => 'tpke',
		'course' => 'crse',
		'hill' => 'hl',
		'place' => 'pl',
		'union' => 'un',
		'court' => 'ct',
		'hills' => 'hls',
		'plain' => 'pln',
		'valley' => 'vly',
		'courts' => 'cts',
		'hollow' => 'holw',
		'plains' => 'plns',
		'viaduct' => 'via',
		'cove' => 'cv',
		'inlet' => 'inlt',
		'plaza' => 'plz',
		'view' => 'vw',
		'creek' => 'crk',
		'island' => 'is',
		'point' => 'pt',
		'village' => 'vlg',
		'crescent' => 'cres',
		'islands' => 'iss',
		'port' => 'prt',
		'ville' => 'vl',
		'crossing' => 'xing',
		'isle' => 'isle',
		'prairie' => 'pr',
		'vista' => 'vis',
		'dale' => 'dl',
		'junction' => 'jct',
		'radial' => 'radl',
		'walk' => 'walk',
		'dam' => 'dm',
		'key' => 'cy',
		'ranch' => 'rnch',
		'way' => 'way',
		'divide' => 'dv',
		'knolls' => 'knls',
		'rapids' => 'rpds',
		'wells' => 'wls',
		'drive' => 'dr',
		'lake' => 'lk',
		'rest' => 'rst',
		'saint' => 'st'		
	);

	function __construct() {
	
	}
	
	function geocodeLocation($location) {
		if ($r2 = $GLOBALS["slCore"]->db->select("db/geolocation",array("text"=>array("contains",$this->searchify($location))),array("limit"=>"1"))) {
			$geo = $r2->fetch_assoc();
			return array(
				'results'=>array (
					array (
						'address_components'=>array(),
						'formatted_address'=>'',
						'geometry'=>array(
							'location'=>array (
								'lat' => $geo["lat"],
								'lng' => $geo["lng"],
								'accuracy' => 25
							),
							'location_type' => 'ESTIMATE',
							'viewport' => NULL
						),
						'types'=>array()
					)
				),
				'status' => 'OK'
			);
		}
		return false;
	}

	function searchify($location) {
		return searchify(str_ireplace(array_keys($this->replace),array_values($this->replace),$location));
	}
	
	function geoDist($lat1,$lng1,$lat2,$lng2) {
		$gp1 = $this->geoPos($lat1,$lng1);
		$gp2 = $this->geoPos($lat2,$lng2);
		$dx = $gp1["x"] - $gp2["x"];
		$dy = $gp1["y"] - $gp2["y"];
		$dz = $gp1["z"] - $gp2["z"];
		return sqrt($dx*$dx+$dy*$dy+$dz*$dz);
	}

	function geoPos($lat,$lng) {
		$rv = array();
		$rv["z"] = sin($lat / 57.295779506) * 6371;
		$dist = cos($lat / 57.295779506) * 6371;
		$rv["x"] = cos($lng / 57.295779506) * $dist;
		$rv["y"] = sin($lng / 57.295779506) * $dist;
		return $rv;
	}

	static function exifToNum($exifCoord, $hemi) {
		$degrees = count($exifCoord) > 0 ? self::gps2Num($exifCoord[0]) : 0;
		$minutes = count($exifCoord) > 1 ? self::gps2Num($exifCoord[1]) : 0;
		$seconds = count($exifCoord) > 2 ? self::gps2Num($exifCoord[2]) : 0;

		$flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

		return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
	}

	static function gps2Num($coordPart) {
		$parts = explode('/', $coordPart);

		if (count($parts) <= 0) return 0;
		if (count($parts) == 1) return $parts[0];

		return floatval($parts[0]) / floatval($parts[1]);
	}
}
