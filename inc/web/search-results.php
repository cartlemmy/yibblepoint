<?php

require_once(SL_INCLUDE_PATH."/class.slSearchIndexer.php");
$indexer = new slSearchIndexer(SL_DATA_PATH."/web-index/".$GLOBALS["slConfig"]["international"]["language"]);

if ($res = $indexer->search($q)) {
	if ($res["suggestion"]) {
		if (!$res["results"] && $res["suggestionResults"]) {
			$res["results"] = $res["suggestionResults"];
			echo "Showing results for '<i>".$res["suggestion"]."</i>'<br /><br />";
		} else {
			echo "Did you mean '<i><a href=\"?q=".urlencode($res["suggestion"])."\">".$res["suggestion"]."</a></i>'?<br /><br />";
		}
	}
		
	if ($res["results"]) {
		foreach ($res["results"] as $r) {
			$url = substr(WWW_BASE,0,-1).$r["name"]
			?><div class="search-result" onclick="window.location.href='<?=$url;?>'">
				<a href="<?=$url;?>"><h2><?=isset($r["title"]) && $r["title"] ? $r["title"] : "Untitled Page";?></h2></a>
				<a href="<?=$url;?>"><i><?=$r["name"];?></i></a>
				<?php
				if (isset($r["description"]) && $r["description"]) {
					echo "<p>".$r["description"]."</p>\n";
				}
				
				if (isset($r["highlighted"]) && $r["highlighted"]) {
					echo "<p>".$r["highlighted"]."</p>\n";
				}
				?>
			</div><?php
		}
	} else {
		?><i>No results found.</i><?php
	}
}
