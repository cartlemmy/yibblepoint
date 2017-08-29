<?php

class slInstance extends slClass {
	private $requestInfo = null;
	private $core;
	private $messages = array();
	private $optionalIncludes = array(
		"value"=>array("core/value.js"),
		"net"=>array("core/sl.js","net/*"),
		"app"=>array("!net","app/*","!value"),
		"visuals"=>array("ui/efx.js"),
		"field"=>array("!net","!visuals","ui/field.js","ui/fieldPrompt.js","ui/fieldValidator.js","!value","ui/messageBox.js"),
		"webform"=>array("!field","ui/webForm.js","ui/icon.js","ui/suggestions.js")
	);
			
	private $mime = array(
		"acx"=>"application/internet-property-stream",
		"ai"=>"application/postscript","aif"=>"audio/x-aiff",
		"aifc"=>"audio/x-aiff","aiff"=>"audio/x-aiff",
		"asf"=>"video/x-ms-asf","asr"=>"video/x-ms-asf",
		"asx"=>"video/x-ms-asf","au"=>"audio/basic",
		"avi"=>"video/x-msvideo","axs"=>"application/olescript",
		"bas"=>"text/plain","bcpio"=>"application/x-bcpio",
		"bin"=>"application/octet-stream","bmp"=>"image/bmp",
		"c"=>"text/plain","cat"=>"application/vnd.ms-pkiseccat",
		"cdf"=>"application/x-cdf","cdf"=>"application/x-netcdf",
		"cer"=>"application/x-x509-ca-cert",
		"class"=>"application/octet-stream","clp"=>"application/x-msclip",
		"cmx"=>"image/x-cmx","cod"=>"image/cis-cod",
		"cpio"=>"application/x-cpio","crd"=>"application/x-mscardfile",
		"crl"=>"application/pkix-crl","crt"=>"application/x-x509-ca-cert",
		"csh"=>"application/x-csh","css"=>"text/css",
		"dcr"=>"application/x-director","der"=>"application/x-x509-ca-cert",
		"dir"=>"application/x-director","dll"=>"application/x-msdownload",
		"dms"=>"application/octet-stream","doc"=>"application/msword",
		"dot"=>"application/msword","dvi"=>"application/x-dvi",
		"dxr"=>"application/x-director","eps"=>"application/postscript",
		"etx"=>"text/x-setext","evy"=>"application/envoy",
		"exe"=>"application/octet-stream","fif"=>"application/fractals",
		"flr"=>"x-world/x-vrml","gif"=>"image/gif",
		"gtar"=>"application/x-gtar","gz"=>"application/x-gzip",
		"h"=>"text/plain","hdf"=>"application/x-hdf",
		"hlp"=>"application/winhlp","hqx"=>"application/mac-binhex40",
		"hta"=>"application/hta","htc"=>"text/x-component",
		"htm"=>"text/html","html"=>"text/html","htt"=>"text/webviewhtml",
		"ico"=>"image/x-icon","ief"=>"image/ief",
		"iii"=>"application/x-iphone",
		"ins"=>"application/x-internet-signup",
		"isp"=>"application/x-internet-signup","jfif"=>"image/pipeg",
		"jpe"=>"image/jpeg","jpeg"=>"image/jpeg","jpg"=>"image/jpeg",
		"js"=>"text/javascript","latex"=>"application/x-latex",
		"lha"=>"application/octet-stream","lsf"=>"video/x-la-asf",
		"lsx"=>"video/x-la-asf","lzh"=>"application/octet-stream",
		"m13"=>"application/x-msmediaview",
		"m14"=>"application/x-msmediaview","m3u"=>"audio/x-mpegurl",
		"man"=>"application/x-troff-man","mdb"=>"application/x-msaccess",
		"me"=>"application/x-troff-me","mht"=>"message/rfc822",
		"mhtml"=>"message/rfc822","mid"=>"audio/mid",
		"mny"=>"application/x-msmoney","mov"=>"video/quicktime",
		"movie"=>"video/x-sgi-movie","mp2"=>"video/mpeg",
		"mp3"=>"audio/mpeg","mpa"=>"video/mpeg","mpe"=>"video/mpeg",
		"mpeg"=>"video/mpeg","mpg"=>"video/mpeg",
		"mpp"=>"application/vnd.ms-project","mpv2"=>"video/mpeg",
		"ms"=>"application/x-troff-ms","msg"=>"application/vnd.ms-outlook",
		"mvb"=>"application/x-msmediaview","nc"=>"application/x-netcdf",
		"nws"=>"message/rfc822","oda"=>"application/oda",
		"ogg"=>"audio/ogg","png"=>"image/png",
		"pbm"=>"image/x-portable-bitmap","pdf"=>"application/pdf",
		"pfx"=>"application/x-pkcs12","pgm"=>"image/x-portable-graymap",
		"pko"=>"application/ynd.ms-pkipko","pma"=>"application/x-perfmon",
		"pmc"=>"application/x-perfmon","pml"=>"application/x-perfmon",
		"pmr"=>"application/x-perfmon","pmw"=>"application/x-perfmon",
		"pnm"=>"image/x-portable-anymap",
		"pot"=>"application/vnd.ms-powerpoint",
		"ppm"=>"image/x-portable-pixmap",
		"pps"=>"application/vnd.ms-powerpoint",
		"ppt"=>"application/vnd.ms-powerpoint",
		"prf"=>"application/pics-rules","ps"=>"application/postscript",
		"pub"=>"application/x-mspublisher","qt"=>"video/quicktime",
		"ra"=>"audio/x-pn-realaudio","ram"=>"audio/x-pn-realaudio",
		"ras"=>"image/x-cmu-raster","rgb"=>"image/x-rgb","rmi"=>"audio/mid",
		"roff"=>"application/x-troff","rtf"=>"application/rtf",
		"rtx"=>"text/richtext","scd"=>"application/x-msschedule",
		"sct"=>"text/scriptlet",
		"setpay"=>"application/set-payment-initiation",
		"setreg"=>"application/set-registration-initiation",
		"sh"=>"application/x-sh","shar"=>"application/x-shar",
		"sit"=>"application/x-stuffit","snd"=>"audio/basic",
		"spc"=>"application/x-pkcs7-certificates",
		"spl"=>"application/futuresplash",
		"src"=>"application/x-wais-source",
		"sst"=>"application/vnd.ms-pkicertstore",
		"stl"=>"application/vnd.ms-pkistl",
		"stm"=>"text/html",
		"sv4cpio"=>"application/x-sv4cpio","sv4crc"=>"application/x-sv4crc",
		"svg"=>"image/svg+xml","swf"=>"application/x-shockwave-flash",
		"t"=>"application/x-troff","tar"=>"application/x-tar",
		"tcl"=>"application/x-tcl","tex"=>"application/x-tex",
		"texi"=>"application/x-texinfo","texinfo"=>"application/x-texinfo",
		"tgz"=>"application/x-compressed","tif"=>"image/tiff",
		"tiff"=>"image/tiff","tr"=>"application/x-troff",
		"trm"=>"application/x-msterminal",
		"tsv"=>"text/tab-separated-values","txt"=>"text/plain",
		"uls"=>"text/iuls","ustar"=>"application/x-ustar",
		"vcf"=>"text/x-vcard","vrml"=>"x-world/x-vrml","wav"=>"audio/x-wav",
		"wcm"=>"application/vnd.ms-works","wdb"=>"application/vnd.ms-works",
		"wks"=>"application/vnd.ms-works","wmf"=>"application/x-msmetafile",
		"wps"=>"application/vnd.ms-works","wri"=>"application/x-mswrite",
		"wrl"=>"x-world/x-vrml","wrz"=>"x-world/x-vrml",
		"xaf"=>"x-world/x-vrml","xbm"=>"image/x-xbitmap",
		"xla"=>"application/vnd.ms-excel","xlc"=>"application/vnd.ms-excel",
		"xlm"=>"application/vnd.ms-excel","xls"=>"application/vnd.ms-excel",
		"xlt"=>"application/vnd.ms-excel","xlw"=>"application/vnd.ms-excel",
		"xof"=>"x-world/x-vrml","xpm"=>"image/x-xpixmap",
		"xwd"=>"image/x-xwindowdump","z"=>"application/x-compress",
		"zip"=>"application/zip"
	);
	
	function __construct($core) {
		$this->core = $core;
		
		$GLOBALS["slInstance"] = $this;
		
		require_once(SL_INCLUDE_PATH."/class.slRequestInfo.php");
		$this->requestInfo = new slRequestInfo();		
		
		$GLOBALS["slConfig"]["requestInfo"] = $requestInfo = $this->requestInfo->get();
				
		$id = array_shift(explode(".",array_pop(explode("/",$requestInfo["path"]))));
		$check = SL_DATA_PATH."/comb-".$id;
		if (is_file($check)) {
			$fail = false;
			$ext = array_shift(explode("?",array_pop(explode(".",$requestInfo["path"]))));
			$outFile = SL_WEB_PATH."/".$ext."/comb/".$id.".".$ext;
			if (!is_dir(SL_WEB_PATH."/".$ext."/comb")) mkdir(SL_WEB_PATH."/".$ext."/comb");
			if ($files = json_decode(file_get_contents($check),true)) {
				$needsUpdate = false;
				if (is_file($outFile)) {
					$ts = filemtime($outFile);
					foreach ($files as $file) {
						$cts = self::MTIME($file);
												
						if (!$cts || $cts > $ts) {
							$needsUpdate = true;
							break;
						}
					}
				} else $needsUpdate = true;
				
				$needsUpdate = true;
								
				if ($needsUpdate) {	
					if ($ext == "js") {
	
						require(SL_INCLUDE_PATH."/class.slScript.php");
						$script = new slScript(array_pop(explode("/",$outFile)),true);
						$script->useAbsolutePath = true;
						
						foreach ($files as $file) {
							if (substr($file,0,2) == '//') $file = 'http:'.$file;
							$script->append('/*! '.$file.' */'."\n");
							if (($res = self::ABSLINKS($file)) !== false) {
								$script->append($res);
							} else {
								$fail = true;
							}
							$script->append(";");
						}
						error_reporting(E_ERROR | E_WARNING | E_PARSE);
						ini_set("display_errors", 1);
						ob_start();
						$script->out();
						
						file_put_contents($outFile,json_encode(headers_list())."\n".ob_get_flush());
						
						if (setAndTrue($GLOBALS["slConfig"]["web"],"enableCaching")) self::cacheFile($requestInfo["path"]);
						exit();
					} else {
						$script->useAbsolutePath = true;
						
						foreach ($files as $file) {
							if (substr($file,0,2) == '//') $file = 'http:'.$file;
							$c .= '/*! '.$file.' */'."\n";
							if (($res = self::ABSLINKS($file)) !== false) {
								$c .= $res;
							} else {
								$fail = true;
							}
						}
					}
					
					if (!$fail) file_put_contents($outFile, $c);
					if (setAndTrue($GLOBALS["slConfig"]["web"],"enableCaching")) self::cacheFile($requestInfo["path"]);
				}
			
				
				if ($ext == "js") {
					$c = explode("\n",file_get_contents($outFile),2);
					$headers = json_decode($c[0],true);
					foreach ($headers as $header) {
						header($header);
					}
					echo $c[1];
					exit();
				} else {
					$this->showFile($outFile);
				}
				exit();				
			}			
		}
		
		$requestInfo["origPath"] = $requestInfo["path"];
		
		if (substr($requestInfo["path"],-7) == ".qr.png") {
			requireThirdParty("phpqrcode");
			$link = WWW_ROOT.array_shift(explode(".qr.png",$requestInfo["uri"])).($requestInfo["rawParams"] ? "?".$requestInfo["rawParams"] : "");
			QRcode::png($link, false, 'L', defined('QR_PPP') ? QR_PPP : 16, 2);
			exit();
		}

		if (substr($requestInfo["path"],-9) == ".manifest") {
			require(SL_INCLUDE_PATH."/appManifest.php");
			exit();
		}
		
		//Are the params pointing to a tiny url?
		if ($requestInfo["path"] == "" && $requestInfo["tiny"] !== false) {
			require_once(SL_INCLUDE_PATH."/class.slURLShortener.php");
			$url = new slURLShortener();
			
			if ($url->fromID($requestInfo["tiny"])) {
				header("Location: ".$url->getLink());			
			} else return $this->pageError("Not found.");
		}
		
		//TODO: Not sure what
		/*if (substr($requestInfo["path"],0,5) == "admin") {
			$requestInfo["path"] = substr($requestInfo["path"],5);
			$this->slRequest($requestInfo);
			return;
		}*/
				
		if (isset($requestInfo["params"]["go"])) {
			
			require_once(SL_INCLUDE_PATH."/class.slURLShortener.php");
			$url = new slURLShortener();

			if ($url->fromName($requestInfo["params"]["go"])) {
				header("Location: ".$url->getLink());			
			} else return $this->pageError("Not found.");			
		}
		
		if (substr($requestInfo["path"],0,6) == "web.js") {
			require_once(SL_INCLUDE_PATH."/class.slScript.php");
			
			$script = new slScript("web.js",true);
			$script->useAbsolutePath = true;
			$script->start();
			$isWeb = true;
			require(SL_INCLUDE_PATH."/slGlobal.js");
			$script->stop();

			$includes = array(
				"core/initSlClass.js","core/general.js",
				"core/string.js","core/serializer.js",
				"core/date.js","core/bitArray.js","core/base64.js",
				"ui/webView.js"
			);
							
			foreach ($this->optionalIncludes as $n=>$files) {
				if (isset($requestInfo["params"][$n])) {
					if (!in_array("!".$n,$includes)) $includes[] = "!".$n;
				}
			}
			
			$this->parseIncludes($includes);
			
			foreach ($includes as $include) {
				$script->parse(SL_INCLUDE_PATH."/js/".$include);
			}
	
			$script->start();
			$fromAPI = 1;
			require(SL_INCLUDE_PATH."/config.js.php");
			require(SL_INCLUDE_PATH."/slLoader.js");
			require(SL_INCLUDE_PATH."/APIInit.js.php");
			
			?>
			window.addEventListener('load',function(){
				<?php if (setAndTrue($GLOBALS["slConfig"]["dev"],"delayLoad")) { ?>
					setTimeout(sl.scriptLoader,<?=((int)$GLOBALS["slConfig"]["dev"]["delayLoad"] * 1000);?>);
				<?php } else { ?>
					sl.scriptLoader();
				<?php } ?>
			});			
			<?php

			$script->stop();
	
			$script->out();
			exit();
		}
		
		if (substr($requestInfo["path"],-3) == "/dl") {
			$path = explode("&",$requestInfo["rawParams"]);
			$name = count($path) > 1 ? array_pop($path) : array_pop(explode("/",$path[0]));
			
			$path = realpath(str_replace(CORE_NAME."/../","",$requestInfo["root"]."/".substr($requestInfo["path"],0,-3)."/".$path[0]));
			$allowed = array(SL_DATA_PATH."/tmp/");
			foreach ($allowed as $p) {
				$p = str_replace("~/",$requestInfo["root"],$p);
				if ($p == substr($path,0,strlen($p))) {
					if (is_file($path)) {
						$ct = "";
						switch (strtolower(array_pop(explode(".",$path)))) {
							case "csv":
								$ct = "text/csv";
								break;
						}

						header('Content-type: $ct');
						header('Content-Disposition: attachment; filename="'.$name.'"');

						readfile($path);
					}
					break;
				}
			}
			exit();
		}
		
		if (strpos($requestInfo["path"],"favicon.ico") !== false) {
			$dir = substr($requestInfo["path"],0,CORE_NAME_LEN + 12) == CORE_NAME."/favicon.ico" ? "lib/" : "web/";
				if (!is_file($dir."favicon.ico") || (file_exists($dir."favicon.ico") && file_exists($dir."icon.png") && filemtime($dir."icon.png") > filemtime($dir."favicon.ico"))) {
				if ($im = imagecreatefrompng($dir."icon.png")) {
					$newIm = imagecreatetruecolor(16,16);
					imagealphablending( $newIm, false );
					imagesavealpha( $newIm, true );

					imagecopyresampled($newIm,$im,0,0,0,0,16,16,imagesx($im),imagesy($im));
					header("Content-type: image/png");
					imagepng($newIm,$dir."favicon.png");
					imagedestroy($im);
					imagedestroy($newIm);
					
					requireThirdParty("php-ico-master");
					$ico = new PHP_ICO($dir."favicon.png");
					$ico->save_ico($dir."favicon.ico");
				}
			}
			header("Content-type: image/ico");
			readfile($dir."favicon.ico");				
			exit();
		}
		
		if (preg_match("/icon\-\d+x\d+/",$requestInfo["path"])) {
			$dir = substr($requestInfo["path"],0,CORE_NAME + 6) == CORE_NAME."/icon-" ? "lib/" : "web/";
			$len = $dir == "lib/" ? 8 : 5;
			
			if ($im = imagecreatefrompng($dir."icon.png")) {
				list($w,$h) = explode("x",array_shift(explode(".",substr($requestInfo["path"],$len))));
				$newIm = imagecreatetruecolor($w,$h);
				imagealphablending( $newIm, false );
				imagesavealpha( $newIm, true );

				imagecopyresampled($newIm,$im,0,0,0,0,$w,$h,imagesx($im),imagesy($im));
				header("Content-type: image/png");
				imagepng($newIm);
				imagedestroy($im);
				imagedestroy($newIm);
			}
			exit();
		}
		
		if (substr($requestInfo["path"],0,9) == "my-files/") {
			$file = $GLOBALS["slSession"]->getUserDir()."/file/".substr($requestInfo["path"],9);
			if (is_file($file)) {
				$this->showFile($file,false,false,setAndTrue($requestInfo["params"],"download"));
			} else {
				header("HTTP/1.0 404 Not Found");
				echo $file." not found";					
			}
			exit();
		}
			
		if (substr($requestInfo["path"],0,CORE_NAME_LEN) == CORE_NAME) {
		
			
			if ($requestInfo["path"] == CORE_NAME) {
				header("Location: ".WWW_BASE.CORE_NAME."/");
				exit();
			}
			
			if ($requestInfo["path"] == CORE_NAME."/slSID.js") {
				$GLOBALS["slSession"] = new slSession($GLOBALS["slConfig"]);

				$GLOBALS["slSession"]->getUserStatus();

				$GLOBALS["slCore"]->db->connect(array("type"=>"user","session"=>$GLOBALS["slSession"]));
	
				require_once(SL_INCLUDE_PATH."/class.slAPILoader.php");
				$api = new slAPILoader($requestInfo,true);
				
				
				header("Content-type: application/javascript");
				//echo "/* ".print_r($requestInfo,true)." */\n\n";
				echo "sl.config.sessionName = ".json_encode(session_name()).";";
				echo "sl.config.sessionId = ".json_encode(session_id()).";";
				echo 'sl.cookie.setItem("'.SL_SID.'",sl.config.sessionId);';
	
				return;
			}
			
			if (substr($requestInfo["path"],0,CORE_NAME_LEN + 5) == CORE_NAME."/api/") {
				require_once(SL_INCLUDE_PATH."/class.slAPILoader.php");
				$api = new slAPILoader($requestInfo);
				$api->respond();
				return;
			}
			
			if (substr($requestInfo["path"],0,CORE_NAME_LEN + 10) == CORE_NAME."/my-files/") {
				$file = $GLOBALS["slSession"]->getUserDir()."/file/".substr($requestInfo["path"],14);
				if (is_file($file)) {
					$this->showFile($file,false,false,setAndTrue($requestInfo["params"],"download"));
				} else {
					header("HTTP/1.0 404 Not Found");
					echo $file." not found";					
				}
				exit();
			}
			
			if (substr($requestInfo["path"],0,CORE_NAME_LEN + 12) == CORE_NAME."/our-files/") {
				$file = $GLOBALS["slSession"]->getUserParentDir()."/file/".substr($requestInfo["path"],15);
				if (is_file($file)) {
					$this->showFile($file,false,false,setAndTrue($requestInfo["params"],"download"));
					exit();
				} else {
					header("HTTP/1.0 404 Not Found");
					echo $file." not found";					
				}
			}
			
			if ($requestInfo["path"] == CORE_NAME."/r") {
				header('Access-Control-Allow-Origin: *');
				require_once(SL_INCLUDE_PATH."/class.slNet.php");
				$net = new slNet($requestInfo);
				$net->respond();
				return;
			}
							
			$file = SL_INCLUDE_PATH."/".substr($requestInfo["path"],CORE_NAME_LEN + 1);
			if (is_file($file)) {
				$this->showFile($file);
				exit();
			}
			
			if (strpos($file,"/inc/app/") !== false) {
				$p = explode("/",substr($file, strlen(SL_INCLUDE_PATH."/app/")));
				$origFile = implode("/",$p);
				$fileName = array_pop($p);
				
				$appFile = false;
				if (array_pop($p) == "js" || is_file($appFile = SL_LIB_PATH."/app/".$origFile)) {					
					header("Content-type: application/javascript");
					
					$appName = implode("/",$p);
					if (!$appFile) $appFile = SL_LIB_PATH."/app/".$appName."/".$fileName;
						
					if (is_file($appFile)) {
						require_once(SL_INCLUDE_PATH."/class.slScript.php");
						
						$scriptName = "app.".$appName.".js";
						$script = new slScript($scriptName,true);

						$script->debug = isset($requestInfo["params"]["debug"]) && $requestInfo["params"]["debug"];
						$script->useAbsolutePath = true;
						if (isset($requestInfo["params"]["aid"])) {
							if (isset($requestInfo["params"]["mn"])) {
								$script->append('(function(){var app = global.handles.app['.$requestInfo["params"]["aid"].'], self = global.handles.app['.$requestInfo["params"]["aid"].'].modules['.$requestInfo["params"]["mn"].'];');
							} else {
								$script->append('(function(){var self = global.handles.app['.$requestInfo["params"]["aid"].'];');
							}
							$script->parse($appFile,true);
							$script->append('})();');
						} else {
							$script->parse($appFile,true);
						}						
						$script->out(true);
						exit();
					}
				} else {
					$appFile = explode("/",str_replace("/inc/app/","/lib/app/",$file));
					$i = count($appFile) - 1;
					$appFile[$i] = "resources/".$appFile[$i];
					$appFile = implode("/",$appFile);
					if (is_file($appFile)) {
						$this->showFile($appFile);
						exit();
					}
				}
			}

			$file = explode("/inc/",$file);
			$file = $file[0]."/inc/handlers/".array_shift(explode("/",$file[1])).".php";
			
			if (is_file($file)) {
				$this->showFile($file,true);
				exit();
			}
		
			$requestInfo["path"] = substr($requestInfo["path"],CORE_NAME_LEN);
			$this->slRequest($requestInfo);
			return;
		}
		
		if (substr($requestInfo["path"],-1) == "/") $requestInfo["path"] = substr($requestInfo["path"],0,-1);
			
		$file = SL_INCLUDE_PATH."/handlers/".array_shift(explode("/",$requestInfo["path"])).".php";
		if (is_file($file)) {
			$this->showFile($file,true);
			exit();
		}
				
		if (substr($requestInfo["path"],0,6) == "qr.png") {
			requireThirdParty("phpqrcode");
			QRcode::png($requestInfo["rawParams"], false, 'L', defined('QR_PPP') ? QR_PPP : 16, 4);
			exit();
		}
		
		if (substr($requestInfo["path"],0,11) == "barcode.png") {
			require_once(SL_INCLUDE_PATH."/class.barcode.php");
			$options = explode(' ',$requestInfo["rawParams"]);
			$num = array_shift($options);
			$bc = new barcode();
			if (in_array('no-text',$options)) $bc->noText = true;
			if (in_array('less-pad',$options)) $bc->pad = 0.1;
			if (in_array('no-pad',$options)) $bc->pad = 0;
			
			header('Content-type: image/png');
			$bc->setPixelWidth(in_array('short',$options) ? 500 : 240);
			$bc->setPixelHeight(in_array('short',$options) ? (in_array('no-pad',$options) ? 50 : 80) : 120);
			$bc->generate($num);
			exit();
		}
			
		if (is_file($file = "inc/thirdparty/".$requestInfo["path"])) {
			$this->showFile($file);
			exit();
		}
		
		if (strpos($requestInfo["path"],"*") !== false && strpos($requestInfo["path"],".js") !== false) {
			$path = explode("/",$requestInfo["path"]);
			$pattern = "/".str_replace("\\*",".+",preg_quote(array_pop($path),"/"))."/";
			$dir = implode("/",$path);
			if (is_dir($dir)) {
				
				$orderFile = "inc/".preg_replace("/[^\w\d]+/","-",$dir);
				$this->order = is_file($orderFile) ? explode(",",file_get_contents($orderFile)) : false;

				if ($dp = opendir($dir)) {
					$files = array();
					while ($file = readdir($dp)) {
						if (preg_match($pattern,$file)) {
							$files[] = $dir."/".$file;
						}
					}
					if (strpos($pattern,".js") === false) {
						foreach ($files as $n=>$file) {
							$this->showFile($file,false,$n == 0);
							echo "\n";
						}
					} else {
						require_once(SL_INCLUDE_PATH."/class.slScript.php");
						$sls = new slScript($dir);
						
						//$sls->debug = isset($this->requestInfo->request["params"]["debug"]) && $this->requestInfo->request["params"]["debug"];
						$sls->useAbsolutePath = true;
						
						if ($this->order) {
							usort($files,array($this,"incOrder"));
						}
						
						foreach ($files as $file) {
							$sls->parse($file, true);
						}
						$sls->out();
					}
					closedir($dp);
				}
			}
			exit();
		}
		
		$njs = str_replace(".js","",$requestInfo["path"]);
		if (is_file($file = "inc/".$njs."/out.php")) {
			header('Content-type: application/javascript');
			$requestInfo["path"] = $njs;
			echo "sl.data.".array_pop(explode("/",$requestInfo["path"])).(trim($requestInfo["rawParams"])?".".$requestInfo["rawParams"]:"")."=";
			include($file);
			echo ";";
			exit();
		}
		
		if (is_file($file = "inc/".$njs."/out.js")) {
			header('Content-type: application/javascript');
			$requestInfo["path"] =$njs;
			echo "sl.data.".array_pop(explode("/",str_replace(".js","",$requestInfo["path"])))."=";
			include($file);
			echo ";";
			exit();
		}
		
		if ($requestInfo["path"] == "" || $requestInfo["path"] == "index.php") {
			$requestInfo["path"] = "home.html";
			$requestInfo["isFile"] = $requestInfo["isDir"] = false;
		}
		
		$ext = explode(".",$requestInfo["path"]);
		if (count($ext) == 1) $requestInfo["path"] .= ".html";
		$ext = count($ext) == 1 ? "html" : array_pop($ext);
				
		if ($ext != "html" && $ext != "php") {
			if (DEV_MODE) {
				$webFile = $requestInfo["root"]."/web/dev/".$requestInfo["path"];
				if (is_file($webFile)) {
					$this->showFile($webFile);
					return;
				}
			}
			
			$webFile = $requestInfo["root"]."/web/".$requestInfo["path"];
			if (is_file($webFile)) {
				$this->showFile($webFile);
				return;
			}
		}
		
		if (DEV_MODE) {
			$webTemplate = $requestInfo["root"]."/web/dev/templates/".$GLOBALS["slConfig"]["web"]["template"]."/".$requestInfo["path"];
			if (is_file($webTemplate)) {
				$this->showFile($webTemplate);
				return;
			}
		}
		
		$webTemplate = $requestInfo["root"]."/web/templates/".$GLOBALS["slConfig"]["web"]["template"]."/".$requestInfo["path"];
		if (is_file($webTemplate)) {
			$this->showFile($webTemplate);
			return;
		}
		
		$page = explode(".",$requestInfo["path"],2);
		$ext = count($page) > 1 ? array_pop($page) : "";
		
		if ($requestInfo["isFile"] || $requestInfo["isDir"]) {
			if (1 /*TODO: has file permissions*/) {
				if ($ext == "inc") exit();
				$this->showFile($requestInfo["fullPath"],$ext == "php");
				return;
			} else return $this->pageError("You do not have permission to access '".$requestInfo["path"]."'.");
		}	
		

		require_once(SL_INCLUDE_PATH."/class.slWeb.php");

		//It's a web page, render it
		$web = new slWeb($requestInfo, $GLOBALS["slConfig"]["web"]);
		
		if ($requestInfo["path"] == "_BLANK.html") {
			//Blank page for web editor
			$web->setSearchIndex(false);
			$web->setCaching(false);
		} else {
			$web->prepareWebPage();
		}
		
		$web->render();
		return;
	
	}
	
	public static function MTIME($file) {
		if (strpos($file,'//') === false) return filemtime($file);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $file);
		curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $header = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        
        if ($err) return false;
        if (isset($info["filetime"])) return $info["filetime"];
        
        return false;
	}
	
	public static function ABSLINKS($file) {
		if (strpos($file,'//') === false) {
			$webPath = webPath($file);
			$c = file_get_contents($file);
		} else {
			$webPath = $file;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $file);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
			curl_setopt($ch, CURLOPT_ENCODING , "gzip");
			
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			$c = curl_exec($ch);
			
			$err = curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200;
			
			curl_close($ch);	
								
			if ($err) return false;
		}
		
		$webPath = explode('/',$webPath);
		array_pop($webPath);
		$webPath = implode('/',$webPath);
		
		if (substr($webPath,0,strlen(WWW_BASE)) == WWW_BASE) {
			$base = 'http://'.$_SERVER["SERVER_NAME"];
		} else {
			$b = explode('/',$webPath);
			$base = $b[0].'//'.$b[2];
		}
		
		if (preg_match_all('/url\(([\'\"])?(.*?)([\'\"])?\)/',$c,$match)) {
			foreach ($match[2] as $n=>$f) {
				if (strpos($f,'//') !== false) {
					continue;
				} elseif (strpos($f,0,1) == '/') {
					$usePath = $base.$f;
				} else {
					$wp = explode('/',$webPath);
					$f2 = explode('/',$f);
					while ($f2[0] == '..') {
						array_shift($f2);
						array_pop($wp);
					}
					$usePath = implode('/',$wp).'/'.implode('/',$f2);
				}					
				$c = str_replace('url('.$match[1][$n].$f,'url('.$match[1][$n].$usePath,$c);
			}
		}
		$c = str_replace('http://','//',$c);
		return $c;
	}
		
	function parseIncludes(&$includes) {		
		foreach ($includes as $n=>$v) {
			if (substr($v,0,1) == "!") {
				if (isset($this->optionalIncludes[substr($v,1)])) {
					$files = $this->optionalIncludes[substr($v,1)];
					$this->parseIncludes($files);
					foreach ($files as $file) {
						if (!in_array($file,$includes)) $includes[] = $file;
					}
				}
				unset($includes[$n]);
			} elseif (substr($v,-2) == "/*") {
				$dir = SL_INCLUDE_PATH."/js/".substr($v,0,-2);
				if ($dp = opendir($dir)) {
					while ($file = readdir($dp)) {
						$path = $dir."/".$file;
						if (is_file($path)) {
							$includes[] = str_replace(SL_INCLUDE_PATH."/js/","",$path);
						}
					}					
					closedir($dp);
				}
				unset($includes[$n]);
			}
		}		
	}
	
	function incOrder($a,$b) {
		return array_search(array_pop(explode("/",$a)),$this->order) - array_search(array_pop(explode("/",$b)),$this->order);
	}
	
	function pageError($message) {
		$this->pageMessage($message, "error");
	}
	
	function pageMessage($message, $type = "message") {
		$this->messages[] = array($message,$type);
	}
	
	function showFile($file,$asInclude = false, $noHeader = false, $download = false, $secondsToCache = 864000) {			
		if ($asInclude) {
			require($file);
		} else {
			if ($download) {
				while (ob_get_clean()) {}
				ob_start();
			}
			$mime = $this->getMIME($file);
			
			if ($mime == "text/javascript") {
				require_once(SL_INCLUDE_PATH."/class.slScript.php");
				
				$sls = new slScript($file);
				$sls->debug = isset($this->requestInfo->request["params"]["debug"]) && $this->requestInfo->request["params"]["debug"];
				$sls->useAbsolutePath = true;

				$sls->parse($file, true);
				
				ob_start();
				
				$sls->out();
				
				if (setAndTrue($GLOBALS["slConfig"]["web"],"enableCaching")) self::cacheFile($file);
			} else {
				if (!$noHeader) {
					header('Content-type: '.$mime);
					self::cacheHeaders($secondsToCache,$file);
				}
				
				readfile($file);
				if (setAndTrue($GLOBALS["slConfig"]["web"],"enableCaching")) self::cacheFile($file);
			}
			if ($download) {
				$c = ob_get_clean();
				
				header("Content-Disposition: attachment; filename=\"".array_pop(explode("/",$file))."\"");
				header("Content-Length: ".strlen($c));
				echo $c;
				exit();
			}
		}
	}
	
	public static function cacheFile($file) {
		if (isset($_GET["getcache"])) return;

		if ($headers = headers_list()) {
			foreach ($headers as $h) {
				if (substr($h,0,15) == "X-Get-For-Cache") return;
			}
		} else return;
		
		$file = preg_replace('/templates\/.*?\//','',str_replace(SL_WEB_PATH."/",'',$file));
		$md5 = md5($file);
	
		$cache = SL_DATA_PATH.'/cache/'.substr($md5,0,2);
				
		if (!is_dir($cache)) mkdir($cache);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, WWW_BASE.$file."?getcache=1");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'X-Get-For-Cache: 1',
		));
		curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $res = curl_exec($ch);
		$size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		
		$err = curl_errno($ch);
        curl_close($ch);
        
        if ($err) return;

		$h = explode("\n",substr($res, 0, $size));
		array_shift($h);
		$content = substr($res, $size);
		
		$skipHeaders = array("Date","Server","Set-Cookie","X-Powered-By","Transfer-Encoding");
		
		$headers = array();
		foreach ($h as $header) {
			$s = explode(":",$header);
			if (trim($header) && !in_array($s[0],$skipHeaders)) $headers[] = trim($header);
			if ($s[0] == "Content-Type" && trim($s[1]) == "text/css") {				
				set_include_path(get_include_path() . PATH_SEPARATOR . SL_INCLUDE_PATH.'/thirdparty/minify/min/lib');
				require_once(SL_INCLUDE_PATH.'/thirdparty/minify/min/lib/Minify/CSS.php');
			}
		}
		
		$gzipoutput = gzencode($content,6);
		
		//header('Content-Type: application/x-download');
		$headers[] = 'Content-Encoding: gzip'; 
		$headers[] = 'Content-Length: '.strlen($gzipoutput); 
		//echo $cache."/".$md5;
		//header('Content-Disposition: attachment; filename="myfile.name"');
		//header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
		//header('Pragma: no-cache');

		file_put_contents($cache."/".$md5,json_encode($headers)."\n".$gzipoutput);
	}
	
	public static function cacheHeaders($secondsToCache = 3600, $file = false) {
		$ts = gmdate("D, d M Y H:i:s", time() + $secondsToCache) . " GMT";
		header("Expires: $ts");
		header("Pragma: cache");
		header("Cache-Control: max-age=$secondsToCache");
		if ($file && is_file($file)) {
			$etag = md5_file($file);
			$modified = filemtime($file);
			header("Etag: ".$etag); 			
			if ($file) header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modified).' GMT');
			if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $modified) || 
				(isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)) { 
				header("HTTP/1.1 304 Not Modified"); 
				exit; 
			}
		}
	}
	
	function getMIME($file) {
		$ext = strtolower(array_pop(explode(".",$file)));
		if (isset($this->mime[$ext])) return $this->mime[$ext];
		
		if (class_exists("finfo")) {
			$finfo = new finfo(FILEINFO_MIME);
			return $finfo->file($file);
		}
		return "application/unknown";
	}
	
	function slRequest($request) {				
		$request["path"] = substr($request["path"],1);
		
		$appFile = SL_LIB_PATH."/app/".$request["path"];

		if (is_dir($appFile)) {
			$request["app"] = $request["path"];
			$request["path"] = "";
		}
		
		if ($request["path"] == "") {
			require(SL_INCLUDE_PATH."/sl.php");
			return true;
		}

		if ($this->core->db->isConnected(array_shift(explode("/",$request["path"])))) {
			require_once(SL_INCLUDE_PATH."/class.slRequest.php");
			$sl = new slRequest($request, $this->core);
			$sl->output();
			return true;
		}
		
		header("HTTP/1.0 404 Not Found");
		exit();
	}
}
