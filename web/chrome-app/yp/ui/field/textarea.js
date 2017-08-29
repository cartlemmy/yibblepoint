sl.fieldDef.textarea = {
	"init":function() {
		var self = this;
		self.el = sl.dg("",self.contEl,"textarea",{"style":{
			"height":"150px"
		}});

		self.el.addEventListener("change",function(){
			if (self.changeTimer) clearTimeout(self.changeTimer);	
			self.changeTimer = setTimeout(function(){
				self.changed(self.el.value);
			},250);
		},false);
		
		self.el.addEventListener("keyup",function(){
			self.changed(self.el.value);
		},false);
		
		return false;
	},
	"setValue":function(value) {
		if (this.el) this.el.value = value;
		return true;
	}
};
