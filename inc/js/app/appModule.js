sl.appModule = function(o) {
	var self = this;
	sl.initSlClass(this,"app-module");

	self.init = function() {
		if (!self.el) return;
		var el = self.el;
		while (el.parentNode) {
			if (el.slTab) {
				el.slTab.addEventListener("appeared",function(t,o){
					self.dispatchEvent("appeared-tab",o);
				});
				if (el.slTab.selected) self.dispatchEvent("appeared-tab");
			}
			el = el.parentNode;
		}
	};
	
	self.setTitle = function(title) {
		if (title) {
			self.parentView.updateNavTitle(self.el,title);
		}
		self.title = title;
	};
	
	self.setEl = function(el) {
		self.el = el;
	};
	
	// Content
	self.appendElement = function(id,t,a,pre) {
		return sl.dg(id,self.el,t,a,pre);
	};
	
	self.setContentAsHTML = function(html) {
		self.view = new sl.appModuleView({
			"module":self
		});
		self.el.innerHTML = html;
		self.parentView.initContentForElement(self.el,null,null,self.view,self.parentView);
	};
	
	self.setContentFromHTMLFile = function() {
		self.setContentAsHTML(self.html?self.html:"en-us|HTML file not found.");
	};
	
	self.formatContent = function() {
		var args = [], i;
		for (i = 0; i < arguments.length; i++) {
			args.push(arguments[i]);
		}
		args.unshift(null);
		
		var c = sl.getChildNodes(self.el);
		var r = ["href","title","alt"];
		for (i = 0; i < c.length; i++) {
			if (c[i].nodeType == 3 && c[i].textContent.indexOf("%") != -1) {
				args[0] = c[i].textContent;
				c[i].textContent = sl.format.apply(self,args);
			} else if (c[i].nodeType == 1) {
				for (var j = 0; j < r.length; j++) {
					if (c[i][r[j]] && c[i][r[j]].indexOf("%") != -1) {
						args[0] = c[i][r[j]];
						c[i][r[j]] = sl.format.apply(self,args);
						if (r[j] == "href") self.parentView.initContentForElement(c[i]);
					}
				}
			}
		}
	};
	
	self.destruct = function(cb) {
		sl.removeChildNodes(self.el);
		if (cb) cb();
	};
	
	self.setValues({
		
	});
	
	if (o) self.setValues(o);
	
	self.init();
};


sl.appModuleView = function(o) {
	var self = this;
	sl.initSlClass(this,"app-module-view");
	
	
	self.field = function(id) {
		var el;
		if (
			(el = self.element(id)) &&
			el.slSpecial) return el.slSpecial;
		return null;
	};
	
	self.getFieldValues = function(el) {
		if (!el) el = self.module.el;
		var rv = {}, field, c = sl.getChildNodes(el);
		for (var i = 0; i < c.length; i++) {
			if (c[i].getAttribute && (field = c[i].slSpecial)) rv[field.n] = field.getValue();
		}
		return rv;
	};
	
	self.element = function(id) {
		return self.module.parentView.element(id,self.module.el);
	};
	
	self.elementMessage = function(element,message,timer) {
		return self.module.parentView.elementMessage(element,message,timer);
	}
	
	self.setValues({
		
	});
	
	if (o) self.setValues(o);
	
	//self.init();
};
