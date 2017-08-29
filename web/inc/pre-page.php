<?php

define("FACEBOOK_LINK","");
define("GOOGLE_LINK","");
define("LINKEDIN_LINK","");
define("FACEBOOK_ADMINS","");
define("GOOGLE_SITE_VERIFICATION","");

define("SITE_IS_LIVE",!($_SERVER["SERVER_NAME"] == "localhost" || substr($_SERVER["SERVER_NAME"],0,7) == "192.168"));

$this->merge("meta",array(
	"og:site_name"=>"",
	"og:type"=>"article",
	"fb:admins"=>FACEBOOK_ADMINS,
	"viewport"=>"width=device-width, initial-scale=1, user-scalable=yes",
	"google-site-verification"=>SITE_IS_LIVE?GOOGLE_SITE_VERIFICATION:""
));

