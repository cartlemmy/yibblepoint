<?php

echo "<pre>";

//$fs->resetAll();

$continue = false;
if ($cnt = $fs->fingerprintPhotos(100)) {
	echo "Fingerprinting photos...\n";
	$continue = true;
} else if ($cnt = $fs->similarityScan(100)) {
	echo "Scanning for similar/duplicate photos...\n";
	$continue = true;
} else if ($cnt = $fs->removeDuplicates(100)) {
	echo "Removing duplicate photos...\n";
	$continue = true;
}

//$fs->removeDuplicates(1000,true);
//$fs->fixIncorrectExtensionCase(true);

echo "</pre>";

if ($continue) {
	?><script type="text/javascript">
		window.location.reload();
	</script><?php
} else {
	echo "<b>Scan Complete</b>";
}
