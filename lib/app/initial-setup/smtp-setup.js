self.setTitle("en-us|SMTP Setup");
self.setContentFromHTMLFile();

self.addEventListener("appeared",function(){
	app.request("getConfig",["communication.smtp"],function(data){
		//console.log(data);
		if (data.Username == false) data.Username = "";
		if (data.Password == false) data.Password = "";
		
		for (var n in data) {
			if (self.view.field(n)) self.view.field(n).setValue(data[n]);
		}
	});
});

self.view.element("continue").addEventListener("click",function(){
	var data = self.view.getFieldValues();
	
	if (data.Username.trim() == "") data.Username = false;
	if (data.Password.trim() == "") data.Password = false;

	app.request("setConfig",["communication.smtp",data],function(res){
		if (res) {
			app.navigate("license-check");
		}
	});
},false);
