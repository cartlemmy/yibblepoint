self.setContentFromHTMLFile();

self.addEventListener("appeared",function(){
	self.formatContent({
		"ref":app.args[0],
		"name":app.setup.name,
		"inserted":app.inserted.count,
		"updated":app.updated.count
	});
});
	

