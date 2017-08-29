
function parseMenu(menu) {
	for (var i = 0; i < menu.length; i++) {
		if (menu[i].icon) {
			menu[i].icon += "/icon-24.png";
		} else if (menu[i].ref) {
			menu[i].icon = "app/"+menu[i].ref+"/icon-24.png";
		}
		if (menu[i].children) parseMenu(menu[i].children);
	}
}

self.request("getMenu",[],function(response){
	if (response.menu) {
		parseMenu(response.menu);
		
		self.menu = new sl.menu({"core":self.core,"buttonEl":self.core.appBarMenuEl,"contents":response.menu,"align":"horizontal","offY":-9});
		
		self.menu.addEventListener("click",function(type,o) {
			if (o.item.ref) {
				self.core.open({"ref":o.item.ref,"args":o.item.args});
			}
		});
	}
});
