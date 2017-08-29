sl.selectionBox = function(o) {
	var self = this;
	var listeners = [];
	var draggers = [
		{"d":[1,0,-1,0],"horizontal":1,"style":{"cursor":"w-resize","left":"0px","top":"0px","bottom":"0px"}},
		{"d":[0,0,1,0],"horizontal":1,"style":{"cursor":"e-resize","right":"0px","top":"0px","bottom":"0px"}},
		{"d":[0,1,0,-1],"vertical":1,"style":{"cursor":"n-resize","top":"0px","left":"0px","right":"0px"}},
		{"d":[0,0,0,1],"vertical":1,"style":{"cursor":"s-resize","bottom":"0px","left":"0px","right":"0px"}},
		{"d":[1,1,-1,-1],"style":{"cursor":"nw-resize","left":"0px","top":"0px"}},
		{"d":[0,1,1,-1],"style":{"cursor":"ne-resize","right":"0px","top":"0px"}},
		{"d":[1,0,-1,1],"style":{"cursor":"sw-resize","left":"0px","bottom":"0px"}},
		{"d":[0,0,1,1],"style":{"cursor":"se-resize","right":"0px","bottom":"0px"}}
	];
	
	sl.initSlClass(this,"selection-box");

	self.init = function() {
		self.contWidth = self.contEl.offsetWidth;
		self.contHeight = self.contEl.offsetHeight;
		self.el = sl.dg("",self.contEl,"div",{"style":{"cursor":"move","border":"1px dashed #999","position":"absolute","top":"0px","backgroundColor":"rgba(255,255,0,0.2)"}});
		self.el.addEventListener("mousedown",function(e){self.mouse("mousedown",e,{"d":[1,1,0,0]});});
		
		if (!self.scrollEl) self.scrollEl = self.contEl;
		
		if (!self.wasSet) {
			self.snap.x = self.x = self.scrollEl.scrollLeft;
			self.snap.y = self.y = self.scrollEl.scrollTop;
		}
		
		if (self.parent) {
			if (!self.parent.boxes) {
				self.parent.boxes = [];
			}
			self.parent.boxes.push(self);
		}
		
		for (var i = 0; i < draggers.length; i++) {
			if (!draggers[i].horizontal) draggers[i].style.height = "10px";
			if (!draggers[i].vertical) draggers[i].style.width = "10px";
			
			draggers[i].style.position = "absolute";
			draggers[i].style.border = "1px solid #FFF";
			draggers[i].el = sl.dg("",self.el,"div",{"style":draggers[i].style});
			(function (i) {
				draggers[i].el.addEventListener("mousedown",function(e){self.mouse("mousedown",e,draggers[i]);});
			})(i);
		}
		
		listeners.push([self.view.core,self.view.core.addEventListener("mousemove",self.mouse)]);
		listeners.push([self.view.core,self.view.core.addEventListener("mouseup",self.mouse)]);
		self.refresh();
		self.isInit = true;
		self.setSelected(true);
	};
	
	self.mouse = function(t,e,dragger) {
		if (!dragger) dragger = self.currentDragger;
		sl.mouseCoords(e);
		switch (t) {
			case "mousedown":
				self.currentDragger = dragger;
				sl.preventDefault(e);
				sl.cancelBubble(e);
				self.dragging = true;
				self.startMouse = {"x":e.clientX,"y":e.clientY};
				self.setSelected(true);

				self.snap.x = self.x;
				self.snap.y = self.y;
				self.snap.width = self.width;
				self.snap.height= self.height;
				break;
			
			case "mouseup":
				if (self.dragging) {
					self.dragging = false;
									
					self.x = self.snap.x;
					self.y = self.snap.y;
					self.width = self.snap.width;
					self.height= self.snap.height;
					
					self.parent.changed(self);
					self.refresh();
				}
				break;
			
			case "mousemove":
				if (self.dragging) {
					sl.preventDefault(e);
					sl.cancelBubble(e);
					
					self.x += (e.clientX - self.startMouse.x) * dragger.d[0];
					self.y += (e.clientY - self.startMouse.y) * dragger.d[1];
					self.width += (e.clientX - self.startMouse.x) * dragger.d[2];
					self.height += (e.clientY - self.startMouse.y) * dragger.d[3];
					
					//SNAP:
					var guides = [[0,self.contWidth],[0,self.contHeight]];
					for (var i = 0; i < self.parent.boxes.length; i++) {
						if (self.parent.boxes[i] != self) {
							var b = self.parent.boxes[i];
							if (guides[0].indexOf(b.x) == -1) guides[0].push(b.x);
							if (guides[0].indexOf(b.x+b.width) == -1) guides[0].push(b.x+b.width);
							if (guides[1].indexOf(b.y) == -1) guides[1].push(b.y);
							if (guides[1].indexOf(b.y+b.height) == -1) guides[1].push(b.y+b.height);
						}
					}
					
					self.snap.x = self.x;
					self.snap.y = self.y;
					self.snap.width = self.width;
					self.snap.height = self.height;					
				
					for (var i = 0; i < guides[0].length; i++) {
						if (Math.abs(self.x - guides[0][i]) < 5) self.snap.x = guides[0][i];
						if (Math.abs(self.y - guides[1][i]) < 5) self.snap.y = guides[1][i];
						if (Math.abs((self.x + self.width) - guides[0][i]) < 5) self.snap.width = guides[0][i] - self.x;
						if (Math.abs((self.y + self.height) - guides[1][i]) < 5) self.snap.height = guides[1][i] - self.y;
					}
					
					self.snap.x = Math.max(0,self.snap.x);
					self.snap.y = Math.max(0,self.snap.y);
					self.snap.width = Math.min(self.contWidth-self.snap.x,self.snap.width);
					self.snap.height = Math.min(self.contHeight-self.snap.y,self.snap.height);
					
					self.refresh();
					
					self.startMouse.x = e.clientX;
					self.startMouse.y = e.clientY;
				}
				break;
		}
	};
	
	self.setSelected = function(yes) {
		self.selected = yes;
		
		if (!self.isInit) return;
		
		for (var i = 0; i < draggers.length; i++) {
			draggers[i].el.style.display = yes ? "block" : "none";
		}
		
		if (yes && self.parent && self.parent.boxes) {
			self.parent.selected(self);
			for (var i = 0; i < self.parent.boxes.length; i++) {
				if (self.parent.boxes[i] != self) self.parent.boxes[i].setSelected(false);
			}
		}
	};
	
	self.refresh = function() {
		self.el.style.left = self.snap.x+"px";
		self.el.style.top = self.snap.y+"px";
		self.el.style.width = (self.snap.width-2)+"px";
		self.el.style.height = (self.snap.height-2)+"px";
	};

	self.setX = function(x) {
		self.wasSet = true;
		self.x = self.snap.x = x;
	};
	
	self.setY = function(y) {
		self.wasSet = true;
		self.y = self.snap.y = y;
	};
	
	self.setWidth = function(width) {
		self.wasSet = true;
		self.width = self.snap.width = width;
	};
	
	self.setHeight = function(height) {
		self.wasSet = true;
		self.height = self.snap.height = height;
	};
	
	self.destruct = function() {
		var l;
		while (l = listeners.pop()) {
			l[0].removeEventListener(l[1]);
		}
		self.el.parentNode.removeChild(self.el);
	};

	self.setValues({
		"snap":{
			"x":0,
			"y":0,
			"width":48,
			"height":48
		},
		"x":0,
		"y":0,
		"width":48,
		"height":48,
		"wasSet":false,
		"parent":null,
		"currentDragger":null,
		"dragging":false,
		"selected":false,
		"isInit":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
