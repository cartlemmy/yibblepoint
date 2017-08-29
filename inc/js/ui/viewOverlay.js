sl.viewOverlay = function(o) {

	var self = this;
	sl.initSlClass(this,"view-overlay");

	self.init = function() {
		self.el = sl.dg("",self.view.elInner,"div",{"className":"overlay"});
		self.elInner = sl.dg("",self.el,"div",{"style":{"overflow":"auto"}});
		
		self.elOuterContent = sl.dg("",self.elInner,"div",{});
		self.elContent = sl.dg("",self.elOuterContent,"div",{});

		if (!self.noCloseButton) {
			sl.cb(self.elOuterContent);
			self.cancelEl = sl.dg("",self.elOuterContent,"button",{"innerHTML":"en-us|Close","style":{"marginTop":"15px"}});
			self.cancelEl.addEventListener("click",function(){
				self.destruct();
			});
		}
			
		sl.cb(self.elOuterContent);
			
		self.resizeListener = self.view.addEventListener("resize",self.resize);
		self.resize();
	};
	
	self.resize = function(t,o) {
		if (!o) o = self.view;
		self.el.style.width = o.width+"px";
		self.el.style.height = o.height+"px";
		self.el.style.top = self.view.spaceOuter.top+"px";
		
		self.width = o.width;
		self.height = o.height;
		
		self.updateContentSize();
	};
	
	self.updateContentSize = function() {		
		self.elContent.style.width = self.elContent.style.height = "";
		var contentSize = sl.getTotalElementSize(self.elOuterContent);
		
		contentSize.width = Math.min(self.width-20,contentSize.width + 16);
		contentSize.height = Math.min(self.height-20,contentSize.height + 16);
				
		self.elInner.style.left = Math.round((self.width - contentSize.width) / 2)+"px";
		self.elInner.style.top = Math.round((self.height - contentSize.height) / 2)+"px";
		
		contentSize.height = Math.min(self.height,contentSize.height);
		
		self.elInner.style.maxHeight = contentSize.height+"px";
		
	}
	
	self.setValues({
		
	});
	
	self.destruct = function() {
		if (self.resizeListener) self.view.removeEventListener(self.resizeListener);
		if (self.el.parentNode) self.el.parentNode.removeChild(self.el);
	};
	
	if (o) self.setValues(o);
	
	self.init();
};
