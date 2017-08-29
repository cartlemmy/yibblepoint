self.createView({"contentPadding":"8px","options":[]});

self.view.setContentFromHTMLFile();


var o = {
	"core":self.core,
	"view":self.view,
	"contEl":self.view.element('test'),
	"n":"test-date",
	"cleaners":[],
	"type":"dateRange",
	"listener":self
};
		
var field = new sl.field(o);		
		
self.view.center();

self.addEventListener("*",function(t,o) {
	console.log(t,o);
});
