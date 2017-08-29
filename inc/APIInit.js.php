sl.loadListeners = [];
sl.addLoadListener = function(f) {
	sl.loadListeners.push(f);
};

sl.init = function() {		
	if (sl.core) {
		var slc = new sl.core({"noInterface":true,"mainEl":document.body});
		
		slc.init();
		
		slc.net.send("login-status",{},{},function(response){
			sl.config.loggedIn = response.loggedIn;
			sl.config.user = response.user;
			sl.config.name = response.name;
		});
		
		<?php if (isset($this->scriptName)) { ?>
			slc.open(<?=json_encode(array_shift(explode(".",$this->scriptName)));?>);
		<?php } ?>
		
		if (sl.webView) {
			var view = new sl.webView({"el":document.body});
		
			sl.initContentForElement(document.body,view,{},view,view);
			
			window.slView = view;
		}
	}
	if (sl.onload) sl.onload();
	while (f = sl.loadListeners.shift()) {
	f.call(sl);
	}
};
