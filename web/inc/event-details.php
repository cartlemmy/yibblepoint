<?php
$this->setTitle($item->get('name'));

?><div class="event-details"><h1><?=$item->get('name');?></h1>
<div class="date"><?php
	$startTs = $item->get('start');
	$endTs = $item->get('end') ? $item->get('end') : $startTs + 86400;
	
	if (date('Ymd',$startTs) == date('Ymd',$endTs)) {
		echo date('l, F d, Y', $startTs).EventBrite::timeFormat($startTs).(
			($fmt = EventBrite::timeFormat($endTs)) !== '' ? $fmt : ''
		);
	} else {
		echo date('F d, Y', $startTs).EventBrite::timeFormat($startTs).' - '.date('F d, Y', $endTs).EventBrite::timeFormat($endTs);
	}
	
	?><div class="desc"><?php
	
		EventBrite::EventDescAsHTML($item);
		
	?></div>
</div></div>
