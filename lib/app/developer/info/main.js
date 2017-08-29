self.createView({"contentPadding":"8px","options":[]});

self.view.setContentFromHTMLFile();

self.view.center();

self.request("getInfo",[],function(res) {
	self.view.element("info").innerHTML = res;
});

