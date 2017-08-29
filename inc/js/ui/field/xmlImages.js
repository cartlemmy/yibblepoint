sl.fieldDef.xmlImages = {
	"init":function() {	
		var self = this;
		var lo;

		self.el = sl.dg("",self.contEl,"button",{});
		self.el.innerHTML = "IMAGES...";
		self.el.addEventListener('click',function(){
			if (self.view.app.args[1] === "NEW") {
				alert("Please save this "+self.view.app.info.setup.singleName+" first.");
				return;
			} 
			
			self.core.open("xmlImages/?"+self.view.app.args[0]+"&"+self.view.app.args[1]);
		});
		return false;
	},
	"setValue":function(value) {	
		return true;
	}
};
