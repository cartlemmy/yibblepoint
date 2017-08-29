<?php
//params: Youtube ID, position = "fill-parent"

$id = $params[0];
$position = isset($params[1]) ? $params[1] : "fill-parent";

?><div id="<?=$id;?>" class="youtube <?=$position;?>"><button>CLOSE</button><div>
	<iframe src="about:blank;" frameborder="0" allowfullscreen></iframe>
</div></div>


