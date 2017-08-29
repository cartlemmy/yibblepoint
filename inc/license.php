<?php

$GLOBALS["slConfig"]["license"] = json_decode(file_get_contents("data/license/info"),true);
$GLOBALS["slConfig"]["license"]["key"] = trim(file_get_contents("data/license/key"));
