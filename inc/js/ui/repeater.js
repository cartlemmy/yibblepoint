sl.repeater = function(o) {
	var self = this;
	sl.initSlClass(this,"repeater");

	self.init = function() {
		self.el.slSpecial = self;
		
		self.template = [];
		for (var i = 0; i < self.el.childNodes.length; i++) {
			self.template.push(self.el.childNodes[i]);
		}
		sl.removeChildNodes(self.el);
	};
		
	self.refresh = function(row) {
		if (!self.el) return;
		
		function ref(row) {
			var item = self.items[row];
			if (item) {
				for (var j = 0; j < item.elements.length; j++) {
					var el = item.elements[j];
					if ((row & 1) == 1) {
						el.className = el.origClassName ? el.origClassName+" odd" : "odd";
					} else {
						el.className = el.origClassName ? el.origClassName : "";
					}
				}
			}
		}
		
		if (row === undefined) {
			for (var i = 0; i < self.items.length; i++) {
				ref(i);
			}
		} else {
			ref(row);
		}
	};
	
	self.appendMultiple = function(items) {
		if (items instanceof Array) {
			for (var i = 0; i < items.length; i++) {
				self.append(items[i],i,1);
			}
		} else {
			for (var i in items) {
				self.append(items[i],i,1);
			}
		}
		self.refresh();
	};
	
	self.rowClick = function(e) {
		var element;
		if (!(e.target.getAttribute && (element = e.target.getAttribute("data-repeater-id")))) {
			element = "row";
		}
		
		sl.cancelBubble(e);
		
		var	target = e.target;
		while (!target.slItem) {
			if (!target.parentNode) return;
			target = target.parentNode;
		}
		
		self.dispatchEvent("click-"+element,{"target":e.target,"item":target.slItem,"n":target.slItem.slN});
	};
	
	self.append = function(item,key,noRefresh) {
		if (typeof(item) != "object" || item === null) return;
		item._KEY = key ? key : "n"+self.n;
		item.elements = [];
		
		for (var i = 0; i < self.template.length; i++) {
			var el = self.template[i].cloneNode(true);
			
			if (el.className) el.origClassName = el.className;
			
			el.slItem = item;
			sl.addEventListener(el,"click",self.rowClick,false);
			
			self.parseItem(el,item);
			self.el.appendChild(el);
			
			item.elements.push(el);
			item.slN = self.n;
			
			self.view.initContentForElement(el,self,{"n":self.n});
		}
		
		self.n++;
		self.items.push(item);
		self.curRow++;
		
		if (!noRefresh) self.refresh(self.curRow - 1);
		return item._KEY;
	};
	
	self.remove = function(key) {
		var i = self.getItemIndexFromKey(key);
		if (i == -1) return;
		var item = self.items[i];
		
		if (!item) return;
		
		for (var j = 0; j < item.elements.length; j++) {
			item.elements[j].parentNode.removeChild(item.elements[j]);
		}
		
		self.items.splice(i,1);
		
		self.dispatchEvent("remove",item);
		self.refresh();
	};
	
	self.removeAll = function() {
		while (self.items.length) {
			self.remove(self.items[0]._KEY);
		}		
	};	
	
	self.getItemIndexFromKey = function(key) {
		if (typeof(key) == "number") return key;
		for (var i = 0; i < self.items.length; i++) {
			if (self.items[i]._KEY == key) return i;
		}
		return -1;
	};
	
	self.getItemFromKey = function(key) {
		var i;
		if ((i = self.getItemIndexFromKey(key)) == -1) return null;
		return self.items[i];
	};
	
	self.parseItem = function(el,item) {
		var c = sl.getChildNodes(el);
		for (var i = 0; i < c.length; i++) {
			self.updateElement(c[i],item);
		}
	};
	
	self.updateElement = function(el,item) {
		if (el.nodeName == "BUTTON") sl.addEventListener(el,"click",self.rowClick,false);
		
		if (el.slSpecial && el.slSpecial.setValue) {
			if (item[el.slSpecial.n] !== undefined) el.slSpecial.setValue(item[el.slSpecial.n]);
		}
			
		if (el.getAttribute) {
			if (valueType = el.getAttribute("data-value-type")) {
				var attr = el.attributes;
				var o = {"type":valueType};
				for (var j = 0; j < attr.length; j++) {
					var n = attr.item(j).nodeName;
					if (n.substr(0,11) == "data-value-") {
						o[n.substr(11)] = el.getAttribute(n);
					}
				}
				o.el = el;
				//console.log(o);
				el.slValue = new sl.value(o);

			}					
			
			var id;
			if (id = el.getAttribute("data-repeater-id")) {
				var drs = el.getAttribute("data-repeater-set");
				
				if (el.slValue) {
					el.slValue.setValue(item[id]);
				} else {
					if (item[id] !== undefined) {
						switch (drs ? drs : el.nodeName) {
							case "background-image":
								el.style.backgroundImage = "url('"+item[id]+"')"
								break;
								
							case "IMG":
								el.src = item[id];
								break;
								
							default:
								el.innerHTML = item[id];
								break;
						}
					}
				}
			}
		}
	};
	
	self.element = function(key,id) {
		var i = self.getItemIndexFromKey(key);

		if (i == -1) return;
		var item = self.items[i], el;
		
		for (var j = 0; j < item.elements.length; j++) {
			var c = sl.getChildNodes(item.elements[j]);
			for (var k = 0; k < c.length; k++) {
				if (c[k].getAttribute && c[k].getAttribute("data-repeater-id") == id) return c[k];
			}
		}
		
		return null;		
	};
		
	self.update = function(key,data) {
		var i = self.getItemIndexFromKey(key);

		if (i == -1) return;
		var item = self.items[i];
		
		
		for (var j = 0; j < item.elements.length; j++) {
			var c = sl.getChildNodes(item.elements[j]);
			for (var k = 0; k < c.length; k++) {
				self.updateElement(c[k],data);
			}
		}
	};
	
	self.destruct  = function() {
		self.removeAll();
	};
	
	self.setValues({
		"curRow":0,
		"items":[],
		"n":0
	});
	
	if (o) self.setValues(o);
	
	self.init();
}

