self.request("setup",[],function(setup){
	self.setup = setup;
	self.createView({
		"title":"en-us|Import "+setup.name,
		"icon":setup.icon?setup.icon:null,
		"tools":["navigation"],
		"contentPadding":"8px"
	});

	self.navigate("import-file");
	self.view.center();

	self.addEventListener("destruct",function() {
		self.removeServerListener(self.refreshListener);
	});
});
