<?php

$this->setCaching(false);

if ($GLOBALS["slSession"]->isLoggedIn()) $this->redirect(WWW_RELATIVE_BASE."home/");

if (isset($_POST["forgot"])) $this->redirect(WWW_RELATIVE_BASE."password-reset/");

$title = "PALI";
$this->setTitle($title);

?><h1><?=$title;?></h1><?php 

$showForm = true;
if (isset($_POST["user"])) {
	if ($GLOBALS["slSession"]->logIn($_POST)) {
		
		//$GLOBALS["slSession"]->setUserData('HTTP_USER_AGENT',$_SERVER["HTTP_USER_AGENT"]);
		
		$url = WWW_RELATIVE_BASE.'import/';	
			
		/*if (isset($_SESSION["LOGIN_RET"]) && $_SESSION["LOGIN_RET"]) {
			$url = $_SESSION["LOGIN_RET"];
			unset($_SESSION["LOGIN_RET"]);
		}*/
		
		$this->redirect($url);
	} else {
		$showForgot = true;
		?><p class="error">en-us|Invalid log in name or password.</p><?php
	}
}


if ($showForm) { ?>
<form action="<?=WWW_RELATIVE_BASE;?>login/" method="post">
	<?php if (setAndTrue($_POST,"from")) { ?><input type="hidden" name="from" value="<?=$_POST["from"];?>"><?php } ?>
	<div class="form-group">
		<label for="user">en-us|User</label>
		<input class="form-control" type="text" name="user"<?=isset($_GET["user"])?' value="'.$_GET["user"].'"':""?>>
	</div>

	<div class="form-group">
		<label for="user">en-us|Password</label>
		<input class="form-control" type="password" name="password">
	</div>

	<div class="form-group">
		<input type="submit" value="en-us|ENTER">
		<div style="float:right;text-align:right">
			<a href="<?=WWW_RELATIVE_BASE;?>password-reset/<?=isset($_GET["user"])?'?email='.$_GET["user"]:""?>" style="line-height: 20px;">en-us|Forgot your password?</a><br>
			<a href="<?=WWW_RELATIVE_BASE;?>new/" style="line-height: 20px;">en-us|New Parent?</a><br>
			<a href="<?=WWW_RELATIVE_BASE;?>help/" style="line-height: 20px;">en-us|Need Help?</a><br>
		</div>
	</div>
</form>
<?php } ?>
