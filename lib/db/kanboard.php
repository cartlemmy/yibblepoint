<?php

require_once(SL_WEB_PATH.'/inc/class.KanboardAPIDB.php');

$this->setConnection($subType, new KanboardAPIDB($settings));
