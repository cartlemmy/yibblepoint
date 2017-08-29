sl.appBarItem = function(o) {
	var self = this;
	sl.initSlClass(this,"app-bar-item");

	self.init = function() {
		self.el = sl.dg("",self.appBarEl,"div",{
			"className":"sl-app-bar-item",
		});
		
		sl.addEventListener(self.el,"click",function(){
			if (self.destructing) return;
			self.view.appBarClick();
		},false);
		
		self.iconEl = sl.dg("",self.el,"div",{
			"className":"sl-app-bar-icon"
		});
		
		self.textEl = sl.dg("",self.el,"div",{
			"className":"sl-app-bar-text",
			"innerHTML":self.view.title
		});
		
		self.view.appBarItem = self;
	};
	
	self.setIcon = function(icon) {
		if (icon && self.iconEl) self.iconEl.style.backgroundImage = "url('"+icon+"-24.png')";
	};
	
	self.setActive = function(yes) {
		self.el.className = "sl-app-bar-item"+(yes ? "" : " sl-app-bar-item-inactive");
	};
	
	self.destruct  = function() {
		if (self.destructing) return;
	
		self.destructing = true;
		
		self.setActive(false);
		
		self.el.cursor = "default";
		
		self.core.removeAppBarItem(self);
		
		sl.efx.fade(self.el,function(){
			if (self.el.parentNode) self.el.parentNode.removeChild(self.el);
		});
	};
	
	self.setValues({
		"destructing":false,
		"view":null,
		"appBarEl":null
	});
	
	if (o) self.setValues(o);
	
	self.init();
}

