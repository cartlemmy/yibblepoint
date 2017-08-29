<?php

class slWeb extends slClass {
	private $request;
	private $settingNames = array("serverName","template","trafficTracking","offlineApp");
	private $charset = "utf-8";
	private $settings;
	private $templateDir;
	public $content = "";
	private $contentFile = false;
	public $title = "";
	public $info = array();
	private $nav = array();
	private $description = "";
	public $cacheContent = true;
	public $subContent = array();
	private $dependencies = array();
	private $cacheVars = array();
	public $searchIndex = false;
	private $extraHeader = array();
	private $includes = array();
	private $includeCnt = 0;
	private $vars = array();
	private $getExtraHeaderCalled = false;
	public $notFound;
	private $appFiles = array();
	private $lastHeader = false;
	private $docPos = "header";
	private $bodyStartContent = array();
	private $bodyEndContent = array();
	private $redirects;
	private $redirectsUpdated = false;
	private $redirFile;
	private $fastLoadComplete = false;
	private $requestHeaders = array();
	private $lastModified = 0;
	
	private $dbgInfo = array();
	private $queueOnCacheList = array();
	
	public $subPage = false;
	public $subPageAllowed = false;
	
	public $repData = array();

	/* slWeb::__construct
	 * params:{"request":{"type":"mixed","description":"Specifies the request paramaters of the web page to be rendered, as defined in requestInfo. Specifiying [!d:false] will use parameters of the current http request."},"settings":{"type":"mixed","description":"Initial settings, [!d:null] to use default settings"}}:
	 */
	 
	function __construct($request = false, $settings = null) {
		$this->requestHeaders = apache_request_headers();
		if (!$settings) $settings = $GLOBALS["slConfig"]["web"];
		if (isset($GLOBALS["slSession"]) && $GLOBALS["slSession"]->isLoggedIn() || DEV_MODE) $this->searchIndex = false; // Don't search index logged in sessions
		
		$this->request = $request ? $request : $GLOBALS["slConfig"]["requestInfo"];
		
		if (setAndTrue($this->request["params"],"PHPPrev") || setAndTrue($this->request["params"],"fromEditor")) $this->setCaching(false);
		
		$this->settings = array();
		foreach ($settings as $n=>$v) {
			$this->set($n,$v);
		}
		
		$this->redirFile = SL_WEB_PATH."/inc/redirects";
		
		if (!setAndTrue($settings,"template")) $this->error('$settings["template"] not defined.');
	}
	
	function __destruct() {
		if ($this->redirectsUpdated) {
			if ($fp = fopen($this->redirFile,"w")) {
				foreach ($this->redirects as $from=>$data) {
					$to = $data["to"];
					unset($data["to"]);
					fputs($fp,$from.">".$to.(count($data)?':'.json_encode($data):'')."\n");
				}
				fclose($fp);
			}			
		}
	}
	
	private function includeSort($a,$b) {
		list(,$typeA,$cntA) = explode("|",$a);
		list(,$typeB,$cntB) = explode("|",$b);
		return (($typeB == "text/css" ? 2000 : 1000) - $cntB) - (($typeA == "text/css" ? 2000 : 1000) - $cntA);
	}
	
	private function sortIncludes($a,$b) {
		return (substr($a,0,1)=="!"?1:0)-(substr($b,0,1)=="!"?1:0);
	}
	
	/* slWeb::render
	 * Renders the requested page and sends it to the browser.
	 * returns:["boolean"]:[!d:true] on success [!d:false] on failure. [!:error-failure] */
	 
	function render() {
		$this->renderStart = microtime(true);
		$this->inf('# Rendering '.$_SERVER['REQUEST_URI']);
		$this->lastModified = 0;
		header('X-Frame-Options: '.$this->get('xFrameOptions','SAMEORIGIN'));
		header("Content-Type: text/html; charset=".strtoupper($this->charset));
		
		if (setAndTrue($GLOBALS["slConfig"]["web"],"trafficTracking")) {
			$this->inf('Recording visit data');
			recordVisitData("page",array("uri"=>$_SERVER["REQUEST_URI"]));
		}
		$file = $this->templateDir."/html.php";
		
		
		if (is_file($file)) {
			$this->inf('Found template file '.$file);
			$renderFile = SL_DATA_PATH."/tmp/web-render";
			
			if (!is_dir($renderFile)) mkdir($renderFile);
			
			$renderFile .= "/".md5($_SERVER["REQUEST_URI"]."-".session_id());
		
			file_put_contents($renderFile,json_encode(array("_SERVER"=>$_SERVER)));
		
			$this->setDependencyFile($file);

			ob_start();
			
			require($file);
			
			$content = translateHTML(ob_get_clean());	

			$minify = array();
			
			uksort($this->includes,array($this,"sortIncludes"));
			
			$combineIncludes = $this->get('fast-load') && !DEV_MODE ? array("header"=>array(array(),array()),"body-start"=>array(array(),array()),"body-end"=>array(array(),array())) : false;
			
			if ($this->includes) {
				$this->inf('# Processing includes');
				foreach ($this->includes as $pos=>$inc) {
					$this->inf('## '.$pos);
					if (substr($pos,0,1) == "!") $pos = substr($pos,1);
					uksort($inc,array($this,"includeSort"));
					$inline = array("","");
					foreach ($inc as $n=>$params) {
						$this->inf('* '.$n);
						list($src,$type) = explode("|",$n);
						
						$async = false;
						if (setAndTrue($params,"_ASYNC")) {
							$async = true;
							unset($params["_ASYNC"]);
						}
						
						if ($combineIncludes && !$async && $this->shouldCombine($src)) {
							$this->inf('  * Combining');
							if ($type == "text/css") {
								$combineIncludes[$pos][0][] = $src;
							} else {
								$combineIncludes[$pos][1][] = $src.($params?"?".slRequestInfo::encodeGet($params):"");
							}
						} else {
							if (($file = $this->getWebSource($src)) && filesize($file) < 2048 && $type == "text/css") {
								$this->inf('  * Inlining');
								if ($type == "text/css") {
									$path = explode('/',$src);
									array_pop($path);
									
									$base = explode('/',WWW_RELATIVE_BASE);
									
									while ($base[0] == ".." && $path[0] == "..") {
										array_shift($base);
										array_shift($path);
									}
									
									$c = file_get_contents($file);
									preg_match_all('/url\(([\'\"])?(.*?)([\'\"])?\)/',$c,$match);

									foreach ($match[2] as $n=>$f) {
										$c = str_replace('url('.$match[1][$n].$f,'url('.$match[1][$n].WWW_RELATIVE_BASE.implode('/',$path)."/".$f,$c);
									}
									$inline[1] .= $c;
								} else {
									$inline[0] .= file_get_contents($file).";";
								}
							} else {
								if ($type == "text/css") {
									$t = "<link href=\"".$src."\" rel=\"stylesheet\" type=\"".$type."\">";
								} else { 
									$t = "<script".($async ? " async" : "")." type=\"".$type."\" src=\"".$src.($params?"?".slRequestInfo::encodeGet($params):"")."\"></script>";
								}
								$this->addToPos($pos,$t);
							}							
						}						
					}
					if ($inline[0]) $this->addToPos($pos,"<script>".$inline[0]."</script>");
					if ($inline[1]) $this->addToPos($pos,"<style>".$inline[1]."</style>");
				}
			}
			
	
			if ($combineIncludes) {
				$this->inf('# Adding combined includes');
				foreach ($combineIncludes as $pos=>$inc) {
					if ($inc[0]) $this->addToPos($pos,"<link href=\"".$this->combineFiles($inc[0],"css")."\" rel=\"stylesheet\" type=\"text/css\">");
					if ($inc[1]) $this->addToPos($pos,"<script src=\"".$this->combineFiles($inc[1],"js")."\"></script>");
				}
			}
					
			if ($this->extraHeader && !$this->getExtraHeaderCalled) {
				$content = str_ireplace("</head>",$this->getExtraHeader()."\n<script type=\"text/javascript\">\nif(window.sl){sl.config.webRelRoot=".json_encode(WWW_RELATIVE_BASE).";sl.config.webRoot=".json_encode(WWW_BASE).";}</script>\n</head>",$content);
			}
			
			if ($this->bodyStartContent) {
				$content = str_ireplace("<!--BODY_START-->",implode("\n",$this->bodyStartContent),$content);
			}
			
			if ($this->bodyEndContent) {
				$content = str_ireplace("<!--BODY_END-->",implode("\n",$this->bodyEndContent),$content);
			}
			
			
			if (setAndTrue($this->settings,"offlineApp")) $this->prepareOfflineApp($content);
			
			// Minify it
			$path = SL_INCLUDE_PATH.'/thirdparty/minify/min/lib';
			if (!$GLOBALS["slConfig"]["dev"]["debug"] && is_dir($path)) {
				$this->inf('# Minifiying');
				set_include_path(get_include_path() . PATH_SEPARATOR . $path);
				require_once($path.'/Minify/HTML.php');
				require_once($path.'/Minify/CSS.php');
				require_once($path.'/JSMin.php');
				
				$dontMinify = array('/* <![CDATA[ */','/* ]]> */');
				$from = array();
				$to = array();
				foreach ($dontMinify as $v) {
					$from[] = $v;
					$to[] = json_encode('DONTMINIFY:'.urlencode($v));
				}

				$content = str_replace($from,$to,$content);
				
				$content = Minify_HTML::minify($content, array(
					'cssMinifier' => array('Minify_CSS', 'minify'),
					'jsMinifier' => array('JSMin', 'minify')
				));
				
				$content = str_replace($to,$from,$content);				
				
			}

			$this->inf('# Parsing content');

			$content = $this->parseContent($content);
			if (isset($GLOBALS["slFormData"])) {
				file_put_contents(self::contentIncPath($this->contentFile,"fields.inc.php"),'<?php return '.var_export($GLOBALS["slFormData"],true).";");
			}
			
			if (setAndTrue($_GET,"pdf")) {
				$this->inf(false);
				$page = array_shift(explode('?',CURRENT_PAGE));
				$tmpId = md5(microtime(true));

				$hash = md5($tmpId."-".$page);
				$tmpFile = SL_DATA_PATH."/tmp/".$hash;
				file_put_contents($tmpFile, $content);
				
				ob_start();
				echo '<pre>';
				$cmd = 'cd '.SL_DATA_PATH.'/tmp; wkhtmltopdf -B 0 -L 0 -R 0 -T 0 \''.$page.'?fromtmp='.$tmpId.'\' '.$hash.".pdf";
				system($cmd);
				echo '</pre>';
				ob_clean();
				unlink($renderFile);
				exit();
			}
			
			if ($this->get('fast-load') && !DEV_MODE) $this->fastLoad($content);

			if (isset($this->requestHeaders['User-Agent']) && strpos($this->requestHeaders['User-Agent'], 'YibblePointCrawler') !== false) {
				$this->inf(false);
				require_once(SL_INCLUDE_PATH."/class.slSearchIndexer.php");
				
				$indexer = new slSearchIndexer(SL_DATA_PATH."/web-index/".$GLOBALS["slConfig"]["international"]["language"]);
				$indexer->indexFromHTML(
					$this->request["uri"],
					$this->getContent(),
					array(
						"title"=>$this->title,
						"description"=>$this->description
					)					
				);
				
				$res = array('success'=>1,'res'=>array(
					'title'=>$this->title,
					'description'=>$this->description,
					"meta"=>$this->vars["meta"],
					"lastModified"=>$this->lastModified,
					"links"=>array(),
					"content"=>strip_tags($this->getContent())
				));
				
				$dom = new DOMDocument('1.0', 'UTF-8');
				$dom->loadHTML($content);
				$a = $dom->getElementsByTagName('a');
				foreach ($a as $link) {
					if ($href = $link->attributes->getNamedItem('href')->nodeValue) {
						$local = false;
						$protocol = 'http';
						if (preg_match('/^([a-zA-Z]+)\:/',$href,$match)) $protocol = $match[1];
						
						if ($match && $match[1] !== '' && $match[1] !== 'http' && $match[1] !== 'https') {
							// Custom protocol
							$local = false;
						} elseif (substr($href,0,strlen(WWW_BASE)) === WWW_BASE) {
							$href = substr($href,strlen(WWW_BASE));
							$local = true;
						} elseif (WWW_RELATIVE_BASE != '' && substr($href,0,strlen(WWW_RELATIVE_BASE)) === WWW_RELATIVE_BASE) {
							$href = substr($href,strlen(WWW_RELATIVE_BASE));
							$local = true;
						} elseif (strpos($href,'//') !== false) {
							// Remote link
							$local = false;
						} elseif (substr($href,0,1) === "/") {
							$href = substr($href,1);
							$local = true;
						} else {
							$local = true;
						}
						
						if ($local) {
							$href = explode('?',$href,2);
							if ($href[0] !== '' && substr($href[0],-1) != '/' && strpos(array_pop(explode('/',$href[0])),'.') === false) $href[0] .= '/';
							$href = implode('?',$href);
						}
					
						$res["res"]["links"][] = array(
							"target"=>$link->attributes->getNamedItem('target')->nodeValue,
							"href"=>$href,
							"protocol"=>$protocol,
							"local"=>$local
						);
					}
				}
				
				echo json_encode($res);
				unlink($renderFile);
				exit();
			}
			
			echo $content;
			
			if ($this->cacheContent && !isset($_GET[session_name()]) && !$GLOBALS["slConfig"]["dev"]["debug"] && setAndTrue($GLOBALS["slConfig"]["web"], "enableCaching")) {
				$this->inf('# Caching');
				require_once(SL_INCLUDE_PATH."/class.slCache.php");
				$cache = new slCache($_SERVER["REQUEST_URI"]."-".($_SESSION["userID"]?session_id()."-":"").$GLOBALS["slConfig"]["international"]["language"]);
				
				$this->setDebugCallback($this->inf);

				$cache->setExpires(time()+7*86400);


				$cache->setGroup("web");
				$cache->setDependencyFile($file);
				
				$cache->addCacheVar("IS_IE",IS_IE);
				
				foreach ($this->cacheVars as $var) {
					$cache->addCacheVar($var[0],$var[1]);
				}
				
				foreach ($this->dependencies as $depFile) {
					$cache->setDependencyFile($depFile);
				}
				$cache->set($content);
				
				@mkdir(SL_DATA_PATH.'/queue-on-cache', 0755, true);
				foreach ($this->queueOnCacheList as $item) {
					file_put_contents($cache->cacheFile.'.qoc', json_encode($item)."\n", FILE_APPEND);
				}
			
				echo "<!-- ".$cache->cacheFile." -->";
				
				
			} else {
				
				@mkdir(SL_DATA_PATH.'/queue-on-cache', 0755, true);
				foreach ($this->queueOnCacheList as $item) {
					file_put_contents(SL_DATA_PATH.'/queue-on-cache/'.$item[0], json_encode($item[1])."\n", FILE_APPEND);
				}
			}
			unlink($renderFile);
			$this->cleanup();
		} else return $this->error($this->cleanup("Template file '".$file."' does not exist."));
		return true;
	}
	
	private function getWebSource($src) {
		if (strpos($src,'//') !== false) return false;
		
		$check = array(
			SL_WEB_PATH."/".$src,
			SL_TEMPLATE_PATH."/".$src
		);
		
		foreach ($check as $file) {
			if (is_file($file)) return $file;
		}
		return false;
	}
	
	private function addToPos($pos,$t) {
		switch ($pos) {
			case "header":
				if (!in_array($t,$this->extraHeader)) $this->extraHeader[] = $t;
				break;
			
			case "body-start":
				if (!in_array($t,$this->bodyStartContent)) $this->bodyStartContent[] = $t;
				break;
			
			case "body-end":
				if (!in_array($t,$this->bodyEndContent)) $this->bodyEndContent[] = $t;
				break;
		}
	}
	
	private function combineFiles($files,$t) {
		foreach ($files as &$file) {
			if (substr($file,0,strlen(WWW_RELATIVE_BASE)) == WWW_RELATIVE_BASE && strpos($file,'//') === false) $file = WWW_BASE.substr($file,strlen(WWW_RELATIVE_BASE));
		}

		if (count($files) == 1) return $files[0];
		
		$json = json_encode($files);
		$md5 = preg_replace('/[^\w\d]/','',base64_encode(md5($json,true)));
		
		file_put_contents(SL_DATA_PATH."/comb-".$md5,$json);
		$this->inf('  * Combining '.implode(', ', $files).' into:');
		$this->inf('    * '.SL_DATA_PATH."/comb-".$md5);
		return WWW_RELATIVE_BASE.$t.'/'.$md5.".".$t;
	}
	
	private function shouldCombine($src) {
		if (strpos($src,'//') !== false) {
			$this->inf('  * NOT combined: remote file');
			return false;
		}
		$ext = array_shift(explode("?",array_pop(explode(".",$src))));
		if ($local = localPath($src)) {
			$this->inf('  * Found local source: '.$local);
			if (strpos(file_get_contents($local), '!!no-fast-load') !== false) {
				$this->inf('  * NOT combined: //!no-fast-load specified in source');
				return false;
			}
		}
		if ($ext == "js") return true;
		if (substr($src,0,strlen(WWW_RELATIVE_BASE)) == WWW_RELATIVE_BASE) $src = substr($src,strlen(WWW_RELATIVE_BASE));
		return substr($src,0,strlen($ext)) == $ext;
	}
	
	/* slWeb::bodyStart
	 * Defines the start of the body in the template. Should be placed immediately after the opening &lt;body&gt; tag.
	 */
	 
	function bodyStart() {
		echo "<!--BODY_START-->";
		$this->docPos = "body-start";
	}
	
	
	/* slWeb::bodyEnd
	 * Defines the end of the body in the template. Should be placed immediately before the closing &lt;/body&gt; tag.
	 */
	 
	function bodyEnd() {
		echo "<!--BODY_END-->";
		$this->docPos = "body-end";
	}
	
	private function hasWebPageFile($page) {
		$page = array_shift(explode(".",$page));
		if (is_file($webFile = $this->request["root"]."/web/".(DEV_MODE ? 'dev/' : '').$page.".php")) return true;
		if (is_file(str_replace(array(".php","web/","web/content/dev/"),array(".html","web/content/","web/dev/content/"),$webFile))) return true;
		return false;
	}
	
	/* slWeb::prepareWebPage
	 * Prepares the web page for rendering.
	 */
	
	function prepareWebPage() {
		$inital = array($this->info,$this->vars);
		$this->docPos = "header";
		
		if (is_file(SL_WEB_PATH."/inc/pre-page.php") && !setAndTrue($this->request,"fromGPC")) require(SL_WEB_PATH."/inc/pre-page.php");
		
		$fullPath = $this->request["path"];
		
		$useTrailing = setAndTrue($GLOBALS["slConfig"]["web"],"useTrailingSlash");
		
		if (substr($fullPath,-1) == "/") $this->request["path"] = substr($fullPath,0,-1);
		
		$fullPath = explode("/",$fullPath);
		
		$this->subPage = false;
		while ($page = array_pop($fullPath)) {
			if ($this->hasWebPageFile($page)) {
				if (array_pop(explode(".",$page)) !== "html") $page .= ".html";
				break;
			}
			if (!$this->subPage) $this->subPage = str_replace(".html","",$page);
		}
		
		$parent = implode("/",$fullPath);
		
		if (!defined("CURRENT_PAGE_NAME")) {
			define("CURRENT_PAGE_NAME",array_shift(explode(".",$page)));
		}
		
		if (!defined("CURRENT_PAGE")) {
			$p = slRequestInfo::encodeGet($this->request["params"]);
			define("CURRENT_PAGE",(isset($GLOBALS["_SERVER"]['HTTPS'])?"https":"http")."://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
		}

		/* WOOO */
		if (setAndTrue($_GET,"fromtmp")) {
			$page = array_shift(explode('?',CURRENT_PAGE));
			$hash = md5($_GET["fromtmp"]."-".$page);
			echo file_get_contents(SL_DATA_PATH."/tmp/".$hash);
			exit();	
		} elseif (setAndTrue($_GET,"pdf")) { ob_start(); } 
		/* WOOO */

		if (setAndTrue($this->request,"dev")) $page = "dev/".$page;
		
		$webFile = $this->request["root"]."/web/".$page;
				
		$this->notFound = true;

		if (is_file(str_replace(".html",".php",$webFile))) {
			$webFile = str_replace(".html",".php",$webFile);
			$ext = "php";
		}
		
		$this->lastHeader = false;
		if (is_file($contentFile = str_replace(array(".php","web/","web/content/dev/"),array(".html","web/content/","web/dev/content/"),$webFile))) {
			$contentFileFound = true;
			if (isset($_GET["fromEditor"]) && isset($_GET["hist"])) {
				$contentFile = SL_WEB_PATH."/content/history/".array_pop(explode("/",$contentFile))."/".safeFile($_GET["hist"]);
			}
			$this->includeContent($contentFile);
		} else {
			$contentFileFound = false;
		}

		if (
			(!$this->subPageAllowed && $this->subPage) ||
			($this->get('parent') && $parent != $this->get('parent'))
		) {
			
			$this->info = $inital[0];
			$this->vars = $inital[1];
			
			$this->page404();
			return;
		}
			
		$this->contentFile = $contentFile;
		
		if ($this->lastHeader && isset($this->lastHeader["parent"]) && !setAndTrue($this->request,"fromGPC")) {
			$parent = $this->lastHeader["parent"];
			$actualPath = array();
			while ($parent) {
				array_unshift($actualPath,$parent);
				$info = $this->getPageInfo($this->request["root"]."/web/".(setAndTrue($this->request,"dev")?"dev/":"")."content/".$parent.".html");
				if (!$info) break;
				$parent = isset($info["parent"]) ? $info["parent"] : false;
			}
						
			$fullPath = implode("/",$fullPath);
			$actualPath = implode("/",$actualPath);
						
			if ($fullPath != $actualPath) {
				$page = explode(".",$page);
				array_pop($page);
				$this->redirect($this->request["docParent"]."/".($actualPath?$actualPath."/":"").implode(".",$page).($useTrailing ? "/" : ""));
			}
						
			//Check for trailing slash
			if ($useTrailing && substr($this->request["origPath"],-1) != "/" && $this->request["origPath"] != "") $this->redirect($this->request["origPath"]."/");
			if (!$useTrailing && substr($this->request["origPath"],-1) == "/") $this->redirect(substr($this->request["origPath"],0,-1));
		}
		
		if ((isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) && $lang = explode("-",$_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? strtolower(substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,5)) : $GLOBALS["slConfig"]["international"]["language"])) {
			$this->setLocale($lang[0],$lang[1]);
		}
		
		
		
		$this->merge("meta",array(
			"description"=>$this->getDescription(),
			"generator"=>"YibblePoint ".$GLOBALS["slConfig"]["version"],
			"og:title"=>$this->getTitle(),
			"og:url"=>$GLOBALS["slConfig"]["web"]["canonicalRoot"].$_SERVER["REQUEST_URI"],
			"og:description"=>$this->getDescription(),
		));

		if (defined('TWITTER_USERNAME')) {
			$this->merge("meta",array(
				"twitter:card"=>"summary",
				"twitter:site"=>"@".TWITTER_USERNAME,
				"twitter:title"=>$this->getTitle(),
				"twitter:description"=>$this->getDescription()
			));
		}
		
		$pagemapFile = SL_DATA_PATH.'/pagemap/'.CURRENT_PAGE_NAME.'.json';
		if (is_file($pagemapFile) && ($pagemap = json_decode(file_get_contents($pagemapFile),true))) {
			$this->merge("meta",array(
				"keywords"=>implode(",",array_slice($pagemap["keywords"],0,min(count($pagemap["keywords"]),10)))
			));	
		}
		
		$setVars = array("meta");
		
		foreach ($setVars as $n) {
			if (isset($this->lastHeader[$n])) $this->merge($n,$this->lastHeader[$n]);
		}
							
		if ($this->contentEmpty() && !$this->fromEditor()) {
			if (is_file($webFile)) {
				$this->notFound = false;
				if ($ext == "html") {
					$this->setDependencyFile($webFile);
					$this->setContent(file_get_contents($webFile));
				} else {
					ob_start();
					
					$slCore = $GLOBALS["slCore"];
					$slConfig = $GLOBALS["slConfig"];
					$slSession = $GLOBALS["slSession"];
					
					$request = $this->request;
															
					$this->setDependencyFile($webFile);
					
					require($webFile);
					
					$this->setContent(ob_get_clean());
					
				}
			} else {
				if (setAndTrue($this->request,"fromGPC")) return;
				
				if (!$contentFileFound) {
					$this->page404();
				}
				$this->setSearchIndex(false);
				$this->setCaching(false);				
			}
		}
	}
	
	public function setOGImage($src, $focalX = false, $focalY = false) {
		require_once(SL_INCLUDE_PATH."/class.slImage.php");
		$img = new slImage($src);
		
		$img->resizeAndCrop(1200, 630, $focalX, $focalY);
		
		$img->out(SL_WEB_PATH."/img/og/".CURRENT_PAGE_NAME.".jpg");
		
		$this->merge("meta",array(
			"og:image"=>WWW_BASE."/img/og/".CURRENT_PAGE_NAME.".jpg",
			"og:image:width"=>1200,
			"og:image:height"=>630
		));
	}
	
	public function getRedirects() {
		if (!$this->redirects) {
			$this->redirects = array();
			
			if (is_file($this->redirFile)) {
				$redirects = explode("\n",file_get_contents($this->redirFile));
				foreach ($redirects as $re) {
					$re = explode(":",$re,2);
					
					$data = array();
					if (count($re) == 2) {
						$data = json_decode(array_pop($re),true);
						if (!is_array($data)) continue;
					}
					
					$re = explode(">",$re[0],2);
					if (count($re) != 2) continue;
					
					list($from,$to) = $re;
					$data["to"] = $to;
					$this->redirects[$from] = $data;
				}
			}
		}
		return $this->redirects;
	}
	
	public function setRedirect($from,$to,$type = false, $extra = array()) {
		$this->getRedirects();
		$extra["to"] = $to;
		if ($type) $extra["type"] = $type;
	
		$this->redirects[$from] = $extra;
		$this->redirectsUpdated = true;
	}
	
	public function unsetRedirect($from) {
		$this->getRedirects();
		
		if (isset($this->redirects[$from])) {
			unset($this->redirects[$from]);
			$this->redirectsUpdated = true;
		}
	}
	
	public function getRedirect($from) {
		$this->getRedirects();
		
		if (isset($this->redirects[$from])) return $this->redirects[$from];
		
		return false;
	}
	
	function page404() {
		if (is_file(SL_WEB_PATH."/inc/pre-404.php")) {
			
			ob_start();
			if (require(SL_WEB_PATH."/inc/pre-404.php")) {
				$this->setContent(ob_get_clean());
				return;
			}
			ob_clean();
		}
		
		$this->setTitle('');
		
		header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
		
		ob_start();					

		if (is_file(SL_WEB_PATH."/404.php")) {
			require(SL_WEB_PATH."/404.php");
		} else {
			require(SL_INCLUDE_PATH."/web/404.php");
		}
		$this->setContent(ob_get_clean());
	}
	
/* slWeb::addAppFile
	 * Adds a file for use in an offline Web App.
	 * params:{"file":{"type":"string","description":"Local path of file to be added."}}:
	 */	

	function addAppFile($file) {
		if (is_file($file)) $this->addDependencyFile($file);
		$this->appFiles[] = $file;
	}
	
	private function prepareOfflineApp(&$content) {
		libxml_use_internal_errors(true);
								
		$DOM = new DOMDocument;
		$DOM->loadHTML($content);

		$offlineFile = SL_DATA_PATH."/web-apps";
		if (!is_dir($offlineFile)) mkdir($offlineFile);
		$offlineFile .= "/".safeFile($this->settings["offlineApp"]).".app";
		
		$offlineData = is_file($offlineFile) ? json_decode(file_get_contents($offlineFile),true) : array("files"=>array());

		$offlineData["fallback"] = $this->request["docParent"].$this->request["uri"]." ".$this->request["docParent"].$this->request["uri"];
		$offlineData["files"] = array();
		
		if ($this->dependencies) {
			if (!isset($offlineData["dependencies"])) $offlineData["dependencies"] = array();
			foreach ($this->dependencies as $d) {
				if (!in_array($d,$offlineData["dependencies"])) $offlineData["dependencies"][] = $d;
			}
		}
		
		$items = $DOM->getElementsByTagName('*');
		
		foreach ($items as $item) {
			if (($file = $item->getAttribute("href")) || ($file = $item->getAttribute("src"))) {
				$file = str_replace(WWW_BASE,"",$file);
				if (trim($file) && $file != "/" && !in_array($file,$offlineData["files"])) $offlineData["files"][] = $file;
			}
		}
		
		foreach ($this->appFiles as $file) {
			if (!in_array($file,$offlineData["files"])) $offlineData["files"][] = $file;
		}

		file_put_contents($offlineFile,json_encode($offlineData));
		
		$content = str_replace('<html','<html manifest="'.WWW_RELATIVE_BASE.safeFile($this->settings["offlineApp"]).'.manifest"',$content);
	}
		
	/* slWeb::showPageContent
	 * Shows the page [!:$page]
	 * params:{"page":{"type":"string","description":"URI name of page"}}:
	 */
	 
	function showPageContent($page) {
		echo $this->getPageContent($page);
	}
	
	/* slWeb::getPageContent
	 * Returns the page [!:$page]
	 * params:{"page":{"type":"string","description":"URI name of page"}}:
	 */
	 
	function getPageContent($page) {
		$web = new slWeb(array_merge($this->request,array("path"=>$page.".html","fromGPC"=>1)), $GLOBALS["slConfig"]["web"]);
		
		$web->prepareWebPage();
		
		return $web->getContent();
	}
	
	/* slWeb::showContent
	 * Shows the current content. Put this in your template where you want your content to appear.
	 */
	 
	function showContent() {
		echo $this->getContent();
	}
	
	/* slWeb::getContent
	 * Gets the current content.
	 * returns:["string"]:The current content.
	 */
	 
	function getContent() {
		if ($this->fromEditor()) {
			$wysiwyg = setAndTrue($GLOBALS["slConfig"]["web"],"wysiwgEditor");
			
			if ($wysiwyg) {
				ob_start();
?><script type="text/javascript" src="inc/thirdparty/tinymce/js/tinymce/tinymce.min.js"></script>
<script type="text/javascript">

tinymce.init({
    selector: "div#editor-content",
    inline: true,
    forced_root_block: false,
    force_br_newlines: true,
    force_p_newlines: false,
    convert_newlines_to_brs: true,
    setup: function(editor) {
		window.wysiwygEditor = editor;
        editor.on('change', function(e) {
           if (window.slApp) window.slApp.wysiwygEvent("change",editor.getContent());
        });
        editor.on('blur', function(e) {
           if (window.slApp) window.slApp.wysiwygEvent("blur");
        });
        editor.on('focus', function(e) {
           if (window.slApp) window.slApp.wysiwygEvent("focus");
        });   
    },
    plugins: [
        "advlist autolink lists link image charmap print preview anchor",
        "searchreplace visualblocks code fullscreen",
        "insertdatetime media table contextmenu paste"
    ],
    toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image"
});
</script><?php
			}

			return '<style>.prev-php{display:inline}</style>'.($wysiwyg ? ob_get_clean()."\n" : "")."<div id=\"editor-content\">".(trim($this->content) ? ($wysiwyg ? "" : $this->content) : "<em>Content Here</em>")."</div>";
		} else {
			if ($this->get("wikify")) {
				require_once(SL_INCLUDE_PATH."/class.wiki.php");
				return wiki::wikify($this->content);
			}
			return $this->content;
		}
	}
	
	
	/* slWeb::getIncludeContent
	 * TODO
	 */
	 
	function getIncludeContent($src) {
		if (strpos($src,"//") === false) {
			$src = WWW_BASE.$src;
		}
		return file_get_contents($src);
	}
		 
	private function fromEditor() {
		return isset($_GET["fromEditor"]) && $_GET["fromEditor"] == "1";
	}
	
	
	/* slWeb::contentEmpty
	 * Check if current content is empty
	 * returns:["boolean"]:[!d:true] if the content is empty.
	 */
	 
	function contentEmpty() {
		return trim($this->content) === "";
	}
	
	
	/* slWeb::beginSubContent
	 * Defines the start of a <a href="?page=sub-content">sub content</a> section.
	 */
	 
	function beginSubContent() {
		ob_start();
	}
	
	
	/* slWeb::endSubContent
	 * Defines the end of a <a href="?page=sub-content">sub content</a> section.
	 */
	 
	function endSubContent($name) {
		$this->subContent[$name] = ob_get_clean();
	}
	
	function getSubContent($name) {
		if (isset($this->subContent[$name])) echo $this->subContent[$name];
	}
	
	function getExtraHeader() {
		$this->getExtraHeaderCalled = true;
		return implode("\n",$this->extraHeader);
	}
	
	function getTitle() {
		return htmlspecialchars($this->get("titleTag")?$this->get("titleTag"):$this->title);
	}
	
	function getCanonicalLink() {
		// TODO: // get a manually set canonical link
		$p = str_replace(".html","",$this->request["path"]);
		if ($p == "home") {
			$p = "";
		} elseif (substr($p,-1) != "/") {
			$p .= "/";
		}
		return (isset($GLOBALS["slConfig"]["web"]["canonicalRoot"]) ? $GLOBALS["slConfig"]["web"]["canonicalRoot"]."/" : WWW_BASE).$p;
	}
	
	function getDescription() {
		return htmlspecialchars($this->description);
	}
	
	function getMetaTags($indent = "") {
		$out = array();
		if ($meta = $this->get("meta")) {
			foreach ($meta as $n=>$v) {
				$out[] = '<meta '.(substr($n,0,3)=="og:"||substr($n,0,3)=="fb:"?"property":"name").'="'.$n.'" content="'.($n == "og:image"?imageURL($v,SL_WEB_PATH."/images/".safeFile($n)):$v).'" />';
			}
		}
		return implode("\n".$indent,$out);
	}
	
	function getCharset() {
		return $this->charset;
	}
	
	
	/* slWeb::copyright
	 * Returns copyright date range from $startYear to the current year. If $startYear is the same as the current year, then only the current year will be returned.
	 * params:{"startYear":{"type":"number","description":"The starting year of the copyright range."}}:
	 * returns:["string"]:The copright range. */
	 
	function copyright($startYear) {
		return $startYear.(date("Y") > $startYear ? "-".date("Y") : "");
	}
	
	
	/* slWeb::setDependencyFile
	 * !HIDE
	 * TODO: Phase this out
	 */
	 
	function setDependencyFile($file = false) {
		$this->addDependencyFile($file);
	}
	
	
	/* slWeb::addDependencyFile
	 * Adds a file that the web page depends on, that way when the $file is updated the cache is cleared for that page.
	 * params:{"file":{"type":"string","description":"The local file to add."}}:
	 */
	 
	function addDependencyFile($file = false) {
		if (!$file) {
			$bt = debug_backtrace();
			$file = $bt[1]["file"];
		}
		if ($file && !in_array($file,$this->dependencies)) {
			if (is_file($file)) $this->lastModified = max($this->lastModified,filemtime($file));
			$this->dependencies[] = $file;
		}
	}
	
	public function queueOnCache($type, $command) {
		if (in_array($type, array("mysql", "mysql-increment"))) $this->queueOnCacheList[] = array($type, $command);
	}
	
	/* slWeb::setContent
	 * Sets the content of the page.
	 * params:{"content":{"type":"string","description":"A string containing the page content."}}:*/
	 
	function setContent($content) {			
		$bt = debug_backtrace();
		if (isset($bt[0]["file"])) $this->setDependencyFile($bt[0]["file"]); 
		$this->content = $content;
	}
	
	/* slWeb::setTitle
	 * Sets the title of the page. This will set the &lt;title&gt; tag, and the og:title if you are using og tags.
	 * params:{"title":{"type":"string","description":"A string containing the title the page will be set to."}}:*/
	 
	function setTitle($title) {
		$this->title = $title;
	}
	
	
	/* slWeb::setDescription
	 * Sets the description of the page. This will set the &lt;meta name="description"&gt; tag, and the og:description if you are using og tags.
	 * params:{"description":{"type":"string","description":"A string containing the description the page will be set to."}}:*/
	 
	function setDescription($description) {
		$this->description = $description;
	}
	
	
	/* slWeb::setLocale
	 * Sets the the locale of the page. This will set the appropriate meta tags, and translate the page if a translation is available.
	 * params:{"language":{"type":"string","description":"<a href='https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes'>ISO 639-1</a> language code"},"country":{"type":"string","description":"2 digit <a href='https://en.wikipedia.org/wiki/ISO_3166-1'>ISO 3166-1</a> country code"}}:*/
	 
	function setLocale($language, $country) {
		$this->set("language",$language);
		$this->set("country",$country);
		
		$this->merge("og",array(
			"locale"=>$language."_".$country
		));
	}
	
	function includeContent($file,$out = false) {
		if (is_file($file)) {
			$content = $this->parseInclude($file);
		} else {
			$file = SL_WEB_PATH."/content/".$file;
			if (is_file($file)) {
				$content = $this->parseInclude($file);
			} else {
				$content = "";
			}
		}
		
		if ($out) {
			echo $content;
		} else {
			$this->content .= $content;
		}
	}
	
	function parseInclude($file) {
		$this->setDependencyFile(realpath($file));
		if (isset($_GET["fromEditor"]) && $_GET["fromEditor"] == array_shift(explode(".",array_pop(explode("/",$file))))) {
			return "<div id=\"editor-content\">".$this->__parseInclude($file,true)."</div>";
		} else {
			return $this->__parseInclude($file);
		}
	}
	
	private function __parseInclude($file,$fromEditor = false) {

		if (file_get_contents($file,false,NULL,0,12) == "!yp-content:") {
			list($header,$content) = explode("\n",file_get_contents($file),2);
			$header = json_decode(substr($header,12),true);
			$this->lastHeader = $header;
			
			if (!$this->title && !(isset($header["subContent"]) && $header["subContent"]) && isset($header["title"])) $this->setTitle($header["title"]);
			if (isset($header["description"])) $this->setDescription($header["description"]);
			
			$this->info = $header;
			
			if (!$fromEditor && (strpos($content,'<?php') !== false || strpos($content,'<?=') !== false || strpos($content,'id="preview-php-') !== false)) {
				if ($this->fromEditor()) {
					$content = preg_replace('/<\\?.*(\\?>|$)/Us', '<!-- PHP CODE -->',$content);
				} else {        
					$incFile = self::contentIncPath($file,setAndTrue($this->request["params"],"PHPPrev") ? "prev.inc.php" : "inc.php");
					
					if (!setAndTrue($this->request["params"],"PHPPrev")) file_put_contents($incFile,$content);
					
					$this->parseFileData($file);
					
					ob_start();
					showErrors();
					
					include($incFile);
					restoreErrors();
					$content = ob_get_clean();	
				}
			}
			return $this->parseContent($content);
		}
		return file_get_contents($file);
	}
	
	private static function contentIncPath($file,$ext) {
		$path = explode('/',$file);
		$file = array_pop($path);
		$path[] = "inc";
		
		if (!is_dir(implode('/',$path))) mkdir(implode('/',$path));
		return implode('/',$path).'/'.str_replace('.html','.'.$ext,$file);
	}
	
	function parseFileData($file) {
		$content = file_get_contents($file);
			
		$pos = 0; $dataCnt = 0;
		while (($pos = strpos($content,"<data>",$pos)) !== false) {
			if (($end = strpos($content,"</data>",$pos)) !== false) {
				$xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".substr($content,$pos, $end - $pos + 7);
				$xmlFile = SL_DATA_PATH."/cache/wd-".safeFile($_SERVER["REQUEST_URI"])."-".$dataCnt.".xml";
			
				file_put_contents($xmlFile,$xml);
				
				libxml_use_internal_errors(true);
				
				if ($xml = json_decode(json_encode(simplexml_load_file($xmlFile)),true)) {
					$this->repData = array_merge($this->repData,$xml);
				}
				$content = substr($content,0,$pos).substr($content,$end + 7);
				$dataCnt++;
			} else {
				$pos += 6;
			}
		}
	}
	
	function parseContent($content) {
		$pos = 0;
		while (($pos = strpos($content,"<data>",$pos)) !== false) {
			if (($end = strpos($content,"</data>",$pos)) !== false) {
				$content = substr($content,0,$pos).substr($content,$end + 7);
			} else {
				$pos += 6;
			}
		}
		
		$content = $this->parseShortCodes($content);
		return $content;
	}
	
	private function fastLoad(&$content) {
		//if ($this->fastLoadComplete) return;
		//$this->fastLoadComplete = true;

		//error_reporting(E_ERROR | E_WARNING | E_PARSE);
		//ini_set("display_errors", 1);
		
		$this->inf('# fastLoad');
		$startTs = microtime(true);
		
		libxml_use_internal_errors(true);
 
		$DOM = new DOMDocument;
		$DOM->loadHTML($content);
		$md5 = md5($content);
		
		$items = $DOM->getElementsByTagName('body');

		$classes = $items->item(0)->getAttribute("class") ? explode(" ",$items->item(0)->getAttribute("class")) : array();
		$classes[] = 'FL_BG';
		$items->item(0)->setAttribute("class",implode(" ",$classes));
		
		$items = $DOM->getElementsByTagName('img');
	
		$name = SL_WEB_PATH."/img/FL-".CURRENT_PAGE_NAME;
		$infoCacheFile = $name.'-info.json';
		
		$cached = false;
		$info = array("ids"=>array(),"ssids"=>array(),"md5"=>$md5);
		
		if (is_file($name.'.js') && is_file($infoCacheFile)) {
			$cached = json_decode(str_replace('window._FL_INFO=','',file_get_contents($name.'.js')),true);
			$info = json_decode(file_get_contents($infoCacheFile),true);
			if ($info["md5"] != $md5) {
				$info["md5"] = $md5;
				$cached = false;
			} else {
				$cacheTime = filemtime($name.'.js');

				foreach ($cached["images"] as $image) {
					if (filemtime($image["file"]) > $cacheTime) {
						$cached = false;
						break;
					}
				}
			}
		}
		
		if (!$cached) {
			$info["ids"] = array();
			$info["ssids"] = array();
			
			require_once(SL_INCLUDE_PATH."/class.spriteSheet.php");
			$lq = new spriteSheet();
			
			$alphaSS = new spriteSheet();
			$alphaSS->retina = true;
			$alphaSS->load(SL_WEB_PATH."/img/fl-ss");
			
			$styles = array();
			$cnt = 0;
			foreach ($items as $item) {
				if ($item->getAttribute("data-no-fast-load")) {
					$this->inf('* data-no-fast-load set');
					continue;
				}
					
				if ($file = $item->getAttribute("src")) {

					$file = str_replace(WWW_BASE,"",$file);
					$ext = strtolower(array_pop(explode(".",$file)));
					if ($ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "gif") {
						
						if (strpos($file,"//") !== false) continue; //TODO handle remote urls

						if (substr($file,0,strlen(WWW_BASE)) == WWW_BASE) $file = substr($file,strlen(WWW_BASE));
						if (substr($file,0,strlen(WWW_RELATIVE_BASE)) == WWW_RELATIVE_BASE) $file = substr($file,strlen(WWW_RELATIVE_BASE));
						
						$TWOX = str_replace(".".$ext,"@2x.".$ext,$file);
						
						$check = array(
							SL_WEB_PATH."/".$file,
							SL_TEMPLATE_PATH."/".$file,
							SL_WEB_PATH."/".$TWOX,
							SL_TEMPLATE_PATH."/".$TWOX
						);
						
						foreach ($check as $n=>$file) {
														
							if (is_file($file)) {
								$size = getimagesize($file);							
							
								$id = $item->getAttribute("id") ? $item->getAttribute("id") : "fl-".self::FLID($file);
								if ($ext == "png") { //Just placeholder
									$image = $lq->add($file,1,"just-placeholder",$id);
									if ($ext == "png" && $size[0] * $size[1] <= 100000) {
										if ($item->getAttribute("id")) $id = "fl-".self::FLID($file);
										$alphaSS->add($file,1,false,$id);
										$info["ssids"][] = "#".$id;
									}
								} elseif ($size[0] * $size[1] >= 300000) {
									$image = $lq->add($file,0.25,true,$id);
									$info["ids"][] = "body.fl-ready #".$id;
								}
								break;
							}
						}				
					}
				}
				$cnt ++;
			}
			$cached = array(
				"images"=>$lq->images,
				"ss"=>$alphaSS->images,
				"w"=>$lq->width(),
				"h"=>$lq->height(),
				"ssw"=>$alphaSS->width(),
				"ssh"=>$alphaSS->height(),
			);
		}
		
		
		foreach ($items as $item) {
			if ($item->getAttribute("data-no-fast-load")) continue;if ($item->getAttribute("data-no-fast-load")) {
				$this->inf('* data-no-fast-load set');
			
				continue;
			}
			if ($file = $item->getAttribute("src")) {
									
				$file = str_replace(WWW_BASE,"",$file);
				if (strpos($file,"//") !== false) continue; //TODO handle remote urls
						
				if (substr($file,0,strlen(WWW_BASE)) == WWW_BASE) $file = substr($file,strlen(WWW_BASE));
				if (substr($file,0,strlen(WWW_RELATIVE_BASE)) == WWW_RELATIVE_BASE) $file = substr($file,strlen(WWW_RELATIVE_BASE));
				
				$TWOX = str_replace(".".$ext,"@2x.".$ext,$file);
				
				$check = array(
					SL_WEB_PATH."/".$file,
					SL_TEMPLATE_PATH."/".$file,
					SL_WEB_PATH."/".$TWOX,
					SL_TEMPLATE_PATH."/".$TWOX
				);
				
				foreach ($check as $n=>$file) {
					if (is_file($file)) {
						$ssid = "fl-".self::FLID($file);
						$id = $item->getAttribute("id") ? $item->getAttribute("id") : $ssid;
						
						$done = false;
						foreach ($cached["ss"] as $n=>$image) {
							if ($image["id"] == $ssid) {
								if (!$item->getAttribute("id")) $item->setAttribute("id", $image["id"]);
								
								$item->setAttribute("data-fast-load", $image["id"]);
								$item->setAttribute("data-ss", "1");
								$item->setAttribute("src", 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
								
								$done = true;
								break;
							}
						}
						if (!$done) {
							foreach ($cached["images"] as $n=>$image) {
								if ($image["id"] == $id) {
									if (!$item->getAttribute("id")) $item->setAttribute("id", $image["id"]);
									
									$item->setAttribute("src", $image["placeholder"]);
									if (strpos($item->getAttribute("style"),"background-color") === false) {
										$item->setAttribute("style","background-color:".$image["bgcolor"].";".$item->getAttribute("style"));
									}
									if (setAndTrue($image,"justPlaceholder")) {
										$item->setAttribute("data-dload", $image["src"]);
									} else {					
										$item->setAttribute("data-fast-load", $image["id"]);
									}
									break;
								}
							}		
						}	
						
						
						break;
					}
				}
			}
		}
		
		$els = $DOM->getElementsByTagName('*');
		foreach ($els as $item) {
			if ($item->getAttribute("data-no-fast-load")) {
					$this->inf('* data-no-fast-load set');
					continue;
				}
			
			if (strtolower($item->nodeName) == "iframe" && $item->getAttribute('src') != "about:blank" && strpos($item->getAttribute('src'),'//') === false) {
				$item->setAttribute("data-dload", $item->getAttribute('src'));
				$item->setAttribute('src','about:blank');
			}
			
			if (($style = $item->getAttribute("style")) && strpos($style,'background-image') !== false) {
				preg_match('/background\-image\s*\:\s*url\([\'\"]?(.*?)[\'\"]?\)\;?/',$style,$match);
				if (strpos($style,'background-color') === false) {
					require_once(SL_INCLUDE_PATH."/class.slImage.php");
		

					$src = $match[1];
					if (substr($src,0,strlen(WWW_RELATIVE_BASE)) == WWW_RELATIVE_BASE) $src = substr($src,strlen(WWW_RELATIVE_BASE));
					
					$check = array(
						SL_WEB_PATH."/".$src,
						SL_TEMPLATE_PATH."/".$src
					);
					foreach ($check as $n=>$src) {
						if (is_file($src) && ($size = getimagesize($src))) break;
					}
					if (is_file($src)) {
						$slm = new slImage();
						$slm->fromFile($src);
						$style = 'background-color:'.$slm->averageColor(255).";".$style;
					}
				}
				$style = str_replace($match[0],'',$style);
				$item->setAttribute('style',$style);
				$item->setAttribute('data-dload',$match[1]);
			}
		}
		
		$styles[] = implode(",",$info["ssids"])."{background-image:url('".WWW_BASE."img/fl-ss.png')}";
		
		$content = $DOM->saveHTML();
		
		foreach ($cached["images"] as $n=>$image) {
			if (setAndTrue($image,"justPlaceholder")) continue;
			$id = $image["id"];
			$scaleX = $image["srcW"] / ($image["x2"] - $image["x1"]);
			$scaleY = $image["srcH"] / ($image["y2"] - $image["y1"]);
			$styles[] = '#'.$id."{background-position:-".($image["x1"]*$scaleX)."px -".($image["y1"]*$scaleY)."px; background-size:".($cached["w"] * $scaleX)."px ".($cached["h"] * $scaleY)."px;}";
		}		
		
		$styles[] = implode(",",$info["ids"])."{background-image:url('".webPath($name).".jpg')}";
		
		if (isset($lq)) {
			$lq->out($name);
			file_put_contents($infoCacheFile, json_encode($info));
		}
		
		if (isset($alphaSS)) {
			file_put_contents(SL_WEB_PATH."/img/ss.js",'window._FL_SS='.json_encode($alphaSS->save()));
		}
		
		if ($info["ids"]) {
			$content = str_replace(
				'</head>','<style>'.implode("\n",$styles).'</style><script>window._FL_PRELOAD='.json_encode(array(webPath($name))).'</script>'.
				'<script src="'.webPath($name,true).'.js"></script><script src="'.WWW_RELATIVE_BASE.'js/fast-load.js"></script>'.
				(isset($alphaSS)?'<script src="'.WWW_RELATIVE_BASE.'img/ss.js"></script>':'').
				'</head>',
			$content);
		}
	}
	
	private static function FLID($file) {
		return substr(preg_replace('/[^A-Za-z0-9]+/','',base64_encode(md5($file,true))),0,16);
	}
	
	function parseShortCodes($__content) {
		if (preg_match_all('/\[\!(\w[\w\d\-]*)\:?([^\]]*)\]/',$__content,$match)) {
			for ($i = 0; $i < count($match[0]); $i++) {
				if (is_file(SL_WEB_PATH."/shortcode/".safeFile($match[1][$i]).".css")) $this->addCSS("shortcode/".safeFile($match[1][$i]).".css"); 
				if (is_file(SL_WEB_PATH."/shortcode/".safeFile($match[1][$i]).".js")) $this->addScript("shortcode/".safeFile($match[1][$i]).".js","text/javascript","body-end");
			}
		}
			
		$__pos = 0;
		$__match = array();
		while (preg_match('/\[\!(\w[\w\d\-]*)\:?([^\]]*)\]/',$__content,$__match,0,$__pos)) {
			$params = json_decode("[".$this->quoteParams(str_replace(array('__RB__','__LB__','__LT__','__GT__'),array('[',']','<','>'),$__match[2]))."]",true);
			$include = SL_WEB_PATH."/shortcode/".safeFile($__match[1]).".php";
			
			$inner = "";
			$endTag = '[/!'.$__match[1].']';
			if (($end = strpos($__content,$endTag,$__pos)) !== false) {
				if (($p2 = strpos($__content,$__match[0],$__pos)) !== false) {
					$p2 += strlen($__match[0]);
					$inner = $this->parseShortCodes(substr($__content,$p2,$end-$p2));
				}
			}
			
			if (is_file($include)) {
				ob_start();
				include($include);
				$rep = ob_get_clean();
			} else $rep = "<!-- Shortcode [!".$__match[1].": ... ] not recognized -->";
			
			if (!is_string($__match[0])) break;
			
			if ($end) {
				$__content = substr($__content,0,$p2).$rep.substr($__content,$end + strlen($endTag));
				$__pos += strlen($rep);
			} elseif (($__pos = strpos($__content,$__match[0])) !== false) {
				$__content = substr($__content,0,$__pos).$rep.substr($__content,$__pos+strlen($__match[0]));
				$__pos += strlen($rep);
			} else {
				$__pos += strlen($__match[1]);
			}
		}
		
		while (preg_match('/(`[\w\d `\.]+`)/',$__content,$__match,0)) {
			$m = explode('.',$__match[0]);
			$ob = $this->repData;
			for ($i = 0; $i < count($m); $i++) {
				$n = str_replace('`','',$m[$i]);
				if (isset($ob[$n])) {
					$ob = $ob[$n];
				} else break;
			}
			$__content = str_replace($__match[0],is_array($ob) ? json_encode($ob) : $ob,$__content);
		}
		return $__content;
	}
	
	function quoteParams($params) {
		$pOrig = $params;
		$match = array();
		$p2 = str_replace("'","\"",$params);
		$pos = 0;
		while ($pos < strlen($p2) && ($pos = strpos($p2,"\"",$pos)) !== false) {
			$start = $pos;
			$q = substr($params,$pos,1);
			do {
				$pos ++;
				if (($pos = strpos($params,$q,$pos)) === false) {
					$pos = strlen($params);
					break;
				}
			} while (substr($params,$pos-1,1) == "\\");
			$pos ++;
			$oldStr = substr($params,$start,$pos-$start);
			$newStr = str_replace(",","__COMMA__",$oldStr);
			$params = substr($params,0,$start).$newStr.substr($params,$pos);
			$pos += strlen($newStr) - strlen($oldStr);
		}
			
		$params = explode(",",$params);
			
	
				
		foreach ($params as &$param) {
			if (substr($param,0,1) == "\"" || substr($param,0,1) == "'") {
				$param = str_replace("__COMMA__",",",$param);
			} else {
				$param = trim($param);
				if (substr($param,0,1) != "[" && substr($param,0,1) != "{" && !in_array(trim(strtolower($param)),array("true","false","null")) && !is_numeric($params)) {
					$param = json_encode($param);
				}
			}
		}
		return implode(",",$params);
	}
	
	function addHeaderScript($src,$type = "text/javascript") {
		return $this->addScript($src,$type,"header");
	}
	
	function add($item) {
		
	}
	
	function addAsyncScript($src, $type = "text/javascript", $docPos = false) {
		return $this->addScript($src, $type, $docPos, true);
	}
	
	function addScript($src, $type = "text/javascript", $docPos = false, $async = false) {
		if (strpos($src,"//") === false) $src = WWW_RELATIVE_BASE.$src;
		if (strpos($src,"?") && $type == "text/javascript") {
			$src = explode("?",$src);
			$params = slRequestInfo::decodeGet(array_pop($src), true);
			$src = $src[0];
		} else {
			$params = false;
		}
		
		if ($async) {
			if ($params === false) {
				$params = array("_ASYNC"=>true);
			} else {
				$params["_ASYNC"] = true;
			}
		}
		
		$n = $src."|".$type."|".$this->includeCnt++;
		
		if ($docPos === false) {
			$docPos = $this->docPos;
		} else {
			$docPos = "!".$docPos;
		}
		
		if (!isset($this->includes[$docPos][$n])) $this->includes[$docPos][$n] = array(); 
		
		if ($params) $this->includes[$docPos][$n] = array_merge($this->includes[$docPos][$n],$params);
	}
	
	function addHeaderCSS($src) {
		return $this->addScript($src,"text/css","header");
	}
	
	function addCSS($src) {
		if ($this->get('fast-load') && !DEV_MODE && strpos($src,"//") === false && strpos($src,"font") === false && strpos($src,'bootstrap') === false && strpos($src,'jquery') === false) {
			$this->addScript($src,"text/css");
			if (is_file(SL_WEB_PATH."/img/fl/preload-".md5($src).".js")) $this->addScript("img/fl/preload-".md5($src).".js","text/javascript","body-end");
			$src = WWW_BASE."fl/?s=".urlencode($src);
		}
		$this->addScript($src,"text/css");
	}
	
	function addFont($name,$type = "google") {
		switch ($type) {
			case "google":
				$this->addCSS("//fonts.googleapis.com/css?family=".urlencode(urldecode($name)));
				break;
		}
	}
	
	function currentPage() {
		return substr($this->request["uri"],1);
	}
	
	function loginCheck() {
		if (!$GLOBALS["slSession"]->isLoggedIn()) {
			$_SESSION["LOGIN_RET"] = CURRENT_PAGE;
			$this->redirect("login/");
		}
	}
	
	function forceHTTPS() {
		if (!$this->isHTTPS() && !setAndTrue($GLOBALS["slConfig"]["dev"],"bypassHTTPS")) {
			$this->redirect("https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"].(strpos($_SERVER["REQUEST_URI"],"?") !== false ? "&" : "?").session_name()."=".session_id());
		}
	}
	
	function isHTTPS() {
		return isset($GLOBALS["_SERVER"]['HTTPS']);
	}
	
	function redirect($url) {
		ob_clean();
		header("Location: ".$url);
		exit();
	}
	
	function addStyleSheet($src) {
		$this->extraHeader[] = "<link type=\"text/css\" rel=\"stylesheet\" href=\"".$src."\" />";
	}
	
	function setCaching($cache) {
		$this->cacheContent = $cache;
	}
	
	function setSearchIndex($si) {
		$this->searchIndex = $si;
	}

	function addCacheVar($var,$revalidate = false) {
		$this->cacheVars[] = array($var,$revalidate);
	}
	
	function expandableDiv() {
		$this->addScript("inc/js/web/extras.js");
		return "onclick=\"return expandableDivClick(event,this)\"";
	}
	
	function getNavElement() {
		if (!$this->nav) {
			$this->nav = $this->getPages(true);
			reset($this->nav);
		}
		
		if (list($name,$info) = each($this->nav)) {
			$info["urlName"] = $name;
			return $info;
		}
		return false;
	}
	
	function getPages($filter = false) {
		$rv = array();
		if ($dp = opendir(SL_WEB_PATH)) {
			while ($file = readdir($dp)) {
				$path = SL_WEB_PATH."/".$file;
				$name = explode(".",$file);
				$ext = array_pop($name);
				$name = implode(".",$name);
				if (is_file($path) && (($ext == "php" && substr($name,-4) != ".inc") || $ext == "html")) {
					$rv[$name] = array(
						"dynamicFile"=>$path						
					);
				}
			}
			closedir($dp);
		}
		
		if ($dp = opendir(SL_WEB_PATH."/content")) {
			while ($file = readdir($dp)) {
				$path = SL_WEB_PATH."/content/".$file;
				$name = explode(".",$file);
				$ext = array_pop($name);
				$name = implode(".",$name);
				if (is_file($path) && (($ext == "php" && substr($name,-4) != ".inc") || $ext == "html")) {
					if (file_get_contents($path,false,NULL,0,12) == "!yp-content:") {
						list($header,$content) = explode("\n",file_get_contents($path),2);
						$info = json_decode(substr($header,12),true);
						$info["contentFile"] = $path;
					} else {
						$info = array();
					}
					
					if (isset($info["relatedPage"])) {
						$pName = $name;
						$name = $info["relatedPage"];
						
						if (!isset($rv[$name])) $rv[$name] = array();
						if (!isset($rv[$name]["children"])) $rv[$name]["children"] = array();
						$rv[$name]["children"][$pName] = $info;
						
					} else {					
						if (isset($rv[$name])) {
							$rv[$name] = array_merge($rv[$name],$info);
						} else $rv[$name] = $info;
					}
				}
			}
			closedir($dp);
		}
		
		if ($filter === true) {
			foreach ($rv as $n=>$v) {
				if (!(isset($v["showInNav"]) && $v["showInNav"])) unset($rv[$n]);
			}
		} elseif ($filter === "editor") {
			foreach ($rv as $n=>$v) {
				if (!(isset($v["contentFile"]))) unset($rv[$n]);
			}
		}
		
		foreach ($rv as $n=>$v) {
			if (isset($v["parent"]) && $v["parent"]) {
				$this->attachToParent($rv,$v["parent"],$n,$v);
				unset($rv[$n]);
			}
		}
		
		foreach ($rv as $n=>&$v) {
			$v["n"] = $n;
		}
		unset($v);
		
		uasort($rv, array($this, sortPages));
		
		return $rv;
	}
	
	private function sortPages($a,$b) {
		return strcmp($a["n"], $b["n"]);
	}
	
	function getPageInfo($path) {
		if (!is_file($path)) return false;
		if (file_get_contents($path,false,NULL,0,12) == "!yp-content:") {
			list($header,$content) = explode("\n",file_get_contents($path),2);
			$info = json_decode(substr($header,12),true);
			$info["contentFile"] = $path;
			$info["content"] = $content;
		} else {
			$info = array();
		}
		return $info;
	}
	
	function attachToParent(&$pages,$parent,$childName,$child) {
		foreach ($pages as $n=>&$v) {
			if ($n == $parent) {
				if (!isset($v["children"])) $v["children"] = array();
				$v["children"][$childName] = $child;
				return;
			}
			if (setAndTrue($v,"children")) {
				$this->attachToParent($v["children"],$parent,$childName,$child);
			}
		}
	}
	
	function get($n,$def = null) {
		return isset($this->vars[$n]) ? $this->vars[$n] : (isset($this->info[$n]) ? $this->info[$n] : $def);
	}
	
	function set($n,$v) {
		if (in_array($n,$this->settingNames)) {
			$this->settings[$n] = $v;
			$func = toCamelCase("set ".$n);
			if (method_exists($this,$func)) call_user_func(array($this,$func),$v);
		} else $this->vars[$n] = $v;
	}
	
	function merge($n,$v) {
		if (isset($this->vars[$n]) && is_array($this->vars[$n]) && is_array($v)) {
			$this->vars[$n] = array_merge($this->vars[$n],$v);
		} else $this->vars[$n] = $v;
	}
	
	function setTemplate($v) {
		if (!defined('SL_WEB_TEMPLATE') && $GLOBALS["slConfig"]["web"]["template"] != $v) define('SL_WEB_TEMPLATE',WWW_BASE.(setAndTrue($this->request,"dev")?"dev/":"")."templates/".$v);
		if (!defined('SL_TEMPLATE_PATH')) define('SL_TEMPLATE_PATH',SL_WEB_PATH."/templates/".$v);
		$this->templateDir = $GLOBALS["slConfig"]["root"]."/web/".(setAndTrue($this->request,"dev")?"dev/":"")."templates/".$v;
		if (!is_dir($this->templateDir)) return $this->error('Template does not exist ('.$this->templateDir.').');
	}
	
	function addModule() {
		$args = func_get_args();
		$name = array_shift($args);
		$className = toCamelCase($name);
		$path = SL_WEB_PATH."/inc/".safeFile($name);
		$inc = $path."/class.".$className.".php";
		
		if (is_file($inc)) {
			require($inc);
			if (class_exists($className)) {
				$mod = new $className($this);
				call_user_func_array(array($mod,"init"),$args);
				
				if ($mod->getAlias()) $this->{$mod->getAlias()} = $mod;
			} else die ('Module class ('.$className.') not found.');			
		} else die ('Module ('.$inc.') not found.');
	}


	private function inf($txt) {
		if ($txt === false) $this->dbgInfo = false;
		if ($this->dbgInfo === false) return;
		if (count($this->dbgInfo)) {
			$cnt = substr_count(substr($txt, 0, 2), '#');
			if ($cnt) $txt = str_repeat("\n", 3 - $cnt).$txt;
		}
		$this->dbgInfo[] = $txt;
	}

	private function cleanup($txt = true) {
		if ($txt !== true) $this->inf($txt);

		if ($this->dbgInfo !== false) {
			$dir = SL_DATA_PATH.'/log/page-info';
			@mkdir($dir, 0777, true);

			$this->inf('Render took '.(microtime(true) - $this->renderStart).'s');

			file_put_contents($dir.'/'.CURRENT_PAGE_NAME.'.md', implode("\n", $this->dbgInfo));
		}
		return $txt;
	}
}

function shortCodeParams() {
	$args = func_get_args();
	return str_replace(array('[',']','<','>'),array('__RB__','__LB__','__LT__','__GT__'),substr(json_encode($args),1,-1));   
}

function stripImgThumb($img) {
	$img = explode(";",$img);
	array_pop($img); 
	return implode(";",$img);
}

class slWebModule {
	protected $web;
	
	public function __construct($web) {
		$this->web = $web;
	}
	
	public function init() {
	}
	
	public function getAlias() {
		return false;
	}
}
