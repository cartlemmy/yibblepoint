sl.sizeableLayout = function(o) {
	var self = this;
	var listeners = [];
	sl.initSlClass(this,"sizeable-layout");

	self.init = function() {
		if (self.isInit) return;
		
		if (!self.contEl) self.contEl = self.view.elInner;
		
		if (!self.el) self.el = sl.dg("",self.contEl,"div");
		
		self.el.style.position = "relative";
		self.el.slSpecial = self;
		self.el.setAttribute("data-slid",self.id);
		
		var children = [];
		if (!self.size) {
			self.size = 0;
			while (self.el.childNodes.length) {
				var node = self.el.childNodes[0];
				if (node.nodeType == 1) {
					self.size ++;
					children.push(node.parentNode.removeChild(node));
				} else node.parentNode.removeChild(node);
			}
		}
				
		if (self.view && self.view.app && self.view.app.args[1] != "NEW") {
			self.view.app.request("varGet",["sizeable-layout",self.getId()],function(r){
				if (r !== null) self.setAreaSizes(r);
			});
		}
		
		var startSize = 1 / self.size;
				
		self.padding = 0;
		self.resizerSize = 0;
		
		for (var i = 0; i < self.size; i++) {
			if (i > 0) {
				var resizer = sl.dg("",self.el,"div",{"className":"resizer-"+self.orientation});
				resizer.addEventListener("mousedown",function(e){self.mouse("mousedown",e);});
				
				self.resizers.push(resizer);
				var s = sl.getTotalElementSize(resizer);
				self.resizerSize = self.orientation == "vertical" ? s.width : s.height;
				self.padding += self.resizerSize;
			}
			self.add({"size":startSize});
		}
		
		self.isInit = true;
		self.resize();
		
		listeners.push([self.view,self.view.addEventListener("resize",self.resize)]);
		listeners.push([self.view.core,self.view.core.addEventListener("mousemove",self.mouse)]);
		listeners.push([self.view.core,self.view.core.addEventListener("mouseup",self.mouse)]);
		
		for (var i = 0; i < children.length; i++) {
			for (var j = 0; j < children[i].childNodes.length; j++) {
				switch (children[i].childNodes[j].nodeType) {
					case 1:
					 self.area[i].el.appendChild(children[i].childNodes[j]);
					 break;
				}
			}
		}
	};
	
	self.mouse = function(t,e) {
		var i = self.resizers.indexOf(e.target);
		//console.log(i);
		
		sl.mouseCoords(e);
		
		switch (t) {
			case "mousedown":
				self.currentSizer = i;
				sl.preventDefault(e);
				sl.cancelBubble(e);
				self.sizing = true;
				self.startMouse = self.orientation == "vertical" ? e.clientX : e.clientY;
				self.startPos = self.getSizerPos(self.currentSizer);
				break;
			
			case "mouseup":
				if (self.sizing) {
					self.sizing = false;
					self.view.app.request("varSet",["sizeable-layout",self.getId(),self.getAreaSizes()],function(r){});
				}
				break;
			
			case "mousemove":
				if (self.sizing) {
					sl.preventDefault(e);
					sl.cancelBubble(e);
					self.setSizerPos(self.currentSizer, self.startPos + (self.orientation == "vertical" ? e.clientX : e.clientY) - self.startMouse);
				}
				break;
		}
	};
	
	self.getSizerPos = function(i) {
		var pos = 0;
		for (var j = 0; j <= i; j++) {
			pos += self.area[j].size;
		}
		return pos * self.fullSize;
	};
	
	self.setSizerPos = function(i,v) {
		var move = (v - self.getSizerPos(i)) / self.fullSize;
		
		if (self.area[i].size + move < 0) move = -self.area[i].size;
		if (self.area[i+1].size - move < 0) move = self.area[i+1].size;
		
		self.area[i].size += move;
		self.area[i+1].size -= move;
		
		//correct rounding errors:
		var tot = 0;
		for (var j = 0; j < self.area.length; j++) {
			tot += self.area[j].size;
		}
		self.area[i].size += 1-tot;
		self.resize();
	};
	
	self.setAreaSizes = function(r) {
		for (var i = 0; i < r.length; i++) {
			self.area[i].size = r[i];
		}
		self.resize();
	};
	
	self.getAreaSizes = function() {
		var rv = [];
		for (var i = 0; i < self.area.length; i++) {
			rv.push(self.area[i].size);
		}
		return rv;
	}
	
	self.getId = function() {
		var node = self.el, id;
		if (self.id) {
			id = self.id;
		} else {
			while (node.getAttribute && !(id = node.getAttribute("data-slid"))) {
				node = node.parentNode;
			}
		}
		return self.view.app.args.join("-").safeName()+"-"+(id?"-"+id:"main");
	};
	
	self.add = function(o) {
		o.el = sl.dg("",self.el,"div",{"style":{"position":"absolute","overflow":"auto"}});
		self.area.push(o);
	};

	self.resize = function() {
		if (sl.isHidden(self.el)) return;
		var err = 0, pos = 0;
		function roundSize(v) {
			var rn = Math.round(v + err);
			err += rn - v;
			return rn;
		};
		
		self.el.style.width = self.el.style.height = "0px";
		
		var pad = sl.getTotalElementSize(self.contEl,true);

		self.width = self.contEl.offsetWidth - pad.width;
		self.height = self.contEl.offsetHeight - pad.height;	
		
		self.el.style.width = self.width+"px";
		self.el.style.height = self.height+"px";
				
		if (self.orientation == "vertical") {
			self.fullSize = self.width - self.padding;
		} else {
			self.fullSize = self.height - self.padding;
		}
		
		for (var i = 0; i < self.size; i++) {
			if (self.orientation == "vertical") {
				var s = roundSize(self.area[i].size * self.fullSize);
				if (i > 0) {
					self.resizers[i - 1].style.left = pos+"px";
					self.resizers[i - 1].style.height = self.height+"px";
					pos += self.resizerSize;
				}
				
				self.area[i].el.style.top = "0px";
				self.area[i].el.style.left = pos+"px";
				pos += s;
				self.area[i].el.style.width = s+"px";
				self.area[i].el.style.height = self.height+"px";
			}
		}
		self.dispatchEvent("resize");
	};
	
	self.destruct  = function() {
		self.el.parentNode.removeChild(self.el);
		var l;
		while (l = listeners.pop()) {
			l[0].removeEventListener(l[1]);
		}
	};

	self.setValues({
		"area":[],
		"resizers":[],
		"sizing":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
