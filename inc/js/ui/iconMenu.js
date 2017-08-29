sl.iconMenu = function(o) {

	var self = this;
	sl.initSlClass(this,"icon-menu");

	self.init = function() {
		for (var i = 0; i < self.menu.length; i++) {
			var el = sl.dg("",self.el,"div",{"className":"icon-menu"});
			self.menu[i].img = sl.dg("",el,"img",{"src":self.menu[i].src});
			self.menu[i].label = sl.dg("",el,"label",{"innerHTML":self.menu[i].label});
			self.menu[i].el = el;
			if (self.menu[i].detail) self.menu[i].detail = sl.dg("",el,"div",{"innerHTML":self.menu[i].detail});
			
			(function(item){
				item.el.addEventListener("click",function(){
					self.dispatchEvent("click",item);
				});
			})(self.menu[i]);
		}
		self.refresh();
		self.resizeCheckTimer = setInterval(self.resizeCheck,200);
	};

	self.resizeCheck = function() {
		if (self.width != self.el.offsetWidth || self.height != self.el.offsetHeight) {
			self.refresh();
		}
	};
	
	self.refresh = function() {
		self.width = self.el.offsetWidth;
		self.height = self.el.offsetHeight;
		var size = Math.min(280,Math.min(self.el.offsetWidth,Math.sqrt(self.el.offsetWidth*self.el.offsetHeight)) * 0.5);
		var cols = Math.min(self.menu.length,Math.floor(self.width / size));
		var margin = Math.floor((self.el.offsetWidth - 20 - (cols * size)) / 2);
		var colNum = 0;
		for (var i = 0; i < self.menu.length; i++) {
			self.menu[i].el.style.marginLeft = "10px";
			var pad = sl.getTotalElementSize(self.menu[i].el, true);
			self.menu[i].el.style.width = (size-pad.width)+"px";
			self.menu[i].el.style.minHeight = (size)+"px";
			self.menu[i].el.style.marginLeft = colNum==0?margin+"px":"10px";
			self.menu[i].el.style.fontSize = Math.round(size/1.5)+"%";
			if (self.menu[i].detail) self.menu[i].detail.style.fontSize = Math.max(10,Math.round(size/20))+"px";
			self.menu[i].img.style.marginLeft = Math.round((self.menu[i].el.offsetWidth - self.menu[i].img.offsetWidth)/ 2)+"px";
			colNum++;
			if (colNum >= cols) colNum = 0;
		}
	};
	
	self.destruct = function() {
		if (self.resizeCheckTimer) clearInterval(self.resizeCheckTimer);
	};
	
	self.setValues({
		"menu":null,
		"width":-1,
		"height":-1
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
