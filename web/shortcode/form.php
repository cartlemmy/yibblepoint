<?php
//params: name, redirect, recipients

$GLOBALS["slFormData"]["name"] = $params[0];
$GLOBALS["slFormData"]["redirect"] = $params[1];
if (isset($params[2])) $GLOBALS["slFormData"]["recipients"] = $params[2];

?>
<form role="form" method="post" action="<?=WWW_RELATIVE_BASE;?>form/" target="form-submit">
