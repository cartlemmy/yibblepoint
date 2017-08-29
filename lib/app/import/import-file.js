sl.require("js/core/mime.js",function(){
	
	self.setTitle("en-us|Choose Import File");
	self.setContentFromHTMLFile();

	var allowedTypes = ["text/csv"];
	self.view.element("importFile").addEventListener("change",function(e){
		if (e.target.files[0]) {
			app.importFile = e.target.files[0];
			
			if (allowedTypes.indexOf(app.importFile.type) != -1) {
				app.navigate("import-setup");
			} else {
				self.view.elementMessage("importFile",sl.format("en-us|This type of file (%%) is not importable, please choose another file.",sl.mimeName(app.importFile.type)),10);
			}
		}
	});

	app.view.maximize();
});
