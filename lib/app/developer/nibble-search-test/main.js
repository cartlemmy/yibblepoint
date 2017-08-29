self.createView({"contentPadding":"8px","options":[]});

self.view.setContentFromHTMLFile();

self.view.center();

var ni;
	
sl.require(["js/core/nibbleIndexer.js"],function(){
	self.requestBinary("search",[],function(res) {
		ni = new sl.nibbleIndexer({"data":res});
	});

	self.view.element("searchButton").addEventListener("click",function(){
		console.log(ni.search(self.view.element("search").value));
		console.log("TOOK "+Math.round(1000*ni.lastQueryDuration)+"MS");
	});
});


