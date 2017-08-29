<?php

require_once(SL_INCLUDE_PATH."/class.pDataFolder.php");
require_once(SL_INCLUDE_PATH."/class.wiki.php");

$items = new pDataFolder(SL_WEB_PATH."/events");
$items->convString = true;
$items->maxImgHeight = 1200;

if (!$this) return false;
$this->subPageAllowed = true;
if ($this->subPage) {
	if ($item = $items->fetch($this->subPage)) {
		require(SL_WEB_PATH."/inc/event-details.php");
		return true;
	}
	$this->page404();
	return;
}

$events = EventBrite::getEvents();
if (!isset($events)) $events = array();

while ($item = $items->fetch()) {
	if ($item->get("hide") == "1") continue;
	
	$events[] = EventBrite::fromYPFormat($item);
}

if (isset($events)) {
	foreach ($events as &$event) {
		$event["start"]["ts"] = strtotime($event["start"]["utc"]);
		$event["end"]["ts"] = strtotime($event["end"]["utc"]);
	}
	unset($event);
}


//TODO: sort events

return $events;

class EventBrite {
	public static function getEvents($q = false, $allowed = false) {
		if ($q === false) $q = EVENTBRITE_QUERY;
		if ($allowed === false) $allowed = EVENTBRITE_ALLOWED_ORGANIZERS;
		require_once(SL_INCLUDE_PATH."/class.slRemote.php");

		$rem = new slRemote;
		
		$events = $rem->request(array(
			CURLOPT_URL=>"https://www.eventbriteapi.com/v3/events/search/?q=".$q."&token=".EVENTBRITE_OAUTH_PERSONAL,	
			"encode"=>"json",
			"cacheFor"=>600
		));
		
		//file_put_contents(SL_DATA_PATH.'/debug.json', json_encode($events, JSON_PRETTY_PRINT));
		
		$allow = explode(",", $allowed);
		$rv = array();
		foreach ($events["events"] as $event) {
			if (in_array($event["organizer_id"],$allow)) $rv[] = $event;
		}
		return count($rv) ? $rv : false;
	}
	
	public static function fromYPFormat($item, $returnExtra = false) {
		$thumb = $item->get("thumb");
		$images = $item->get("images");
	
		$start = $item->get("start");
		$end = $item->get("end");
		
		if (!($status = $item->get('status',''))) {
			if (time() > $end) {
				$status = "ended";
			} elseif (time() >= $start) {
					$status = "live";
			} else {
				$status = "started";
			}
		}
		
		$rv = array(
			"name"=>array(
				"text"=>$item->get('name'),
				"html"=>$item->get('name')
			),
			"description"=>array(
				"text"=>$item->get('description'),
				"html"=>false
			),
			"id"=>$item->get('uuid'),
			"url"=>trim($cust = $item->get('customUrl')) ? $cust : $item->get('url'),
			"start"=>EventBrite::date($start),
			"end"=>EventBrite::date($end),
			"status"=>$status,
			"created"=>EventBrite::date($item->get('created'), 'LocalDatetime'),
			"changed"=>EventBrite::date($item->get('changed'), 'LocalDatetime'),
			"capacity"=>$item->get('capacity'),
			"currency"=>"USD", //TODO: internationalize
			"listed"=>$item->get("hide") != "1",
			"locale"=>"en_US",
		    "is_locked"=>false,
		    "privacy_setting"=>"unlocked",
			"source"=>"YibblePoint",
			"is_free"=>!$item->get('price'),
			"resource_uri"=>$item->get('url'),
		   "logo"=>$thumb
		);
		
		if ($returnExtra) {
			$possible = $item->getAll();
			foreach ($possible as $n=>$v) {
				if (empty($rv[$n])) $rv[$n] = $v;
			}
		}
		return $rv;
	}
	
	private static function formatDesc($desc) {
		return str_replace(
			array('[!--','--]'),
			array('<!--','-->'),
			$desc
		);
	}
	
	public static function EventDescAsHTML($event, $truncate = false) {
		if (is_a($event, "pDataPoint")) $event = EventBrite::fromYPFormat($event, true);
		if ($event["description"]["html"] !== false && strpos($event["description"]["html"],'<!-- HIDE -->') !== false) {
			$desc = self::formatDesc(explode('<!-- HIDE -->',$event["description"]["html"]));
			foreach ($desc as &$l) {
				$l = explode('<!-- ENDHIDE -->', $l, 2);
				$l = isset($l[1]) ? $l[1] : implode('',$l);
			}
			$desc = implode('',$desc);
			//TODO: truncate HTML
		} else {
			$desc = self::formatDesc($event["description"]["text"]);
			if (is_a($event, "pDataPoint")) { echo $desc; exit(); }
			if ($truncate && ($prev = getStringBetween('<!--begin-preview-->','<!--end-preview-->',$desc))) {
				$desc = $prev;
			}
			if ($truncate) $desc = truncate($desc, $truncate);
		}
		
		if (!$truncate && $event["description"]["html"] === false) $desc = wiki::wikify($desc);
		
		$button = $truncate ? '<button type="button">More Info</button>' : '';
		$desc = str_replace(array('<P','</P'),array('<p','</p'),$desc);
		if (strpos($desc,'</p>') === false) {
			echo $desc.$button;
		} else {
			$desc = explode('</p>',$desc);
			$desc[count($desc) - 2] .= $button;
			echo implode('</p>', $desc);
		}
	}
	
	public static function timeFormat($ts) {
		$hm = date('Hi', $ts);
		if ($hm == '0000') {
			return '';
		} elseif (substr($hm, 2, 2) == '00') {
			return date(' ga', $ts);
		}
		return date(' g:ia', $ts);
	}
	
	public static function date($ts, $type = "Datetime+Timezone") {
		if ($ts == 0) return null;
		$types = array(
			"Date"=>"UTC.Date",
			"Datetime"=>"UTC.Datetime",
			"LocalDate"=>"LOC.Date",
			"LocalDatetime"=>"LOC.Datetime",
			"Timezone"=>"LocTZ",
			"UnixTS"=>"UnixTS",
			"Datetime+Timezone"=>array(
				"timezone"=>"Timezone",
				"utc"=>"Datetime",
				"local"=>"LocalDatetime"
			)
		);	
		
		$out = isset($types[$type]) ? $types[$type] : false;
		if (!$out) return null;
		if (is_array($out)) {
			$rv = array();
			
			foreach ($out as $n=>$t) {
				$rv[$n] = self::date($ts, $t);
			}
			return $rv;
		}
		
		$cdob = explode('.', $out);
		$format = array_pop($cdob);
		$cdob = implode('.',$cdob);
		
		if ($cdob == 'LOC' || $cdob == 'UTC') {
			$d = new DateTime(date('Y-m-d\TH:i:s',round($ts)));
			if ($cdob == 'UTC') $d->setTimezone(new DateTimeZone('UTC'));
		}
		$suff = $cdob == 'UTC' ? 'Z' : '';
	
		switch ($format) {
			case "Date":
				return $d->format('Y-m-d\TH:i:s').$suff;
				
			case "Datetime":
				return $d->format('Y-m-d\TH:i:s').$suff;
				
			case "LocTZ":
				return date_default_timezone_get();
			
			case "UnixTS":
				return (int)$ts;			
		}
	}
}
