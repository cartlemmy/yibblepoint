<?php
//params: var

if (substr($params[0],0,1) == '$') {
	echo '<span class="var">'.$params[0]."</span>";
} else {
	echo '<span class="'.gettype($params[0]).'">';
	var_export($params[0]);
	echo '</span>';
}
