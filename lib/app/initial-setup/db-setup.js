self.setTitle("en-us|DB Setup");
self.setContentFromHTMLFile();

self.addEventListener("appeared",function(){
	app.request("getConfig",["db"],function(data){
		for (var n in data) {
			if (self.view.field(n)) self.view.field(n).setValue(data[n]);
		}
	});
});

self.view.element("continue").addEventListener("click",function(){
	app.request("setConfig",["db",self.view.getFieldValues()],function(data){
		if (data) {
			if (data.success) {
				app.navigate("smtp-setup");
			} else {
				console.log(data);
				self.view.elementMessage(self.view.element("continue"), data.error, 4);
			}
		}
	});
},false);
