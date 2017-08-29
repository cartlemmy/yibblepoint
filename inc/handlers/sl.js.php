<?php

require_once(SL_INCLUDE_PATH."/class.slScript.php");

$sls = new slScript("sl.js");

$sls->start();

require(SL_INCLUDE_PATH."/slGlobal.js");

$sls->stop();

$includes = array(
	"core/sl.js","core/initSlClass.js","core/general.js","core/string.js",
	"core/serializer.js","core/value.js","core/date.js",
	"core/bitArray.js","core/base64.js","core/bigArray.js",
	"core/stacktrace.js",
		
	"security/sha1.js",
	
	"app/app.js","app/appModule.js",
	
	"net/net.js","net/cookies.js","net/browser.js",
	
	"ui/view.js","ui/messageBox.js","ui/appBarItem.js","ui/html2image.js",
	"ui/efx.js","ui/tabbed.js","ui/repeater.js","ui/scroller.js",
	"ui/loadingOverlay.js","ui/fieldValidator.js","ui/menu.js",
	"ui/field.js","ui/notification.js","ui/suggestions.js","ui/icon.js",
	"ui/iconMenu.js","ui/fieldPrompt.js","ui/viewOverlay.js",
	"ui/toolbar.js","ui/sizeableLayout.js","ui/heirarchicalView.js",
	
	"media/sound.js"
);

foreach ($includes as $include) {
	$sls->parse(SL_INCLUDE_PATH."/js/".$include);
}

$sls->start();

?>

sl.init = function() {
	var slc = new sl.core();
	
	slc.init();

	slc.net.send("login-status",{},{},function(response){

		sl.config.loggedIn = response.loggedIn;
		sl.config.user = response.user;
		sl.config.name = response.name;
		sl.config.credits = response.credits;
		
		<?php if (is_file(SL_LIB_PATH."/start.js")) readfile(SL_LIB_PATH."/start.js"); ?>
		
		if (sl.config.setupMode) {
			slc.open("initial-setup");
		} else if (sl.config.loggedIn) {
			slc.initInterface();
		} else {
			slc.net.forcePollFrequency(3);
			slc.open("login");
		}
		
		slc.open("dispatcher");
		
		if (sl.config.package.runOnStart) {
			var ref;
			while (ref = sl.config.package.runOnStart.pop()) {
				slc.open(ref);
			}
		}
		
		if (window.openAtStart) {
			slc.open(window.openAtStart);
		}
		
		//slc.open("our-office/task");
		//slc.open("site-management/editor");
		//slc.open("view/?db/contacts");
		//slc.open("game/test");
		//slc.open("edit/?db/user&cartlemmy");
		//slc.open("edit/?db/contacts&Bob@HamsterRepublic.com");
		//slc.open("import/?db/contacts");
		//slc.open("marketing/campaign/?db/campaigns&testing");
		//slc.open("marketing/campaignComponent/?db/campaignComponents&15");
	});
};
	
<?php

require(SL_INCLUDE_PATH."/slLoader.js");

$sls->stop();
$sls->out();
