<?php

class nibbleTestApp extends slAppClass {		
	function search($v) {
		readfile($GLOBALS["slSession"]->userFilePath("nibble-indexer-test","application/binary"));
		exit();
	}
}
