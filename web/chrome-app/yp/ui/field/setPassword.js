sl.fieldDef.setPassword = {
	"init":function() {
		this.type = "password";
		return true;
	},
	"postInit":function() {
		var self = this;
		sl.cb(self.contEl);
		sl.dg("",self.contEl,"label",{"innerHTML":"en-us|Confirm Password","style":{"marginTop":"6px"}});
		self.confirmEl = sl.dg("",self.contEl,"input",{"type":"password"});
		
		var validator = new sl.fieldValidator({"field":self.confirmEl,"view":self.view,"core":self.view.core,"rules":"password-confirm"});
		return true;
	}
};
