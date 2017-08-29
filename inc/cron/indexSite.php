<?php

//Run:every 5 minutes

require_once(SL_INCLUDE_PATH.'/class.slRemote.php');
$rem = new slRemote();

$maxLevel = 5;

$map = getCronData('map',false);

if (!$map) $map = array(''=>array('crawled'=>false,"level"=>0));

$endTs = time() + 60;
$crawled = false;

echo "Crawling\n";
foreach ($map as $n=>&$page) {
	if (!$page["crawled"]) {
		if ($page["level"] < $maxLevel) {
			$url = $GLOBALS["slConfig"]["web"]["canonicalRoot"]."/".$n;
			echo $url."\n";
			if (($res = $rem->request(array(
				CURLOPT_URL=>$url,
				CURLOPT_USERAGENT=>"Mozilla/5.0 (YibblePointCrawler/".$GLOBALS["slConfig"]["version"]." en-us)",
				"encode"=>"json"
			))) && setAndTrue($res,"success")) {
				$res = $res["res"];
				if (!(isset($res["meta"]["robots"]) && $res["meta"]["robots"] == "noindex")) {
					$page["keywords"] = mapKeywords($res["content"]);
					
					$page['title'] = $res['title'];

					foreach ($res["links"] as $link) {
						if ($link["local"]) {
							if ($link["href"] == "sitemap/") continue;
							$href = $link["href"] == "home/" ? "" : $link["href"];
							if (!isset($map[$href])) $map[$href] = array('crawled'=>false,"page"=>false,"level"=>$page["level"] + 1);
						}
					}
					$page['page'] = true;
					$page['lastmod'] = date("c",$res["lastModified"]);
				}
			} else {
				echo "\tDidn't scan.\n";
				//var_dump($res);
			}		
		}
		$page['crawled'] = true;
		$crawled = true;
		if (time() > $endTs) break;
	}
}

if (!$crawled) {
	echo "Done crawling\n";
	echo "Creating sitemap.xml\n";
	
	if ($fp = fopen(SL_WEB_PATH.'/sitemap.xml','w')) {
		fwrite($fp,'<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n");
		foreach ($map as $url=>$page) {
			if (!$page["page"]) {
				unset($map[$url]);
				continue;
			}
			fwrite($fp,"\t<url>\n");
			$out = array("loc"=>$GLOBALS["slConfig"]["web"]["canonicalRoot"]."/".$url,"lastmod"=>$page["lastmod"]);
			fwrite($fp,xmlOut($out));
			fwrite($fp,"\t</url>\n");
		}
		fwrite($fp,"\t</urlset>");
		fclose($fp);
		
		if (!is_dir(SL_DATA_PATH.'/pagemap')) mkdir(SL_DATA_PATH.'/pagemap');
		
		$cnt = 0;
		foreach ($map as $n=>&$checkpage) {
			$sitewideTerms = array();
			foreach ($map as $page) {
				if ($checkpage != $page) {
					foreach ($page["keywords"] as $kw) {
						if (!in_array($kw,$sitewideTerms)) $sitewideTerms[] = $kw;
						$cnt++;
						if ($cnt > 10000) break 3;
					}
				}
			}
			$useKws = array();
			foreach ($checkpage["keywords"] as $kw) {
				if (!in_array($kw,$sitewideTerms) && !in_array($kw,$useKws)) $useKws[] = $kw;
			}
			$checkpage["keywords"] = $useKws;
			if ($n == '') $n = 'home';
			if (substr($n,-1) == '/') $n = substr($n,0,-1);
			$n = array_pop(explode('/',$n));
			file_put_contents(SL_DATA_PATH.'/pagemap/'.$n.'.json',json_encode($checkpage));	
		}
		
		file_put_contents(SL_WEB_PATH.'/sitemap.json',json_encode($map));	
	}
	$map = false;
}

function xmlOut($o) {
	$rv = "";
	foreach ($o as $n=>$v) {
		$rv .= "\t\t<".$n.">".htmlentities($v)."</".$n.">\n";
	}
	return $rv;
}

function mapKeywords($c) {
	$c = preg_split('/[^a-z0-9]+/',trim(str_replace("'",'',strtolower(html_entity_decode($c)))));
	$cj = implode(" ",$c);
	$kws = array();
	for ($l = 2; $l <= 3; $l++) {
		for ($i = 0; $i < count($c) - $l; $i++) {
			$kw = implode(" ",array_slice($c,$i,$l));
			if (substr_count($cj,$kw) > 1) $kws[] = $kw;
		}
	}
	for ($i = 0; $i < count($c); $i++) {
		if (strlen($c[$i]) > 1) $kws[] = $c[$i];
	}
	return $kws;
}
					
setCronData('map',$map);
