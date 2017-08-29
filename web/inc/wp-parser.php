<?php

require_once(SL_INCLUDE_PATH."/class.slWordpress.php");
require_once(SL_WEB_PATH."/inc/wp-config.php");
require_once(SL_WEB_PATH."/inc/custom-parsers.php");

function parseWPContent($web,$uri,$returnAsData = false) {
	$wp = new slWordpressQuery($GLOBALS["WORDPRESS_CONFIG"]);
	
	if ($post = $wp->fetchPost(array("post_name"=>$uri,"post_type"=>$GLOBALS["isBlog"]?"post":"page"))) {
		
		$rv = $post->getAll();
		$web->setTitle($post->get("post_title"));
		$meta = $post->get("meta");
		if (isset($meta["_yoast_wpseo_metadesc"])) $web->setDescription($meta["_yoast_wpseo_metadesc"]);
			
		$content = $post->get("post_content");

		$rv["parsed"] = array();
		$parsers = array(
			'/\[nggallery id\=(\d+)\]/'=>'nggallery',
			'/\<\?php\s*\$gallery\s*\=\s*(\d+)([^`]*?)\?\>/'=>'nggallery',
			'/\<\?php([^`]*?)\?\>/'=>'phpCode',
			'/\<\?([^`]*?)\?\>/'=>'phpCode',
			'/<video(\s.*)?>([^`]*?)<\/video>/'=>'paliVideo',
			'/<iframe(\s.*)?>([^`]*?)<\/iframe>/'=>'iframe',
			'/<ul(\s.*)?>([^`]*?)<\/ul>/'=>'paliUL',
			'/<p(\s.*)?>([^`]*?)<\/p>/'=>'paliP',
			'/<h2(\s.*)?>([^`]*?)<\/h2>/'=>'paliH2',
			'/<h1 class\=\"orange-header\"(.*?)>([^`]*?)<\/h1>/'=>array('<h1 class="h1-bar" style="margin-bottom:5px">$2</h1>',"h1"),
			'/(wp\-content\/uploads\/2011\/12\/open\.png\")/'=>array('$1 style="width:100%"'),
			'/(wp\-content\/uploads\/2011\/12\/ready\.png\")/'=>array('$1 style="width:100%"'),
			'/(wp\-content\/uploads\/2011\/12\/page\-breaksm\.png\")/'=>array('$1 style="width:100%"'),
			'/http(s?)\:\/\/www.paliadventures.com\/([^\"\'\s]*)/'=>"linkFix",
			'/([\"\'])\/([^\/][^\1\s]*?)(\1)/'=>"rootLinkFix",
			'/\[([A-Za-z][A-Za-z\d]+)\s?([^\]]*?)\]([^\[]*?)\[\/\1\]/'=>'shortCode',
			'/\[\/?([A-Za-z][A-Za-z\d]+)([^\]]+)\]/'=>'shortCode'
		);
		
		
		foreach ($parsers as $pattern=>$parser) {
			$match = array();
			if (is_array($parser)) {
				if (isset($parser[1]) && preg_match_all($pattern,$content,$match)) {
					$rv["parsed"][$parser[1]] = $match;
				}
				$content = preg_replace($pattern,$parser[0],$content);
			} else {
				$pos = 0;
				while (preg_match($pattern,$content,$match,0,$pos)) {
					$t = array_shift($match);
					$origMatch = $match;
					$pos = strpos($content,$t,$pos);
					array_unshift($match,$web);
					$p = call_user_func_array($parser,$match);
					$content = substr($content,0,$pos).$p.substr($content,$pos+strlen($t));
					if (!isset($rv["parsed"][$parser])) $rv["parsed"][$parser] = array();
					$rv["parsed"][$parser][] = array("content"=>$p,"match"=>$origMatch);
					$pos += strlen($p);
				}
			}
		}
		if ($returnAsData) {
			return $rv;
		} else {
			libxml_use_internal_errors(true);
			
			$DOM = new DOMDocument;
			$DOM->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8"));
			
			$items = $DOM->getElementsByTagName('*');
			
			$table = false;
			$tables = array();
			
			foreach ($items as $item) {
				switch ($item->nodeName) {
					case "img":
						if (substr($item->getAttribute("src"),-10) == "/ready.png") {
							$node = $DOM->createTextNode("[enrollbutton]");							
							
							$item->parentNode->replaceChild($node,$item);
							continue;
						}
						$width = 0;

						if ($item->hasAttribute("width")) {
							$width = $item->getAttribute("width");
							$item->removeAttribute("width");
						}
						
						if ($item->hasAttribute("height")) {
							$item->removeAttribute("height");
						}
						
						$style = array();
						if ($item->hasAttribute("style")) {
							$s = explode(";",$item->getAttribute("style"));
							foreach ($s as $o) {
								$o = explode(":",$o,2);
								if (count($o) == 2) $style[$o[0]] = $o[1];
							}
						}
						
						if ($width) {
							$style["max-width"] = $width."px";
							$style["width"] = "100%";
							$styleOut = array();
						
							foreach ($style as $n=>$v) {
								$styleOut[] = $n.":".$v;
							}
							$item->setAttribute("style",implode(";",$styleOut));
						}
						break;
						
					case "table":
						$table = array($item,array());
						$tables[] = &$table;
						break;
						
					case "td":
						foreach ($item->childNodes as $child) { 
							if (!($child->nodeName == "#text" && trim(str_replace(chr(0xc2).chr(0xa0),"",$child->textContent)) == "")) $table[1][] = $child;
						}
						break;
				}
			}

			foreach ($tables as $table) {
				foreach ($table[1] as $child) {
					$table[0]->parentNode->insertBefore ( $child, $table[0] );
				}
			}
			
			ob_start();
			?><div class="full-button"><div>
	<div class="image"><img src="images/button/home/enroll-now.jpg"></div>
	<div class="label">Enroll Now</div>
	<div class="button-arrow"></div>
	<div style="clear:both"></div>
</div></div><?php 

			echo "<div id='wp-content'>".str_replace("[enrollbutton]",ob_get_clean(),$DOM->saveHTML())."</div>";
			return true;
		}		
	}
	
	return false;
}
