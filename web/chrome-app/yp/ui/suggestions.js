sl.suggestions = function(o) {
	var self = this;
	sl.initSlClass(this,"suggestions");

	self.init = function() {
		self.el = sl.dg("",self.fieldEl.parentNode,"div",{"className":"sl-suggestions"},{"after":self.fieldEl});
		
		var pos = sl.getElementPosition(self.fieldEl,"left,bottom",self.el);
		
		self.el.style.left = (pos.x+20)+"px";
		self.el.style.top = pos.y+"px";
		
		self.fieldEl.addEventListener("focus",self.show);
		self.fieldEl.addEventListener("blur",function(){
			if (!self.overSuggestion) self.hide();
		},false);
		self.fieldEl.addEventListener("keydown",function(e){
			if (e.keyCode == 13) {
				if (self.shown) self.select(self.items[self.cursor]);
			} else if (e.keyCode == 38 || e.keyCode == 40) {
				self.cursor += e.keyCode == 38 ? -1 : 1;
				self.setCursor(self.cursor);
				e.preventDefault();
			}
		},true);
	};
	
	self.select = function(o) {
		self.suggestionClicked = true;
		if (o && o.name) self.dispatchEvent("select",o);
		self.fieldEl.focus();
		self.clear();
	};
	
	var outTimer = null;
	self.add = function(sug) {
		var o = {
			"field":self.fieldEl,
			"el":sl.dg("",self.el,"div",{"innerHTML":sug.formatted}),
			"value":sug.value,
			"name":sug.name
		};
		
		o.el.addEventListener("click",function(e){
			self.select(o);
		});
		
		o.el.addEventListener("mouseover",function(){
			if (outTimer) clearTimeout(outTimer);
			self.setCursor(self.items.indexOf(o));
			self.overSuggestion = o;
		});
		
		o.el.addEventListener("mouseout",function(){
			self.setCursor(null);
			if (outTimer) clearTimeout(outTimer);
			outTimer = setTimeout(function(){self.overSuggestion = null;},100);
		});
		
		self.items.push(o);
		self.show();
	};
	
	self.show = function() {
		if (self.blurTimer) clearTimeout(self.blurTimer);
		if (!self.items.length) return;
		self.el.style.display = "";
		self.shown = true;
	};
	
	self.hide = function() {
		self.el.style.display = "none";
		self.shown = false;
	};
	
	self.setCursor = function(cursor) {
		if (cursor === null) {
			self.cursor = -1;
		}
		self.cursor = (cursor + self.items.length) % self.items.length;
		for (var i = 0; i < self.items.length; i++) {
			self.items[i].el.className = i == self.cursor ? "selected" : "";
		}		
	};
	
	self.clear = function() {
		self.cursor = -1;
		while (item = self.items.pop()) {
			item.el.parentNode.removeChild(item.el);
		}
		self.hide();
	};
	
	self.setValues({
		"blurTimer":null,
		"shown":false,
		"items":[],
		"suggestionClicked":false,
		"overSuggestion":null,
		"cursor":null
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
