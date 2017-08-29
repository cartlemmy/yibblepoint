sl.tabbed = function(o) {
	var self = this;
	sl.initSlClass(this,"tabbed");

	self.init = function() {
		if (self.isInit) return;
		
		self.topLevel = self.el.parentNode.className.indexOf("sl-view-inner") != -1;
		
		self.el.slSpecial = self;
		
		self.tabEl = sl.dg("",self.el,"div",{
			"className":"tabbed-top"
		});
		
		var pad = sl.getTotalElementSize(self.el,1);
		self.tabsWidth = pad.width;
		
		var pad = sl.getTotalElementSize(self.tabEl,1);
		self.tabsWidth += pad.width;

		self.width = self.startWidth;
		self.height = self.startHeight;
				
		var c = self.el.childNodes;
		for (var i = 0; i < c.length; i++) {
			if (c[i].nodeName == "SECTION") {
				self.initTab({"section":c[i]});
			}
		}
		
		self.setSelected(0);
		self.tabsWidth = Math.min(self.maxWidth,self.tabsWidth);
		if (self.view && self.view.app && self.view.app.args[1] != "NEW") {
			self.view.app.request("varGet",["tab-open",self.getId()],function(r){
				if (r !== null) self.setSelected(r);
			});
		}
		
		self.refresh();
		
		self.resizeListener = self.view.addResizeListener(self.el,function(t,o){
			if (self.topLevel) {
				self.setSize(o.width,o.height);
			} else {
				self.el.width = "1px";
				//var pad = sl.getTotalElementSize(self.el.parentNode, true);
				//self.setSize(self.el.parentNode.offsetWidth-pad.width,self.fitParent ? self.el.parentNode.offsetHeight-pad.height : false);
				
				var size = sl.innerSize(self.el.parentNode);
				self.setSize(size.width,self.fitParent ? size.height : false);
			}
			self.updateHeight();
		});
		self.isInit = true;
	};

	self.getId = function() {
		var node = self.el, id;
		if (self.id) {
			id = self.id;
		} else {
			while (node && node.getAttribute && !(id = node.getAttribute("data-slid"))) {
				node = node.parentNode;
			}
		}
		return self.view.app.args.join("-").safeName()+"-"+(id?"-"+id:"main");
	};
	
	self.addTab = function(title,o) {
		if (!o) o = {};
		o.section = sl.dg("",self.el,"section",{
			"title":title
		});
		
		var tab = self.initTab(o);
		if (o.showTab === undefined || o.showTab) self.setSelected(o.section);
		return tab;
	};
	
	self.initTab = function(o) {
		o.tabbed = self;
		var tab = new sl.tab(o);
		self.tabs.push(tab);
		self.updateWidth();
		return tab;
	};
	
	self.hideTab = function(s) {
		if ((s = self.getTabNumber(s)) == -1) return;
		self.tabs[s].hide();
	};
	
	self.showTab = function(s) {
		if ((s = self.getTabNumber(s)) == -1) return;
		self.tabs[s].show();
	};
	
	self.setSelected = function(s,user) {
		if ((s = self.getTabNumber(s)) == -1) return;
		
		if (user && self.view && self.view.app) {
			self.view.app.request("varSet",["tab-open",self.getId(),s],function(r){});
		}
		
		self.checked(s);
		
		if (s != self.selected) {
			self.selected = s;
			self.refresh();
			self.dispatchEvent("changed",{"num":s,"tab":self.tabs[s]});
			self.updateHeight();
			self.tabs[s].dispatchEvent("appeared");
		}
	};
	
	self.updated = function(s) {
		if ((s = self.getTabNumber(s)) == -1) return;
		self.tabs[s].updated();
	};
	
	self.checked = function(s) {
		if ((s = self.getTabNumber(s)) == -1) return;
		self.tabs[s].checked();
	}
	
	self.getTabNumber = function(s) {
		if (!self.tabs || self.tabs.length == 0) return -1;
		
		if (typeof(s) == "number") return Math.min(self.tabs.length - 1,s);
		
		for (var i = 0; i < self.tabs.length; i++) {
			if (self.tabs[i] == s || self.tabs[i].section == s || self.tabs[i].tab == s) {
				return i;					
			}
		}
		
		return -1;
	};
	
	self.updateWidth = function() {
		for (var i = 0; i < self.tabs.length; i++) {
			var tab = self.tabs[i];
			if (!tab.initialSizing) {
				var size = sl.getTotalElementSize(tab.section);
				
				self.width = Math.max(self.width,tab.width);
				self.height = Math.max(self.height,size.height);
				tab.section.style.display = "block";
				tab.section.style.overflow = "auto";
				tab.initialSizing = true;
			}
		}
		var tabContSize = sl.getTotalElementSize(self.tabEl);
		
		self.setSize(self.width,self.height+tabContSize.height);
		if (self.topLevel && self.view.state == "optimal") self.view.setSize(self.width,self.height+tabContSize.height,true);
	};
		
	self.setSize = function(width,height) {
		var pad = sl.getTotalElementSize(self.el,true);

		width -= pad.width;
		height -= pad.height;
		
		if (height)	self.height = height;
		self.width = width;
		self.refresh();
		
		self.updateSize();
		
		self.el.style.width = self.width+"px";
		self.tabEl.style.width = self.width+"px";
	};
	
	self.updateSize = function() {
		var elPad = sl.getTotalElementSize(self.el, true);		
		for (var i = 0; i < self.tabs.length; i++) {
			var tab = self.tabs[i];
			
			var pad = sl.getTotalElementSize(tab.section, true);
			tab.section.style.width = (self.width-pad.width)+"px";
			tab.section.style.height = (self.height-(pad.height+elPad.height+16))+"px"; //TODO: the +16 is kinda hacky
		}
	};
	
	self.updateHeight = function() {
		var tab = self.tabs[self.selected];
		if (!tab) return;
		var section = sl.getTotalElementSize(tab.section);
		var pad = sl.getTotalElementSize(self.el, true);
		var tabContSize = sl.getTotalElementSize(self.tabEl);
		self.el.style.height = Math.max(self.height-tabContSize.height,(section.height-pad.height+tabContSize.height))+"px";
	};
	
	self.refresh = function() {
		if (!self.el) return;
		
		var bottomRow = null, tab = null;
		for (var i = 0; i < self.tabs.length; i++) {
			tab = self.tabs[i];
			
			var show = !tab.hidden && self.selected == i;

			tab.selected = self.selected == i;
			tab.i = i;
			
			tab.section.style.display = show ? "" : "none";
			
			tab.tab.className = show ? "active" : "";
			
			if (tab.tab.offsetTop > (self.tabEl.offsetHeight-tab.tab.offsetHeight)) {
				if (!bottomRow)	bottomRow = tab.tab;
			} else {
				tab.tab.className += " back";
			}
		};
		
		if (self.tabs.length && self.tabs[self.selected]) {
			tab = self.tabs[self.selected];
			if (tab.tab.offsetTop<(self.tabEl.offsetHeight-tab.tab.offsetHeight)) {
				self.tabEl.removeChild(tab.tab);
				if (bottomRow) {
					self.tabEl.insertBefore(tab.tab, bottomRow);
				} else {
					self.tabEl.appendChild(tab.tab);
				}
			}
		}
		var tabContSize = sl.getTotalElementSize(self.tabEl);
		
		self.el.style.marginTop = tabContSize.height+"px";
		self.tabEl.style.top = "-"+tabContSize.height+"px";
	};
		
	self.destruct  = function() {
		self.view.removeEventListener(self.resizeListener);
	};

	self.setValues({
		"resizeListener":null,
		"selected":0,
		"tabs":[],
		"tabsWidth":0,
		"startWidth":300,
		"startHeight":300,
		"width":0,
		"height":0,
		"maxWidth":600,
		"fitParent":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

sl.tab = function(o) {
	var self = this;
	sl.initSlClass(this,"tab");
	
	self.init = function() {
		
		self.tab = sl.dg("",self.tabbed.tabEl,"div",{
			
		});
		
		if (self.closeable) {
			self.closeEl = sl.dg("",self.tab,"div",{
				"className":"close",
				"innerHTML":"[X]"
			});
			self.closeEl.addEventListener("click",function(){
				self.remove();
			},false);
		}
		
		self.titleEl = sl.dg("",self.tab,"div",{
			"innerHTML":self.section.title
		});
		
		sl.addEventListener(self.tab,"click",function() {
			self.tabbed.setSelected(this,true);
		});
		
		self.section.slTab = self;
		
		self.section.style.minWidth = self.tabbed.startWidth+"px";
		var size = sl.getTotalElementSize(self.section);
		self.width = size.width;

		self.section.style.minWidth = "";
		
		if (self.section.title) self.setTitle(self.section.title);
		
		self.section.title = "";
		
		self.section.style.display = "block";
		self.section.style.overflow = "hidden";
	};

	self.hide = function() {
		self.hidden = true;
		self.section.style.display = self.tab.style.display = "none";
		
		if (self.selected) {
			var i = self.i, num = 0;
			while (self.tabbed.tabs[i].hidden) {
				i = (i + self.tabbed.tabs.length - 1) % self.tabbed.tabs.length;
				num ++;
				if (num > self.tabbed.tabs.length) return;
			}
			self.tabbed.setSelected(i);
		}
	};
	
	self.show = function() {
		if (self.firstShow) {
			self.firstShow = false;
			self.tabbed.updateSize();
		}
		self.hidden = false;
		self.section.style.display = "";
		self.tabbed.refresh();
	};
	
	self.setTitle = function(title) {
		self.title = title;
		self.updateTitle();
	};

	self.updateTitle = function() {
		self.titleEl.innerHTML = (self.isUpdated?"* ":"")+self.title;
		self.titleEl.style.fontStyle = self.isUpdated?"italic":"";
	};
	
	self.updated = function() {
		self.isUpdated = true;
		self.updateTitle();
	};

	self.checked = function() {
		self.isUpdated = false;
		self.updateTitle();
	};

	self.remove = function() {		
		var s;
		if ((s = self.tabbed.getTabNumber(self.tab)) == -1) return;
		 
		self.tabbed.tabEl.removeChild(self.tab);
		self.section.parentNode.removeChild(self.section);

		self.tabbed.tabs.splice(s,1);
		self.tabbed.refresh();

		self.tabbed.setSelected(self.tabbed.tabs.length - 1);

		self.dispatchEvent("remove");
		self.tabbed.dispatchEvent("remove",self);
	};
	
	self.setValues({
		"firstShow":false,
		"isInit":false,
		"isUpdated":false,
		"initialSizing":false,
		"selected":false,
		"i":-1
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

