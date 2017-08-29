sl.fieldDef.iframe = {
	"init":function() {	
		var self = this;

		self.el = sl.dg("",self.contEl,"input",{"type":"text","style":{"width":"200px"}});
		self.but = sl.dg("",self.contEl,"input",{"type":"button","value":"SELECT...","style":{"width":"84px"}});
		
		self.but.addEventListener("click",function(){
			var w = window.open(self.src+"?f=1&v="+encodeURIComponent(self.getValue()));
			w.addEventListener("load",function(){
				w._ypField = self;
			});
		})
		return false;
	},
	"setValue":function(value) {
		if (value && this.el) {
			console.log(value);
		}				
		return true;
	}
};
