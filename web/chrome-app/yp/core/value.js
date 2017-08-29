sl.valueDef = {};
	
/*!include("value/*")*/

sl.value = function(o) {
	var self = this;
	sl.initSlClass(this,"value");
	
	self.setType = function(type) {
		if (sl.valueDef[type]) {
			self.def = sl.valueDef[type];
			if (self.def.init) self.def.init.call(this);
		}
		self.type = type;
	};
	
	self.setValue = function(value) {
		if (value !== undefined) self.value = value;

		if (self.el && (self.el.nodeName == "SPAN" || self.el.nodeName == "DIV")) {
			var v = self.toString(function(v){
				self.el.style.display = self.label && v == "" ? "none" : "";
				self.el.innerHTML = self.getLabel() + (v ? v.diminishParentheses() : "");
			});
			if (v) self.el.innerHTML = self.getLabel() + v.diminishParentheses();
			self.el.style.display = self.label && v == "" ? "none" : "";
			if (!self.autoRefreshTimer && self.def && self.def.autoRefresh) self.autoRefreshTimer = setInterval(self.setValue,self.def.autoRefresh);
		}
	};
	
	self.setLabel = function(label) {
		self.label = label;
	};
	
	self.getLabel = function() {
		return self.label !== false && self.label !== undefined ? self.label+": " : "";
	};
	
	function toStringFinal(caller,cb) {
		if (self.def && self.def.toString) return self.def.toString.call(caller,cb);
		switch (typeof(caller.value)) {
			case "number": case "string":
				return String(caller.value);
			
			case "boolean":
				return caller.value ? "true" : "false";
		}
		return "";
	};
	
	self.toString = function(cb) {
		if (self.multi) {
			if (self.value === undefined) return "";
			var v = typeof(self.value) == "string" ? self.value.split("\n") : [];
			var mo = {
				"value":v.shift()
			};
			var f = toStringFinal(mo,cb);
			return typeof(f) == "string" ? f+(v.length?" (+"+v.length+")":"") : f;
		}
		return toStringFinal(self,cb);
	};
	
	self.destruct = function() {
		if (self.autoRefreshTimer) clearTimeout(self.autoRefreshTimer);
	};
	
	self.fromString = function(string) {
		return self.value = self.def && self.def.fromString ? self.def.fromString.call(self,string) : string;
	};
	
	self.setValues({
		"el":null,
		"def":null,
		"type":"null",
		"value":null,
		"autoRefreshTimer":null
	});
	
	if (o) self.setValues(o);
};
