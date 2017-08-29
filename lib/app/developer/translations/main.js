self.createView({"contentPadding":"8px","options":[]});

self.view.setContentFromHTMLFile();


var scroller = self.view.element("translations").slSpecial;
var total = 0;

self.request("cnt",[],function(cnt){
	total = cnt;
	scroller.setItemCount(cnt);
});

scroller.requestItem = function(itemIndex) {
	var scrollerItem = this;
	scrollerItem.loadingMessage(["app","status","text"]);
	self.request("item",[itemIndex],function(res){

		scrollerItem.element("app").innerHTML = res.app;
		scrollerItem.element("lang").innerHTML = res.lang;
		scrollerItem.element("translated").innerHTML = res.translated;
		
		scrollerItem.setAsLoaded();
	});
};

self.view.center();
