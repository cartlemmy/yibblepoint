self.createView({"contentPadding":"8px","options":[]});

self.view.setContentFromHTMLFile();

self.view.center();

self.view.element("encButton").addEventListener("click",function(){
	self.request("encrypt",[self.view.element("plain").value],function(res) {
		self.view.element("enc").value = res;
	});
});
