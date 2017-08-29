<?php

require_once(dirname(__FILE__)."/class.GsmEncoder.php");

$url = 'http://api.clickatell.com/http/sendmsg?'.http_build_query(array(
	"user"=>$info["user"],
	"password"=>$info["password"],
	"api_id"=>$info["api_id"],
	"to"=>preg_replace('/[^\d]/','',$to),
	"text"=>GsmEncoder::utf8_to_gsm0338(($this->subject?$this->subject."\n":"").htmlToText($this->message))
));

echo $url; exit();
$res = file_get_contents($url);

if (substr($res,0,3) == "ID:") return true;

return array("success"=>false,"message"=>$res);
