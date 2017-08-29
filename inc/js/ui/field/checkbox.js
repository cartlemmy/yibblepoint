sl.fieldDef.checkbox = {
	"init":function() {
		var self = this;
		
	
		if (self.label) {
			self.labelEl = sl.dg("",self.contEl,"label");
			self.el = sl.dg("",self.labelEl,"input",{"type":"checkbox"});
			sl.dg("",self.labelEl,"text"," "+self.label);
		} else {
			self.el = sl.dg("",self.contEl,"input",{"type":"checkbox"});
		}
		
		self.el.checked = !!this.value.value;

		var i = 0;	
		self.el.addEventListener("change",function(){
			self.changed(self.el.checked?1:0,true);
		},false);
		return false;
	},
	"setValue":function(value) {

		this.el.checked = !!value;
		return true;
	},
	"getValue":function() {
		return this.el.checked;
	}
};
