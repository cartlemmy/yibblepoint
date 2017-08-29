self.setTitle("en-us|Super User Info");
self.setContentFromHTMLFile();

self.addEventListener("appeared",function(){
	app.request("getConfig",["superUser"],function(data){
		for (var n in data) {
			self.view.field(n).setValue(data[n]);
		}
	});
	
	app.request("getConfig",["communication.defaultFrom"],function(data){
		for (var n in data) {
			self.view.field("from."+n).setValue(data[n]);
		}
	});
});

self.view.element("continue").addEventListener("click",function(){
	var data = self.view.getFieldValues();
	var defaultFrom = {};
	for (var n in data) {
		if (n.split(".").shift() == "from") {
			defaultFrom[n.split(".").pop()] = data[n];
			delete data[n];
		}
	}

	sl.chainer(
		[app,app.request,"setConfig",["superUser",data]],
		[app,app.request,"setConfig",["communication.defaultFrom",defaultFrom]],
		[app,app.navigate,"db-setup"]
	);
		
	
	/*var chainer = new sl.chainer(function(){
		app.navigate("db-setup");
	});	
	
	app.request("setConfig",["superUser",data], chainer.add());
	app.request("setConfig",["communication.defaultFrom",defaultFrom], chainer.add());*/
	
},false);
