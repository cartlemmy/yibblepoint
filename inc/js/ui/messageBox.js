sl.messageBox = function(o) {
	var self = this;
	sl.initSlClass(this,"message-box");

	self.init = function() {
		self.el = sl.dg("",self.core.mainEl,"div",{
			"className":"sl-message-box",
			"style":{"opacity":1}
		});
		
		if (self.noPadding) self.el.style.padding = "0px";
		
		sl.addEventListener(self.el,"click",function(){
			self.dispatchEvent("click");
			if (self.clickToDismiss) self.dismissed("cancel");
		},false);
		
		self.contentEl = sl.dg("",self.el,"div",{
			"innerHTML":self.message ? self.message : ""
		});	
		
		if (self.choices) {
			for (var i in self.choices) {
				(function(name,label){
					var choice = sl.dg("",self.el,"a",{
						"href":"javascript:;",
						"innerHTML":label
					});
					sl.addEventListener(choice,"click",function(){
						if (self.clickToDismiss) self.dismissed(name);
					},false);
				})(i,self.choices[i]);
				
				self.el.appendChild(document.createTextNode(" \u00a0 "));
			}
		}
		
		self.elPointer = sl.dg("",self.el,"div",{});	
		
		self.elPointerInner = sl.dg("",self.elPointer,"div",{});	
		
		self.elementPosition();
	};
	
	self.dismissed = function(msg) {
		if (!self.destructing) {
			self.destruct();
			if (self.callback) self.callback(msg);
		}
	};
	
	self.setX = function(x) {
		self.x = x;
	};
	
	self.setY = function(y) {
		self.y = y;
	};
	
	self.setPosition = function(x,y) {
		self.x = x;
		self.y = y;
		self.reposition();
	};
	
	self.setDirection = function(dir) {
		self.direction = dir;
		self.reposition();
	};
	
	self.setTimer = function(timer) {
		if (!timer) return;
		setTimeout(function() {
			self.destruct();
		},timer * 1000);
	};
	
	self.reposition = function() {
		if (!self.elPointer || !self.core) return;
		self.elPointer.className = "sl-message-box-"+self.direction;
		
		var dm = self.directionMap[self.direction];
		if (dm) {
			self.xOff = self.elPointer.offsetLeft + (dm[0] * sl.config.theme["pointer-length"]);
			self.yOff = self.elPointer.offsetTop + (dm[1] * sl.config.theme["pointer-length"]);
		
			self.el.style.left = Math.min(self.core.width - sl.getTotalElementSize(self.el).width,Math.max(0,(self.x - self.xOff))) + "px";
			self.el.style.top = Math.min(self.core.height - sl.getTotalElementSize(self.el).height,Math.max(0,(self.y - self.yOff))) + "px";
		}
	};
	
	self.setMessage = function(message) {
		self.message = message;
		if (!self.el) return;
		self.el.style.opacity = 1;
		self.contentEl.innerHTML = message ? message : "";
	};
	
	self.setCore = function(core) {
		self.core = core;
	};
	
	var destructing = false;
	self.setElement = function(element) {
		
		self.element = element;
		if (element) {
			sl.addEventListener(element,"keypress",function(){
				if (!destructing) self.destruct();
			},false);
		}
		self.elementPosition();
	};
	
	self.elementPosition = function() {
		if (!self.element || !self.core) return;
		
		var pos = sl.getElementPosition(self.element,"center,center");		
		if (Math.abs(pos.x - self.core.width) < Math.abs(pos.y - self.core.height)) {
			self.setDirection(pos.x > self.core.width / 2 ? "right" : "left");
		} else {
			self.setDirection(pos.y > self.core.height / 2 ? "down" : "up");
		}
		
		var dm = self.directionMap[self.direction];
		if (dm) {
			self.set(sl.getElementPosition(self.element,dm[2]));
			self.reposition();
		}
	};
	
	self.show = function() {
		self.el.style.display = "";
		self.el.style.opacity = 1;
		self.destructing = false;
	};
	
	self.hide = function() {
		if (self.el.style.opacity < 1) return;
		sl.efx.fade(self.el,function(){
			self.el.style.opacity = 0;
			self.el.style.display = "none";
			if (self.destructing && self.el.parentNode) self.el.parentNode.removeChild(self.el); 
		});
	};
	
	self.destruct = function() {
		if (self.destructing) return;
		self.destructing = true;
						
		sl.efx.fade(self.el,function(){
			if (self.destructing) {
				if (self.view) self.view.removeMessageBox(self);
				if (self.el.parentNode) self.el.parentNode.removeChild(self.el);
			}
		});
	};
	
	self.setValues({
		"direction":"right",
		"directionMap":{
			"up":[0,-1,"center,bottom"],
			"right":[1,0,"left,center"],
			"down":[0,1,"center,top"],
			"left":[-1,0,"right,center"]
		},
		"noPadding":false,
		"xOff":0,
		"yOff":0,
		"core":null,
		"element":null,
		"el":null,
		"message":null,
		"destructing":false,
		"clickToDismiss":true
	});
	
	if (o) self.setValues(o);
	
	self.init();
	
	var dirs = ["up","right","down","left"], dirN = 0;
};
