self.createView({"contentPadding":"8px"});

var fieldTest = new sl.field({
	"type":"dateRange",
	"core":self.core,
	"view":self.view,
	"contEl":self.view.elInner,
	"n":"range-test",
	"value":"0-1401745727",
	"listener":self
});

self.addEventListener("change",function(t,o){
	console.log(t,o);
});

self.view.initContent();
self.view.center();
