<?php

$this->setCaching(false);

require_once(SL_INCLUDE_PATH."/class.slContact.php");
require_once(SL_WEB_PATH."/inc/class.formSubmission.php");

$form = new formSubmission("Kinetic Signup");

$form->addRecipient("itsupport@palimountain.com","Josh");
$form->addRecipient("rob@kineticteambuilding.com","Rob");

$submittedData = array();

$_POST['name'] = $_POST['fname'].' '.$_POST['lname'];

$possibleFields = array(
	"name"=>array("label"=>"Name"),
	"email"=>array("label"=>"E-mail")
);

$fields = array();

foreach ($possibleFields as $n=>$o) {
	if (isset($o["value"])) {
		$fields[$n] = array("label"=>$o["label"]);
		$submittedData[$n] = $o["value"];
	} elseif (isset($_POST[$n])) {
		$fields[$n] = array("label"=>$o["label"]);
		$submittedData[$n] = $_POST[$n];
	}
}
$submittedData["groups"] = "+Kinetic";

$form->setFields($fields);

$form->submit($submittedData);

?><script>
window.parent.$('.inner-container .inner').addClass('subscribed');
</script>
<?php exit();
