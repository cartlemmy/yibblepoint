self.setTitle("en-us|License Check");
self.setContentFromHTMLFile();

self.addEventListener("appeared",checkLicense);
self.view.element("retry").addEventListener("click",checkLicense);

function checkLicense() {
	self.view.element("showInvalid").style.display = "none";
	app.request("checkLicense",[],function(data){
		
		self.view.element("showInvalid").style.display = data.check.licenseValid ? "none" : "";
		self.view.element("showValid").style.display = data.check.licenseValid ? "" : "none";
		
		var desc = [
			"en-us|Licensed To: "+data.license.licensedTo,
			"en-us|Licensed Key: "+data.license.key,
			"",
			data.license.description
		];
		
		self.view.element("description").innerHTML = desc.join("\n");		
	});
};

self.view.element("continue").addEventListener("click",function(){
	app.navigate("complete");
},false);
