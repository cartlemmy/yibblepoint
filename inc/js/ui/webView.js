sl.webView = function(o) {
	var self = this;
	sl.initSlClass(this,"web-view");

	var mouseInterface = [
		["move","","",1,1,0,0],
		["left","w-resize","width",1,0,-1,0],
		["right","e-resize","width",0,0,1,0],
		["bottom","s-resize","height",0,0,0,1],
		["corner","","",0,0,1,1]
	];
	var buttonNames = ["close", "size", "minimize"];

	self.init = function() {
		self.core = sl.coreOb;
	};
		
	self.setTitle = function(title) {
		if (title !== undefined) self.title = title;
		self.extraTitle = (self.navElements.length > 0 ? sl.config.sep+self.navElements[self.navNum].title : "");
		var title = (self.saveState == "unsaved" ? "* " : "") + self.title + self.extraTitle;
		if (self.elTitle) self.elTitle.innerHTML = title;
		if (self.app && self.app.title) self.app.title = title;
		if (self.appBarItem && self.appBarItem.textEl) self.appBarItem.textEl.innerHTML = title;
		sl.coreOb.updateTitle();
	};
		
	self.elementMessage = function(element,message,timer) {
		if (element = self.element(element)) {
			for (var i = 0; i < self.messageBox.length; i++) {
				if (self.messageBox[i].element == element) {
					self.messageBox[i].setMessage(message);
					return self.messageBox[i];
				}
			} 
			var o = new sl.messageBox({"element":element,"message":message,"core":self.core,"view":self,"timer":timer});
			self.messageBox.push(o);
			return o;
		}
	};
	
	self.floatingElement = function(element,timer) {
		if (element = self.element(element)) {
			for (var i = 0; i < self.messageBox.length; i++) {
				if (self.messageBox[i].element == element) {
					self.messageBox[i].setMessage(message);
					return self.messageBox[i];
				}
			} 
			var o = new sl.messageBox({"element":element,"core":self.core,"view":self,"timer":timer,"noPadding":true});
			self.messageBox.push(o);
			return o;
		}
	};
		
	self.elementPrompt = function(element,message,choices,cb) {
		if (element = self.element(element)) {
			for (var i = 0; i < self.messageBox.length; i++) {
				if (self.messageBox[i].element == element) {
					self.messageBox[i].setMessage(message);
					return;
				}
			}
			self.messageBox.push(new sl.messageBox({"element":element,"message":message,"core":self.core,"view":self,"choices":choices,"callback":cb}));
		}
	};
		
	self.element = function(id,parent) {
		if (typeof(id) != "string") return id;
		
		if (!parent) {
			var q = id.split(/(=| contains )/);
			if (q.length != 1) return self.getElement(q,self.el);
		}
		
		return document.getElementById(id);
	};
	
	self.getElement = function(q,parent) {
		var c = parent.childNodes;
		for (var i = 0; i < c.length; i++) {
			if (c[i].nodeType == 1) {
				var c1 = self.getElementAttribute(c[i],q[0]);
				switch (q[1]) {
					case "=": if (c1 == q[2]) return c[i] ? c[i] : c[i];
						break;
					
					case " contains ":
						if (c1 && c1.indexOf(q[2]) != -1) return c[i] ? c[i] : c[i];
						break;
				}
			}
			var el;
			if (el = self.getElement(q,c[i])) return el;
		}
		return null;
	};
	
	self.getElementAttribute = function(el,attr) {
		switch (attr) {
			case "validate":
				return el.slValidator ? el.slValidator.rules : false;
			
			default:
				if (el.getAttribute) {
					if (el.getAttribute("data-"+attr)) return el.getAttribute("data-"+attr);
					if (el.getAttribute(attr)) return el.getAttribute(attr);
				}
		}
		return false;
	};
	
	// Mouse
	self.mouse = function(e) {
		sl.mouseCoords(e);
		
		if (e.type == "mousedown" && self.state != "maximized") {
			self.core.setActiveView(self);
			self.mPos.target = e.target;
			self.mPos.down = true;
			self.mPos.mouseX = e.clientX;
			self.mPos.mouseY = e.clientY;
			self.mPos.x = self.x;
			self.mPos.y = self.y;
			self.mPos.width = self.width;
			self.mPos.height = self.height;
		}
		
		if (e.type == "mouseup") {
			self.mPos.down = false;	
		} else if (self.mPos.target && self.mPos.target.mi != undefined && self.mPos.down) {
			var mi = mouseInterface[self.mPos.target.mi];
			self.setPosition(self.mPos.x + (e.clientX - self.mPos.mouseX) * mi[3],self.mPos.y + (e.clientY - self.mPos.mouseY) * mi[4]);
			self.setSize(self.mPos.width + (e.clientX - self.mPos.mouseX) * mi[5],self.mPos.height + (e.clientY - self.mPos.mouseY) * mi[6],1);
		}
		
		sl.preventDefault(e);
		sl.cancelBubble(e);
	};
	
	self.cancelMouse = function() {
		self.mPos.down = false;
	};
	
	self.destruct = function(fromCloseButton,force) {
		
	};
	
	self.removeMessageBox = function(mb) {
		self.messageBox.splice(self.messageBox.indexOf(mb),1);
	};
	
	self.isHolding = function() {
		return self.holdCnt > 0;
	};
	
	self.hold = function() {
		if (!self.isHolding()) self.dispatchEvent("held");
		self.holdCnt++;
	};
	
	self.release = function() {
		self.holdCnt--;
		if (!self.isHolding()) self.dispatchEvent("released");
	};
			
	self.setValues({
		"x":-1,
		"y":-1,
		"holdCnt":0,
		"navElements":[],
		"active":null,
		"activeNum":-1,
		"app":null,
		"isMainView":false,
		"width":0,
		"height":0,
		"options":[],
		"userState":null,
		"optimalState":null,
		"saveState":"",
		"closeAfterSave":false,
		"spaceInner":{},
		"spaceOuter":{},
		"mPos":{},
		"core":null,
		"el":null,
		"elInner":null,
		"elTitle":null,
		"focusField":null,
		"title":"",
		"extraTitle":"",
		"buttons":{},
		"state":"",
		"previousState":"",
		"messageBox":[],
		"destructing":false,
		"snapShot":null,
		"startHidden":false,
		"showing":false,
		"noScroll":false,
		"specialElements":[],
		"tools":[],
		"toolEl":{},
		"connectedApps":[]
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
