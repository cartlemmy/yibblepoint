<?php

$this->setCaching(false);

if (setAndTrue($_POST,"submit")) {
	require_once(SL_INCLUDE_PATH."/class.slSupport.php");
	
	$errors = array();
	
	if (!setAndTrue($_POST,"issue")) $errors[] = "Please enter your issue";
	if (!(isset($hwUser) && setAndTrue($hwUser,"name")) && !setAndTrue($_POST,"name"))  $errors[] = "Please provide your name";
	if (!(isset($hwUser) && setAndTrue($hwUser,"email")) && !setAndTrue($_POST,"email"))  $errors[] = "Please provide your email address";
	
	if (!$errors) {
		$fromEmail = isset($hwUser) && setAndTrue($hwUser,"email") ? $hwUser["email"] : $_POST["email"];
				
		$ticket = slSupport::add(array(
			"name"=>strTruncate(array_shift(explode("\n",$_POST["issue"])),50),
			"from"=>array(
				"name"=>isset($hwUser) && setAndTrue($hwUser,"name") ? $hwUser["name"] : $_POST["name"],
				"email"=>$fromEmail,
			)
		));
		
		$ticket->update(array(
			"status"=>"open",
			"from"=>$fromEmail,
			"text"=>$_POST["issue"]
		));

		$ticket->commit();
		
		echo "Your issue has been submitted. Your ticket ID is: ".$ticket->get("id");
		return;
	}
} 

echo '<form action="?submit=1" method="post">';
echo '<table class="table table-striped">';
echo '<tbody>';

if (isset($hwUser) && $hwUser["_KEY"]) {
	echo '<tr><td>Name</td><td>'.$hwUser["name"].'</td></tr>';
	echo '<tr><td>E-mail</td><td>'.$hwUser["email"].'</td></tr>';
} else {
	echo '<tr><td>Name</td><td><input name="name" type="text" class="form-control" value=""></td></tr>';
	echo '<tr><td>E-mail</td><td><input name="email" type="email" class="form-control" value=""></td></tr>';
}

echo '<tr><td>What is your issue?</td><td><textarea name="issue" class="form-control" rows="20"></textarea></td></tr>';

echo '<tr><td colspan="2"><button class="btn btn-default" name="submit" type="submit" value="1">Submit Issue</button></td></tr>';
echo '<tbody></tbody>';
echo '</form>';


