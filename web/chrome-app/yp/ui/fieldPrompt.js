sl.fieldPrompt = function(o) {

	var self = this;
	sl.initSlClass(this,"field-prompt");

	self.init = function() {
		self.overlay = new sl.viewOverlay({"view":self.view,"noCloseButton":true});
		
		if (self.message) {
			self.titleEl = sl.dg("",self.overlay.elContent,"h3",{"innerHTML":self.message});
		}
		
		self.fieldOb = [];

		for (var i = 0; i < self.fields.length; i++) {
			var field = self.fields[i];
			var cont = sl.dg("",self.overlay.elContent,"fieldset",{"className":"vertical"});
			sl.dg("",cont,"label",{"innerHTML":field.label});
			field.contEl = cont;
			field.core = self.view.core;
			field.view = self.view;
			self.fieldOb.push(new sl.field(field));
		}
		
		self.goEl = sl.dg("",self.overlay.elContent,"button",{"innerHTML":self.goName,"style":{"margin-right":"10px"}});
		self.goEl.addEventListener("click",self.sendValues);
		
		self.cancelEl = sl.dg("",self.overlay.elContent,"button",{"innerHTML":"en-us|Cancel"});
		self.cancelEl.addEventListener("click",function(){
			self.respond(false);
		});
		
		self.overlay.updateContentSize();
	};
	
	self.sendValues = function() {
		if (self.wait) return;
	
		var vals = {};
		for (var i = 0; i < self.fields.length; i++) {
			if (self.fieldOb[i].wait) {
				self.fieldOb[i].addEventListener("wait-end",function(){
					self.wait--;
					if (self.wait == 0) self.sendValues();
				});
				self.wait ++;
			}
			vals[self.fields[i].n] = self.fieldOb[i].value.value;
		}
		if (self.wait) return;
		self.respond(vals);
	};
	
	self.respond = function(v) {
		if (v === false) {
			self.dispatchEvent("cancel");
		} else {
			self.dispatchEvent("go",v);
		}
		self.destruct();
		if (self.cb) self.cb(v);
	};	
	
	self.setValues({
		"wait":0
	});
	
	self.destruct = function() {
		self.overlay.destruct();
	};
	
	if (o) self.setValues(o);
	
	self.init();
};
