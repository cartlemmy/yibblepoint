sl.fieldDef.select = {
	"init":function() {
		var self = this;
		
		if (typeof(self.options) == "string") {
			self.value.options = self.options = self.indexIsValue ? self.options.split(",") : self.options.multiSplit(true,",","=");
		}
		
		self.el = sl.dg("",self.contEl,"select",{});
		if (sl.typeOf(self.options) == "array") {
			for (var i = 0; i < self.options.length; i++) {
				sl.dg("",self.el,"option",{
					"value":self.indexIsValue?i:self.options[i],
					"innerHTML":self.options[i]
				});
			}
		} else {
			for (var n in self.options) {
				sl.dg("",self.el,"option",{
					"value":n,
					"innerHTML":self.options[n]
				});
			}
		}
		for (var i = 0; i < self.el.options.length; i++) {
			if (self.value.value == self.el.options[i].value) {
				self.el.selectedIndex = i;
				break;
			}
		}	
			
		self.el.addEventListener("change",function(){
			self.changed(self.el.options[self.el.selectedIndex].value,true);
		},false);
		return false;
	},
	"setValue":function(value) {
		for (var i = 0; i < this.el.options.length; i++) {
			if (value == this.el.options[i].value) {
				this.el.selectedIndex = i;
				return true;
			}
		}
		return true;
	}
};
