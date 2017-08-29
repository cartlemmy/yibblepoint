<?php

class multiPage {
	private $pages = array();
	
	function add($title,$html = false) {
		if ($html === false) $html = ob_get_clean();
		$this->pages[] = array(
			"title"=>$title,
			"html"=>$html
		);
	}

	function addNoNav($title,$html = false) {
		if ($html === false) $html = ob_get_clean();
		$this->pages[] = array(
			"title"=>$title,
			"html"=>$html,
			"noNav"=>true
		);
	}
	
	function render() {
		$jsData = array("pages"=>array());
		?><div id="multi-page-nav" style="position:absolute;top:0;left:0;width:100%;height:48px;">
			<button id="multi-page-prev" type="button" style="float:left;visibility:hidden;width:15%" class="btn btn-primary">&larr;<span class="hidden-xs"> Prev</span></button>
			<div style="float:left;width:70%;height:48px"><div id="multi-page-info" style="height:48px;margin:0 20px 0 20px"></div></div>
			<button id="multi-page-next" type="button" style="float:right;visibility:hidden;width:15%" class="btn btn-primary"><span class="hidden-xs">Next </span>&rarr;</button>
		</div>
		<div id="multi-page-loading"><div>Loading...</div></div><?php
		foreach ($this->pages as $n=>&$page) {
			$id = "multi-page-".$n;
			$page["id"] = $id;
			echo "<div class=\"multi-page\" id=\"$id\" style=\"top:0px;display:none;\" class=\"multi-page\">\n".str_replace("_ON_MULTI_PAGE_LOAD","_ON_MULTI_PAGE_LOAD".$n,$page["html"])."\n</div>\n";
			$d = $page;
			unset($d["html"]);
			$jsData["pages"][] = $d;
		}
		echo "<script type=\"text/javascript\">var multiPageData = ".json_encode($jsData).";</script>\n";
	}
}
