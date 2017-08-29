<div style="margin-top:5px"><?php
 
$this->addScript("js/slGallery.js"); 

if (substr($params[0],0,3) == "NG-") {
	require_once(SL_INCLUDE_PATH."/class.slWordpress.php");
	require_once(SL_WEB_PATH."/inc/wp-config.php");
	$wp = new slWordpressQuery($GLOBALS["WORDPRESS_CONFIG"]);
	require_once(SL_WEB_PATH."/inc/custom-parsers.php");
	
	echo nggallery($this,(int)substr($params[0],3));
} else {
	include_once(SL_INCLUDE_PATH."/class.slGallery.php");

	$gallery = new slSwipeGallery('super',$params[0]);

	$gallery->setParam("imageDir",SL_WEB_PATH."/images/gallery/".(isset($params[1]) ? $params[1] : $params[0]));
	$gallery->setParam("showCaption",isset($params[2]) ? $params[2] : false);
	$gallery->setParam("padding",isset($params[3]) ? $params[3] : 10);

	$gallery->render();
}

  ?></div>
