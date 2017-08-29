sl.fieldDef = {};

/*!include("field/*")*/

sl.field = function(o) {
	var self = this;
	sl.initSlClass(this,"field");
	
	self.value = new sl.value(o);
	
	self.setCleaners = function(cleaners) {
		self.cleaners = typeof(cleaners) == "string" ? cleaners.split(",") : cleaners;
	};
	
	self.applyValue = function(value) {
		self.setValue(value);
		if (self.listener) {
			self.listener.dispatchEvent("blur",{"field":self.n,"value":self.value.value,"ob":self,"changed":true});
			self.listener.dispatchEvent("change",{"field":self.n,"value":self.value.value,"ob":self,"changed":true});
		}
	};
	
	self.setValue = function(value) {
		self.value.setValue(value);
		
		if (!self.isInit) return;
		
		if (!self.type) self.setType("text");
		
		if (self.def && self.def.setValue) {
			if (self.def.setValue.call(self,value)) return;
		}		
		if (self.el && !self.readOnly && !self.readOnlyField && !self.noValueInit) {
			self.el.value = self.value.toString(function(v){
				self.el.value = v;
				if (self.validator) self.validator.update(false,true);
			});
			if (self.validator) self.validator.update(false,true);
		}
		self.noValueInit = false;
	};

	self.setType = function(type) {
		self.value.setType(type);
		if (sl.fieldDef[type]) self.def = sl.fieldDef[type];
		self.fieldType = self.value.def && self.value.def.fieldType ? self.value.def.fieldType : "text";
		self.type = type;
	};
	
	self.setOptions = function(options) {
		self.value.options = options;
		self.options = options;
	};
	
	self.setFormat = function(format) {
		self.value.format = self.format = format;
	};
	
	self.setValidate = function(rules) {
		if (self.el && rules && !self.multi) {
			if (!self.validator) {
				self.validator = new sl.fieldValidator({"field":self.el,"view":self.view,"core":self.view.core,"rules":rules});
				if (self.userID) self.validator.userID = self.userID;
			} else {
				self.validator.setRules(rules);
			}
		} else {
			self.validate = rules;
		}
	};
	
	self.setUserID = function(uid) {
		self.userID = uid;
		if (self.validator && uid) self.validator.userID = uid;
	};
	
	self.getValue = function() {
		return self.value.value;
	};
	
	self.setDefinition = function(def) {
		def = def.split("/");
		if (def.length == 3) self.n = def.pop();
		self.definition = def.join("/");
	};
		
	self.changed = function(text,updateValue) {
		var value = self.value.fromString(text);

		if (self.updateJS) {
			var fields = self.fields;
			eval(self.updateJS);
		}
		
		if (self.directChange) {
			if (self.cleaners) {
				for (var i = 0; i < self.cleaners.length; i++) {
					switch (self.cleaners[i]) {
						case "trim":
							value = value.trim();
							break;
					}
				}
			}
					
			var o = {"field":self.n,"text":text,"value":value,"ob":self};
			
			if (updateValue) {			
				self.setValue(value);
			}	

			if (self.lastValue == value) return;
			self.lastValue = value;
			
			if (self.def && self.def.change) self.def.change.call(self,value);
			
			self.dispatchEvent("change",o);
			if (self.listener) self.listener.dispatchEvent("change",o);
		} else {
			if (self.lastValue == value) return;
			self.lastValue = value;
			if (self.def && self.def.change) self.def.change.call(self,value);
		}
	};
	
	self.getElAttributes = function(def) {
		var elAttributes = self.elAttributes ? self.elAttributes : {};
		sl.recursiveSet(elAttributes,def);
		return elAttributes;
	};
	
	self.init = function(o) {
		if (self.definition && !self.definitionInit) {
			self.definitionInit = true;
			self.view.hold();
			sl.net.send("item-info",{"ref":self.definition},{},function(res){
				self.view.release();
				var field;
				if (res.info && res.info.fields && (field = res.info.fields[self.n])) {
					self.set(field);
					self.init();
				}
			});
			return;
		}
		var changeTimer = null, defaultInit = true;
		
		if (self.readOnly || self.readOnlyField) {
			self.el = sl.dg("",self.contEl,"pre",self.getElAttributes({"style":{"whiteSpace":"pre-wrap","wordWrap":"break-word"}}));
			self.value.el = self.el;
			
			self.el.innerHTML = self.value.toString(function(v){
				self.el.innerHTML = v;
			});
			return;
		}
		
		if (self.multi) {
			self.multiType = self.type;
			self.setType("multi");
		}
		
		if (self.def && self.def.init) {
			defaultInit = self.def.init.call(self);
		}
		
		if (defaultInit) {
			self.fieldType = self.type && ["password","color","email","month","number","range","tel","time","url","week"].indexOf(self.type) != -1 ? self.type : "text";

			if (!self.el) self.el = sl.dg("",self.contEl,"input",self.getElAttributes({"type":self.fieldType}));
					
			sl.addEventListener(self.el,"keyup",function(){
				if (changeTimer) clearTimeout(changeTimer);
				changeTimer = setTimeout(function(){self.changed(self.el.value)},100);
			},false);
			
			sl.addEventListener(self.el,"keydown",function(e){
				if ((e.keyCode == 8 || e.keyCode == 46) && self.el.value == "") { self.lastValue = "."; self.changed("",true); }
			},false);
			
			sl.addEventListener(self.el,"change",function(){
				if (changeTimer) clearTimeout(changeTimer);
				self.changed(self.el.value,true);
			},false);
			
			sl.addEventListener(self.el,"paste",function(){
				if (changeTimer) clearTimeout(changeTimer);
				self.changed(self.el.value,true);
			},false);
			
			
			if (!self.customBlur) {
				sl.addEventListener(self.el,"focus",function(){
					self.focusValue = self.value.value;
					self.focus = true;
				},false);
				
				sl.addEventListener(self.el,"blur",function(){
					self.focus = false;
					if (self.listener) self.listener.dispatchEvent("blur",{"field":self.n,"value":self.value.value,"changed":self.focusValue != self.value.value,"ob":self});
					if (self.focusValue != self.value.value && self.def && self.def.blur) self.def.blur.call(self,self.value.value);
				},false);
			}
		}
				
		if (self.style) {
			var style = self.style.multiSplit(";",":");
			for (var i in style) {
				self.el.style[i] = style[i];
			}
		}
		
		if (self.def && self.def.postInit) {
			self.def.postInit.call(self);
		}
		
		if (self.validate) self.setValidate(self.validate);
		
		self.lastValue = self.value.value;
		
		self.isInit = true;
		
		if (self.el && self.el.value) {
			self.changed(self.el.value,true);
			if (self.validator) self.validator.update({"type":"change"});
		}
		
		if (self.el) self.el.slSpecial = self;
		self.setValue(self.value.value);
	};
	
	//Suggestions
	self.clearSuggestions = function() {
		if (self.suggestions) self.suggestions.clear();
	};
	
	self.hideSuggestions = function() {
		if (self.suggestions) self.suggestions.hide();
	};
	
	self.suggestionClick = function() {
		return self.suggestions && (self.suggestions.overSuggestion || self.suggestions.suggestionClicked);
	};
	
	self.addSuggestion = function(sug) {
		if (!self.suggestions) {
			self.suggestions = new sl.suggestions({
				"core":self.core,
				"fieldEl":self.el
			});
			self.suggestions.pipeEvent("select",self);
		}
		self.suggestions.add(sug);
	};

	self.startWait = function() {
		self.wait = 1;
		if (self.parent) self.parent.startWait();
	};
	
	self.endWait = function() {
		self.wait--;
		if (self.wait <= 0) {
			self.wait = 0;
			self.dispatchEvent("wait-end",self);
		}
		if (self.parent) self.parent.endWait();
	};
	
	self.destruct = function() {
		self.el.parentNode.removeChild(self.el);
	};

	self.setValues({
		"listener":null,
		"focus":false,
		"wait":0,
		"suggestions":null,
		"def":null,
		"directChange":true,
		"customBlur":false,
		"lastValue":"",
		"validate":false,
		"isInit":false
	});
	
	if (o) {
		self.origValues = {};
		for (var i in o) {
			self.origValues[i] = o[i];
		}
		self.setValues(o);
	}

	self.init(o);
};

