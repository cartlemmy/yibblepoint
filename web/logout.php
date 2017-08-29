<?php

$this->setCaching(false);
$this->setSearchIndex(false);

?><h1>en-us|Log Out</h1><?php 

if (!isset($_GET["fromEditor"])) {
	$showForm = true;
	if ($GLOBALS["slSession"]->logOut()) {
		$this->redirect(isset($_POST["from"]) && $_POST["from"] ? $_POST["from"] : WWW_RELATIVE_BASE);
	}
}

