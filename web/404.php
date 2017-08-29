<section id="error404">
  <div class="blurb col-xs-10 col-xs-offset-1 col-md-8 col-md-offset-2 text-center transparent">
    <h1>We're Sorry</h1>
    <p>The page you are looking for can't be found. You can return to our Home Page or try searching our website below. If you still can't find what you're looking for, try giving us a call - we'd be happy to help!</p>
    <p><span class="phone">909-867-5743</span></p>
    <p><a href="<?=WWW_RELATIVE_BASE;?>" class="btn btn-default">Back to Home</a></p>
  </div>
</section>

<?php 
/*
require_once(SL_INCLUDE_PATH."/class.slSearchIndexer.php");
$indexer = new slSearchIndexer(SL_DATA_PATH."/web-index/".$GLOBALS["slConfig"]["international"]["language"]);

if ($res = $indexer->search($q)) {		
	if ($res["results"]) {
		?><p><b>en-us|Perhaps one of the below pages contains what you were looking for:</b></p><?php
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
?></div><?php
*/
