sl.menu = function(o) {

	var self = this;
	sl.initSlClass(this,"menu");

	self.init = function() {
		sl.addEventListener(self.buttonEl,"click",self.show,false);
		if (!self.parent) {
			self.core.addEventListener("click",function(t,e){
				if (!sl.isChildOf(e.target,self.buttonEl) && e.target != self.buttonEl) self.hide();
			},false);
		}
	};
	
	self.hideOtherChildren = function(showChild) {
		for (var i = 0; i < self.contents.length; i++) {
			var item = self.contents[i];
			if (item.subMenu && item.subMenu != showChild) item.subMenu.hide();
		}
	};
	
	self.hide = function() {
		if (!self.isShown) return;
		self.isShown = false;
		self.menuEl.style.display = "none";
	};
	
	self.getAnchorEl = function() {
		return self.anchorEl ? self.anchorEl : self.buttonEl;
	};
	
	self.show = function(noHide) {
		if (self.isShown) {
			if (noHide !== true) self.hide();
			return;
		}
		
		if (self.parent) self.parent.hideOtherChildren(self);
		
		if (!self.menuEl) {
			self.menuEl = sl.dg("",self.getAnchorEl(),"div",{
				"className":"sl-menu"
			});
			
			for (var i = 0; i < self.contents.length; i++) {
				var item = self.contents[i];
				
				if (typeof(item) == "string") {
					if (item == "") {
							sl.dg("",self.menuEl,"div",{
							"className":"sep"
						});
					} else {
						sl.dg("",self.menuEl,"div",{
							"className":"label",
							"innerHTML":item
						});
					}
				} else {
					(function(item) {
						var cont = sl.dg("",self.menuEl,"div",{
							
						});
						
						item.el = cont;
						item.el.slMenuButton = self.buttonEl;
						
						sl.addEventListener(cont,"mouseover",function(){
							cont.className = "over";
						},false);
						
						sl.addEventListener(cont,"mouseout",function(){
							cont.className = "";
						},false);
											
						sl.addEventListener(cont,"click",function(e){
							if (item.click) {
								item.click.call(self,e);
							} else if (item.children) {
								if (!item.subMenu) {
									item.subMenu = new sl.menu({"core":self.core,"buttonEl":cont,"contents":item.children,"parent":self,"align":"from-top-or-bottom","offX":-4,"offY":-7});
									item.subMenu.show(true);
								}
							} else {
								self.itemClick(item,e);
							}
							sl.cancelBubble(e);
						},false);
						
						if (item.icon) {
							sl.dg("",cont,"div",{
								"className":"sl-menu-icon",
								"style":{"backgroundImage":"url('"+item.icon+"')"}
							});
						}
						
					
						sl.dg("",cont,"div",{
							"innerHTML":item.label,
							"className":"label"
						});						
						
						if (item.children) {
							sl.dg("",cont,"div",{
								"className":"sl-sub-menu"
							});
						}			
						cont.style.cursor = "pointer";
					})(item);
				}
			}
		}

		self.itemClick = function(item,e) {
			self.dispatchEvent("click",{"item":item,"event":e});
			self.hide();
			if (self.parent) self.parent.itemClick(item,e);
		};
		
		self.isShown = true;
		self.menuEl.style.display = "";
		self.elementPosition();
	};
	
	self.setX = function(x) {
		self.x = x;
	};
	
	self.setY = function(y) {
		self.y = y;
	};
	
	self.setPosition = function(pos) {
		if (typeof(pos) == "string") pos = pos.split(",");
		self.position = pos;
	};
	
	self.reposition = function() {
		if (!self.menuEl) return;
		
		self.menuEl.style.left = (self.x + self.offX * (self.position[0] == "from-right" || self.position[0] == "left" ? -1 : 1)) + "px";
		self.menuEl.style.top = (self.y + self.offY * (self.position[1] == "from-bottom" || self.position[1] == "top" ? -1 : 1)) + "px";

	};
	
	self.elementPosition = function() {
		var pos = sl.getElementPosition(self.getAnchorEl(),"center,center");		
		
		var posX = "", posY = "";
		
		if (self.align == "horizontal") {
			posX = "same";
		} else if (self.align == "from-left") {
			posX = "from-eft";
		} else if (self.align == "from-right") {
			posX = "from-right";
		} else {
			posX = pos.x > self.core.width / 2 ? "left" : "right";
			if (self.align == "from-left-or-right") {
				posX = "from-"+(posX == "left" ? "right" : "left");
			}
		} 
		
		if (self.align == "vertical") {
			posY = "same";
		} else if (self.align == "from-top") {
			posY = "from-top";
		} else if (self.align == "from-bottom") {
			posY = "from-bottom";
		} else {
			posY = pos.y > self.core.height / 2 ? "top" : "bottom";
			if (self.align == "from-top-or-bottom") {
				posY = "from-"+(posY == "top" ? "bottom" : "top");
			}
		} 
		
		var position = posX+","+posY;
				
		self.setPosition(position);
		
		self.set(sl.getElementPosition(self.getAnchorEl(),position,self.menuEl));
		
		self.reposition();		
	};
	
	self.destruct = function() {
		
	};
				
	self.setValues({
		"position":"center,center",
		"x":0,
		"y":0,
		"offX":0,
		"offY":0,
		"align":"none",
		"isShown":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
