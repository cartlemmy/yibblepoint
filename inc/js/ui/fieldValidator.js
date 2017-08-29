sl.fieldValidator = function(o) {
	var self = this;
	sl.initSlClass(self,"field-validator");
	
	self.update = function(e,now) {
		if (self.submitter) return;
		if (self.messageEl) self.messageEl.innerHTML = "";
		self.lastEvent = e;
		if (e.type == "change" && !now) now = true;
		if (self.timer) clearTimeout(self.timer);
		if (now) {
			if (e.type == "change" || self.problems.length || self.immediateCheck) {
				var v = self.getValue();
				if (v != self.lastCheckValue || e.type == "change" || now == 2) {
					self.lastCheckValue = v;
					self.fromSubmit = now == 2;
					self.validate(self.getValue(), e.type == "change");
				}
			}
		} else {
			self.timer = setTimeout(function(){self.update(self.lastEvent,true);},self.updateWait);
		}
	};
	
	self.resetAll = function() {
		var el = sl.getChildNodes(self.form);
		for (var i = 0; i < el.length; i++) {
			if (el[i].slValidator && !el[i].slValidator.submitter) {
				el[i].slValidator.validIcon.className = "";
				if (el[i].slValidator.popUp) el[i].slValidator.popUp.hide();
			}
		}
	};
	
	this.allFieldsValid = function() {
		var el = sl.getChildNodes(self.form);

		var pass = true;
		for (var i = 0; i < el.length; i++) {
			var field = el[i];
			if (el[i].slValidator && !el[i].slValidator.submitter) {
				var validator = el[i].slValidator;
				validator.update({"type":"change"},2);
				if (!validator.isValid()) pass = false;
			}
		}
		return pass;
	}
	
	this.validate = function(v,change) {
		self.delayedCheckQueue = [];
		self.problems = [];
		self.checked = 0;
		self.problemSeverity = 0;

		for (var i = 0; i < self.rules.length; i++) {
			var rule = self.rules[i];
			if (self.validators[rule]) {
				self.checked ++;
				var func = typeof(self.validators[rule]) == "function" ? self.validators[rule] : self.validators[rule].check;
				if (change || self.validators[rule].immediate) {
					if (func.call(self,v)) self.pass();
				} else self.pass();
			} else self.log('No such rule: '+rule);
		}
	};
	
	this.isValid = function() {
		return self.problemSeverity <= 1;
	};
	
	this.allChecked = function() {
		if (self.checked > 0) return;

		self.setIcon(self.problemSeverity);
		
		var l = Math.min(2,self.problemSeverity);
		var lc = ["field-success","field-warning","field-error"];
		
		if (self.field.className.indexOf("form-control") != -1) {
			
			var bsm = ["has-success","has-warning","has-error"],
				bsmi = ["ok","warning","remove"];
			
			//Bootstrap
			var el = self.field;
			while (el = el.parentNode) {
				if (el.className.indexOf("form-group") != -1) {
					
					if (!self.feedbackEl) self.feedbackEl = sl.dg("",el,"span");
					
					self.feedbackEl.className = "glyphicon glyphicon-"+bsmi[l]+" form-control-feedback";
					
					el.className = "form-group " + bsm[l] + " has-feedback";
					break;
				}
			}
		} else {
			self.field.className = self.problemSeverity >= 2 ? 
				(function(){var c = ["field-issue"];if (self.className) {c.push(self.className);} return c.join(" ")})() : 
				self.className;
		}
			
		if (self.problems.length) {
			var html = [];
			for (var i = 0; i < self.problems.length; i++) {
				var d = self.problems[i].description;
				if (html.indexOf(d) == -1) html.push(d);
			}
			if (self.messageEl) {
				self.messageEl.className = lc[l];
				self.messageEl.style.display = "";
				self.messageEl.innerHTML = html.join("<br />");
			} else {
				if (!self.fromSubmit || self.problemSeverity) {
					if (self.popUp && !self.popUp.destructing) {
						self.popUp.setMessage(html.join("<br />"));
						self.popUp.show();
					} else {
						self.popUp = self.view.elementMessage(self.validIcon,html.join("<br />"));
					}
				}
			}
		} else {
			if (self.messageEl) {
				self.messageEl.style.display = "none";
				self.messageEl.innerHTML = "";
			} else {
				self.field.className = self.className;
				if (self.popUp) self.popUp.hide();
			}
		}
		self.dispatchEvent("checked",self.problems);
	};
	
	this.setIcon = function(severity) {
		if (!self.validIcon) {
			self.validIcon = sl.dg("",null,"div",{},{"after":self.field});
		}
		var a = ["valid","warn","error"];
		self.validIcon.className = "field-"+a[severity];
	};
	
	this.pass = function() {
		self.checked --;
		self.allChecked();
		return true;
	};
	
	this.warn = function(description,fix,suggestion) {
		self.message(description,fix,suggestion,1);
	};
	
	this.fail = function(description,fix,suggestion) {
		self.message(description,fix,suggestion,2);
		return false;
	};
	
	this.message = function(description,fix,suggestion,severity) {
		if (!severity) severity = 0;
		self.problemSeverity = Math.max(severity,self.problemSeverity);
		var rv = {"description":description,"severity":severity};
		if (fix) rv.fix = fix;
		if (suggestion) rv.suggestion = suggestion;
		self.problems.push(rv);
		self.checked --;
		self.allChecked();
		return false;
	};
	
	this.getValue = function() {
		return self.field.slSpecial ? self.field.slSpecial.getValue() : self.field.value;
	};
	
	this.setRules = function(rules) {
		if (rules) {
			self.immediateCheck = false;
			if (typeof(rules) == "string") rules = rules.split(",");
			for (var i = 0; i < rules.length; i++) {
				var rule = rules[i];
				if (self.validators[rule] && typeof(self.validators[rule]) != "function" && self.validators[rule].immediate) {
					self.immediateCheck = true;
				}
			}
		}
		self.rules = rules;
	};
	
	self.formMessage = function(message) {
		if (!self.messageEl) return;
		self.messageEl.innerHTML = message;
	};
	
	self.setField = function(field) {
		if (field) {
			
			if (field.type == "button" || field.type == "submit") {
				self.submitter = true;
				
				sl.addEventListener(field,"click",function(e) {
					if (self.disabled) return;
					if (!(this.slValid = self.allFieldsValid())) {
						sl.cancelBubble(e);
						sl.preventDefault(e);
						e.stopImmediatePropagation();
						self.formMessage("en-us|Please fix the highlighted problems first.");
						return false;
					}
				},true);
			} else {
				self.className = field.className;
				sl.addEventListener(field,"change",self.update,false);
				sl.addEventListener(field,"keyup",self.update,false);
				sl.addEventListener(field,"blur",function(){
					if (self.popUp && self.problemSeverity == 0) self.popUp.hide();
				},false);
			}
			
			var node = field;
			while (node = node.parentNode) {
				if (node.className == "sl-view" || node.nodeName == "FORM" || node.nodeName == "SECTION") {
					self.form = node;
					
					var el = sl.getChildNodes(self.form);
					for (var i = 0; i < el.length; i++) {
						if (el[i].className == "form-message") {
							self.messageEl = el[i];
						}
					}
					break;
				}
			}
		}
		
		self.field = field;
	};
	
	self.delayedCheck = function() {
		var dcn = self.delayedCheckCnt++;
		self.delayedCheckQueue.push(dcn);
		return dcn;
	};
	
	self.delayedCheckCancelled = function(dcn) {
		return self.delayedCheckQueue.indexOf(dcn) == -1;
	};
	
	self.destruct = function() {
		if (self.popUp) self.popUp.destruct();
	};
	
	//Set defaults:
	this.setValues({
		"form":null,
		"messageEl":null,
		"field":null,
		"timer":null,
		"lastEvent":null,
		"rules":null,
		"submitter":false,
		"oldOnClick":null,
		"problems":[],
		"delayedCheckQueue":[],
		"delayedCheckCnt":0,
		"updateWait":250,
		"lastCheckValue":"",
		"immediateCheck":false,
		"popUp":null,
		"validIcon":null,
		"validators":!inline("validators.js")
	});
		
	if (o) {
		this.setValues(o);	
	}
}
