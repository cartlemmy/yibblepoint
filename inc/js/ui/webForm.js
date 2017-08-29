sl.webForm = function(o) {
	var self = this;
	sl.initSlClass(this,"web-form");

	self.init = function() {
		self.core = sl.coreOb;
		self.isInit = true;
		if (self.fields) self.setFields(self.fields);
	};
	
	self.setFields = function(fields) {
		self.fields = fields;
		if (!self.isInit) return;
		
		self.fields = {};
		var els = document.getElementsByTagName("*");
			
		for (var n in fields) {
			var field = fields[n];
			
			field.view = slView;
			
			for (var i = 0; i < els.length; i++) {
				if (els[i].getAttribute("data-slwfid") && els[i].getAttribute("data-slwfid") == n+"-"+self.uid) {
					field.el = els[i];
					break;
				}
			}
			
			self.fields[n] = new sl.field(field);
			field.el.slSpecial = self.fields[n];
			if (self.fields[n].validator) field.el.slValidator = self.fields[n].validator;
			
			for (var i = 0; i < els.length; i++) {
				if (els[i].getAttribute("data-slwfmid") && els[i].getAttribute("data-slwfmid") == n+"-"+self.uid && self.fields[n].validator) {
					self.fields[n].validator.messageEl = els[i];
					break;
				}
			}
		}
		
		for (var i = 0; i < els.length; i++) {
			if (els[i].getAttribute("data-slwf-sumbit") && !els[i].slValidator) {
				els[i].slValidator = new sl.fieldValidator({"field":els[i],"view":slView,"core":slView.core});
			}
		}
	};
			
	self.setValues({
		"isInit":false
	});
		
	if (o) self.setValues(o);
	
	self.init();
};
