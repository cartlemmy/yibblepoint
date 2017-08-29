<?php

require_once(SL_INCLUDE_PATH."/class.wiki.php");

if (!trim($inner)) return;

echo "--- HERE ---";
$inner = explode("\n",trim($inner));

$name = array_shift($inner);
$request = array_shift($inner);
$params = array_shift($inner);
$description = implode("\n",$inner);

?><h3><?=$name;?></h3>
<pre><?=$request;?></pre>
<?=wiki::wikify(trim($description));?>
