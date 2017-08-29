self.createView({"contentPadding":"8px","options":[]});

self.view.setContentFromHTMLFile();

self.request("getAll",[],function(info){
	var isOptionChild = false;
	if (info.data || self.args[1] == "NEW") {
		console.log(info);

		self.view.element("export").addEventListener("click",function(){
			self.request("export",[],function(res){
				console.log(res);
				self.core.action.apply(self.core,res.action);
			});
		});
	}
});
		
self.view.maximize();

