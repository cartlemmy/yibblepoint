<?php

$p = explode("/",$_SERVER["PHP_SELF"]); array_pop($p);

if (!defined("YP_API_USER")) define("YP_API_USER",'super');
if (!defined("YP_API_USER_ID")) define("YP_API_USER_ID",'1');
if (!defined("YP_API_ROOT")) define("YP_API_ROOT",implode("/",$p));
if (!defined("YP_API_URI") && isset($_SERVER["REQUEST_URI"])) define("YP_API_URI",trim(array_pop(explode(YP_API_ROOT,$_SERVER["REQUEST_URI"])),"/"));
if (!defined("YP_API_LOG_RESPONSE")) define("YP_API_LOG_RESPONSE",true);
if (!defined("YP_CACHE_DIR")) define("YP_CACHE_DIR","/tmp/yp-cache");
if (!is_dir(YP_CACHE_DIR)) mkdir(YP_CACHE_DIR);
