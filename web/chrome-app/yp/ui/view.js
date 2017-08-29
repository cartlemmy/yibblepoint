sl.view = function(o) {
	var self = this;
	sl.initSlClass(this,"view");

	var mouseInterface = [
		["move","","",1,1,0,0],
		["left","w-resize","width",1,0,-1,0],
		["right","e-resize","width",0,0,1,0],
		["bottom","s-resize","height",0,0,0,1],
		["corner","","",0,0,1,1]
	];
	var buttonNames = ["close", "size", "minimize"];

	self.init = function() {
		if (self.widget) {
			self.appBarItem = false;
			self.el = sl.dg("",self.contEl,"div",{
				"className":"sl-widget"
			});
			
			self.elInner = sl.dg("",self.el,"div",{});
			
			self.setPosition(0,0);
			
			self.app.request("varGet",["widget",self.ref],function(r){
				self.widgetInit(r);
			});
		} else {
			self.el = sl.dg("",self.contEl,"div",{
				"className":"sl-view",
				"style":{"visibility":self.startHidden ? "hidden" : ""}
			});
			
			self.elInner = sl.dg("",self.el,"div",{
				"className":self.innerClass(),
				"style":{"overflow":self.noScroll ? "hidden" : ""}
			});
			
			if (sl.config.isMobile) self.contentPadding = "8px";
			
			if (self.contentPadding) self.elInner.style.padding = self.contentPadding;
			if (sl.config.isMobile) {
				if (!self.optSet("no-close-button")) {
					self.buttons.closeEl = sl.dg("",self.el,"div",{
							"className":"sl-mobile-close-button",
							"title":"close"
						});
						sl.addEventListener(self.buttons.closeEl,"click",self.button,false);
				}
			} else {
				var n = 0, right = sl.config.theme["button-right"];
				for (var i = 0; i < buttonNames.length; i++) {
					if (!self.optSet("no-"+buttonNames[i]+"-button")) {
						self.buttons[buttonNames[i]+"El"] = sl.dg("",self.el,"div",{
							"className":"sl-view-button",
							"style":{
								"backgroundPosition":"-"+((buttonNames.length - n - 1) * sl.config.theme["button-size"][0])+"px 0px ",
								"right":right+"px"
							},
							"title":buttonNames[i]
						});
						sl.addEventListener(self.buttons[buttonNames[i]+"El"],"click",self.button,false);
						right += sl.config.theme["button-size"][0];
					}
					n ++;
				}
						
				self.buttonsWidth = right;
			
			
				self.elTitle = sl.dg("",self.el,"div",{
					"className":"sl-view-title",
					"style":{
						"right":right+"px"
					},
					"innerHTML":self.title
				});
				
				self.elTitle.mi = 0;
				sl.addEventListener(self.elTitle,"mousedown",self.mouse,false);		
				
				self.elIconBut = sl.dg("",self.el,"div",{"className":"sl-view-icon"});
				
				self.elIcon = sl.dg("",self.elIconBut,"div",{"className":"icon"});
				
				self.contextMenu = new sl.menu({"core":self.core,"buttonEl":self.elIconBut,"contents":[
					{"label":"en-us|Get Info","action":"get-info"}
				],"align":"horizontal","offY":0});
				
				self.contextMenu.addEventListener("click",function(type,o) {
					switch (o.item.action) {
						case "get-info":
							self.app.request("getInfo",[sl.refEncode(self.app.args)],function(info){
								var overlay = new sl.viewOverlay({"view":self});
								overlay.elContent.innerHTML = info;
								overlay.updateContentSize();
								
							});
							break;
					}
					self.dispatchEvent("context-click",o);
				});
				if (self.icon) self.setIcon(self.icon);
			}
		}
		
		self.calculateSpace();
		
		if (!sl.config.isMobile && !self.widget && !self.optSet("no-resize")) {
			for (var i = 1; i < 4; i++) {
				var mi = mouseInterface[i];
				var el = sl.dg("",self.el,"div",{
					"style":{
						"position":"absolute",
						"cursor":mi[1]
					}
				});
				
				if (mi[2] == "width") {
					el.style.top = self.spaceOuter.top+"px"
				} else {
					el.style.left = self.spaceOuter.left+"px"
				}
				el.style[mi[0]] = "0px";
				el.style[mi[2]] = self.spaceOuter[mi[0]]+"px";
				
				sl.addEventListener(el,"mousedown",self.mouse,false);		
				
				el.mi = i;
				self[("el-resize-"+mi[0]).toCamelCase()] = el;
			}
			
			self.elResize = sl.dg("",self.el,"div",{
				"className":"sl-view-resize"
			});
			
			self.elResize.mi = 4;
			sl.addEventListener(self.elResize,"mousedown",self.mouse,false);		
		}
		
		if (self.tools.length) {
			self.toolsEl = sl.dg("",self.el,"div",{
				"className":"sl-view-tool"
			});

			for (var i = 0; i < self.tools.length; i++) {
				self.toolEl[self.tools[i]] = sl.dg("",self.toolsEl,"div",{
					"className":"sl-view-tool-"+self.tools[i]
				}, self.tools[i] == "save" ? true : null);
				self.toolSetup(self.tools[i],self.toolEl[self.tools[i]]);
			}
		}
			
		self.setPositionAndSize(self.x,self.y,self.width,self.height,1);
		self.refresh();
		
		self.el.addEventListener("keypress",function(e){
			self.dispatchEvent("keypress-"+sl.getKeyFromEvent(e),e);
		});
		
		if (sl.config.isMobile) {
			self.setState("maximized");
			self.setPosition(0,0);
		}
	};
	
	self.appendContextMenu = function(item) {
	};
	
	self.widgetInit = function(o) {
		if (o !== null) {
			self.setPosition(o.x,o.y);
			self.setOuterSize(o.w,o.h);
			
			self.core.registerWidget(self);			
		} else {
			self.setOuterSize(self.minWidth,self.minHeight);
			
			var pos = self.core.requestWidgetSpace(self.outerWidth,self.outerHeight);
			
			if (pos) {
				self.setPosition(pos.x,pos.y);
				self.core.registerWidget(self);
				
				self.app.request("varSet",["widget",self.ref,{"x":self.x,"y":self.y,"w":self.outerWidth,"h":self.outerHeight}],function(r){});
				
			} else {
				//TODO: no room for widget
			}
		}
	};
	
	self.innerClass = function(maximized) {
		return (maximized?"sl-view-inner-maximized":"sl-view-inner")+(self.tools.length?" tools":"");
	};
	
	self.setActive = function(yes) {
		if (yes && self.active != !!yes) {
			self.activeNum = self.core.activeViewNum++;
		}
		self.active = !!yes;
		
		if (yes === null) return;
		self.el.style.zIndex = yes ? 10 : 0;
		
		var c = sl.getChildNodes(self.el);
		c.push(self.el);
		var s = ["sl-view","sl-view-button","sl-view-title","sl-view-inner","sl-view-resize"];
		for (var j = 0; j < c.length; j++) {
			for (var i = 0; i < s.length; i++) {
				if (c[j].className) {
					var ci, cnSplit = c[j].className.split(" ");
					if ((ci = cnSplit.indexOf(s[i]+"-inactive")) != -1) {
						cnSplit.splice(ci,1);
					}
					
					if ((ci = cnSplit.indexOf(s[i])) != -1) {
						cnSplit[ci] = s[i]+(yes ? "" : " "+s[i]+"-inactive");
						c[j].className = cnSplit.join(" ");
						break;
					}
				}
			}
		}
		
		if (yes && self.app) {
			self.app.title = self.title;
			self.core.dispatchEvent("app-update",self.app);
		}
		
		if (self.appBarItem) self.appBarItem.setActive(yes);
	};
	
	self.setTitle = function(title) {
		if (title !== undefined) self.title = title;
		self.extraTitle = (self.navElements.length > 0 ? sl.config.sep+self.navElements[self.navNum].title : "");
		var title = (self.saveState == "unsaved" ? "* " : "") + self.title + self.extraTitle;
		if (self.elTitle) self.elTitle.innerHTML = title;
		if (self.app && self.app.title) self.app.title = title;
		if (self.appBarItem && self.appBarItem.textEl) self.appBarItem.textEl.innerHTML = title;
		if (self.active) self.core.updateTitle();
	};
	
	self.setIcon = function(icon) {
		self.icon = icon;
		if (self.appBarItem) self.appBarItem.setIcon(icon);
		if (icon && self.elIcon) self.elIcon.style.backgroundImage = "url('"+icon+"-24.png')";
	};
	
	self.button = function(e) {
		if (self.destructing) return;
		self[e.target.title]();
	};
	
	self.appBarClick = function() {
		if (self.state == "minimized") {
			self.core.setActiveView(self);
			self.size();
		} else {
			if (self.active) {
				self.setState("minimized");
			} else {
				self.core.setActiveView(self);
			}
		}
	};
	
	self.close = function() {
		self.destruct(true);
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
	
	self.updateNoItemMessage = function(element) {
		if ((element = self.element(element)) && element.noItem) {
			var c = element.noItem.tbody.childNodes;
			var noItem = true;
			for (var i = 0; i < c.length; i++) {
				if (c[i].nodeName == "TR" && c[i].className != "no-item") {
					noItem = false;
					break;
				}
			}
			if (element.noItem.show != noItem) {
				if (noItem) {
					element.noItem.tbody.appendChild(element.noItem.tr);
				} else {
					element.noItem.tbody.removeChild(element.noItem.tr);
				}
				element.noItem.show = noItem;
			}
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
		
	self.setState = function(state) {
		if (self.widget) return;
		if (sl.config.isMobile && (state == "optimal" || state == "user")) state = "maximized";
		
		if (self.state == "minimized" && self.snapShotIm) {
			self.el.style.opacity = 0;
			sl.efx.appear(sl.getElementXYWH(self.appBarItem.el),self.el,self.snapShotIm,function(){
				self.el.style.display = "";
			});
		}
		
		if (self.el) {
			self.el.className = state == "maximized" ? "sl-view-maximized" : "sl-view";
			self.elInner.className = self.innerClass(state == "maximized");
			if (self.elResize) self.elResize.style.display = state == "maximized" ? "none" : "";
			
			self.calculateSpace();
			
			switch (state) {
				case "optimal":
					if (!self.sizeOptimal()) return false;
					break;

				case "maximized":
					self.setPositionAndSize(0,0,self.core.width - (self.spaceOuter.left + self.spaceOuter.right),self.core.height - (self.spaceOuter.top + self.spaceOuter.bottom),false,true);
					break;
					
				case "user":
					if (!self.userState) return false;
					self.setPositionAndSize(self.userState.x,self.userState.y,self.userState.width,self.userState.height);
					break;			
					
				case "minimized":
					sl.efx.appear(sl.getElementXYWH(self.appBarItem.el),self.el,self.snapShotIm,function(){
						self.el.style.display = "hidden";
					},1);
					break;
			}
		}
		
		self.state = state;
		return true;
	};
	
	var stateCycle = ["optimal","maximized","user"];
	self.size = function() {
		if (self.previousState) {
			self.setState(self.previousState);
			return;
		}
		
		var newState;
		while (!self.setState(newState = stateCycle[(stateCycle.indexOf(self.state) + 1) % 3])) { self.state = newState; }
		self.previousState = "";
	};
	
	self.minimize = function() {
		self.previousState = self.state;
		
		if (self.needsNewSnapshot) {
			self.getSnapshot(function(){
				self.setState("minimized");
			});
		} else self.setState("minimized");			
	};
	
	self.maximize = function() {
		self.setState("maximized");
	};
		
	// Tools
	self.toolSetup = function(name,el) {
		switch (name) {
			case "search":
				var input = sl.dg("",el,"input",{});
				var button = sl.dg("",el,"button",{});
				
				var inputTimer = null;
				sl.addEventListener(input,"keyup",function() {
					if (inputTimer) clearTimeout(inputTimer);
					inputTimer = setTimeout(function(){
						self.dispatchEvent("search-change",input.value);
						if (input.value == "") self.dispatchEvent("search-click","");
					},100);
				},false);
				
				sl.addEventListener(input,"keydown",function(e) {
					if (inputTimer) clearTimeout(inputTimer);
					if (e.keyCode == 13) self.dispatchEvent("search-click",input.value);
				},false);
				
				sl.addEventListener(input,"change",function(){
					if (inputTimer) clearTimeout(inputTimer);
					self.dispatchEvent("search-change",input.value);
				},false);
				
				sl.addEventListener(button,"click",function(){
					self.dispatchEvent("search-click",input.value);
				},false);
				break;
				
				case "save":
					self.saveButtonEl = sl.dg("",el,"div",{"style":{"cursor":"pointer"}});
					self.saveButtonInnerEl = sl.dg("",self.saveButtonEl,"div",{});
					
					sl.addEventListener(self.saveButtonEl,"click",function() {
						if (self.saveState == "new" || self.saveState == "unsaved") {
							self.save();
						}
					},false);					
					break;
				
				case "navigation":					
					self.prevButtonEl = sl.dg("",el,"div",{});
					self.prevButtonEl.addEventListener("click",function(){self.navigateTo(self.navNum - 1);});
					self.nextButtonEl = sl.dg("",el,"div",{});
					self.nextButtonEl.addEventListener("click",function(){self.navigateTo(self.navNum + 1);});
					self.navElements = [];
					self.navNum = 0;
					self.updateNavigation();
					break;
		}
	};
	
	self.doSave = function() {
		self.dispatchEvent("save-click",self.saveState);
		self.setSaveState("saving");
	};
	
	//Menu
	self.setMenu = function(menu) {
		self.menuItems = [];
		for (var i = 0; i < menu.length; i++) {		
			(function(item) {
				item.el = sl.dg("",self.toolEl["menu"],"div",{
					"innerHTML":item.label
				});
				if (item.children) {
					item.subMenu = new sl.menu({"core":self.core,"buttonEl":item.el,"contents":item.children,"align":"horizontal","offY":-9});
			
					item.subMenu.addEventListener("click",function(type,o) {
						self.dispatchEvent("menu-click",o);
					});
				} else {
					sl.addEventListener(item.el,"click",function(e) {
						if (item.click) item.click.call(self,item);
			
						self.dispatchEvent("menu-click",{"event":e,"item":item});
					},false);
				}
				self.menuItems.push(item);
			})(menu[i]);
		}
	};
	
	self.removeMenuItem = function(i) {
		var item = self.menuItems[i];
		if (item) {
			item.el.parentNode.removeChild(item.el);
			self.menuItems.splice(i,1);
		}
	};
	
	// Navigation
	function navElement(title) {
		var nav = this;
		nav.title = title;
		nav.el = self.appendElement("","div",{"className":"sl-view-module","style":{"display":"none"}});
	};
	
	self.updateNavigation = function() {
		self.core.setCommonIcon(self.prevButtonEl,"prev"+(self.navNum == 0?"-disabled":""));
		self.prevButtonEl.title = self.navNum == 0 ? "" : self.navElements[self.navNum - 1].title;
		self.core.setCommonIcon(self.nextButtonEl,"next"+(self.navNum >= self.navElements.length - 1?"-disabled":""));
		self.nextButtonEl.title = self.navNum >= self.navElements.length - 1 ? "" : self.navElements[self.navNum + 1].title;
		self.setTitle();
	};

	self.navigateTo = function(num) {
		if ((num = self.navFromEl(num,true)) === -1) return;
		num = Math.max(0,Math.min(num,self.navElements.length - 1));
		
		self.navNum = num;
		for (var i = 0; i < self.navElements.length; i++) {
			self.navElements[i].el.style.display = num == i ? "" : "none";
		}
		self.updateNavigation();
	};
	
	self.addToNav = function(title) {
		var nav = new navElement(title);
		self.navElements.push(nav);
		self.navigateTo(self.navElements.length - 1);
		return nav.el;
	};
	
	self.updateNavTitle = function(nav,title) {
		if (!(nav = self.navFromEl(nav))) return;
		nav.title = title;
		self.updateNavigation();
	};
	
	self.navFromEl = function(el,returnNum) {
		if (typeof(el) == "number") return el;
		for (var i = 0; i < self.navElements.length; i++) {
			if (self.navElements[i].el == el) return returnNum?i:self.navElements[i];
		}
		return returnNum?-1:null;
	};
	
	self.openConnectedApp = function(o) {
		var app = self.core.open(o);
		app.setConnected(self);
		return app;
	};
	
	self.addConnectedApp = function(app) {
		self.connectedApps.push(app);
	};

	self.connectedAppsFunc = function() {
		if (!self.connectedApps.length) return;
		var args = [];
		for (var i = 1; i < arguments.length; i++) {
			args.push(arguments[i]);
		}
		
		for (var i = 0; i < self.connectedApps.length; i++) {
			self.connectedApps[i][arguments[0]].apply(self,args);
		}
	};
	
	// Save
	self.setSaveState = function(state) {
		if (state == self.saveState) return;
		
		var stateMap = {
			"new":"en-us|New","unsaved":"en-us|Save",
			"saving":"en-us|Saving","saved":"en-us|Saved"
		};

		switch (state) {
			case "new":
				break;
			
			case "unsaved":
				break;
			
			case "saving":
				break;
			
			case "saved":
				if (self.closeAfterSave) {
					self.destruct();
					return;
				}
				break;
				
			default:
				return;
		}
		
		self.saveButtonEl.className = state == "saved" || state == "new" ? "saved" : "";
		 
		self.saveState = state;
		self.saveButtonInnerEl.innerHTML = stateMap[state];
		
		self.setTitle();
		
		self.dispatchEvent("save-state",state);
	};
	
	self.unsavedCheck = function(fromCloseButton) {
		if (self.saveState == "unsaved") {
			if (fromCloseButton) {
				self.elementPrompt(self.buttons.closeEl,sl.format("en-us|%% has unsaved changes, Do you want to save before closing?",self.title),{"yes":"en-us|Yes","no":"en-us|No"},function(choice){
					if (choice == "yes") {
						self.closeAfterSave = true;
						if (self.save) self.save();
					} else self.destruct(false,true);
				});
				return true;
			} else {
			}
		}
		return false;
	};
	
	// Show
	self.show = function() {
		if (self.showing) return;
		self.showing = true;
		if (self.widget) return;
		self.getSnapshot(function(im){
			sl.efx.appear(null,self.el,im,function(){
				
			});
		});
	};
	
	self.isVisible = function() {
		return self.showing && !self.destructing && self.state != "minimized";
	};
	
	self.getSnapshot = function(cb) {
		
		var canvas = document.createElement('canvas');
		canvas.setAttribute('width',self.el.offsetWidth);
		canvas.setAttribute('height',self.el.offsetHeight);
		
		//TODO: Make it look better
		if (sl.supports("canvas")) {
			var ctx = canvas.getContext('2d');
			ctx.fillStyle = "#FFF";
			ctx.strokeStyle = "#999";
			ctx.lineWidth = 1;
			
			ctx.fillRect(0,0,self.el.offsetWidth,self.el.offsetHeight);
			ctx.strokeRect(0.5,0.5,self.el.offsetWidth-1,self.el.offsetHeight-1);
			
			self.snapShotIm = canvas;
				
			/*self.snapShot = new html2Image(self.el,function(im){
				self.needsNewSnapshot = false;
				self.snapShotIm = im;
				if (cb) cb(im);
			},{"stopAt":{"el":self.elInner}});*/
		}
		cb(self.snapShotIm);
	};
	
	// Content
	self.appendElement = function(id,t,a,pre) {
		return sl.dg(id,self.elInner,t,a,pre);
	};
	
	self.setContentAsHTML = function(html) {
		self.elInner.innerHTML = sl.parseHTML(html);
		self.initContent();
	};
	
	self.setContentFromHTMLFile = function() {
		self.setContentAsHTML(self.html?self.html:"en-us|HTML file not found.");
	};
	
	self.formatContent = function() {
		var args = [], i;
		for (i = 0; i < arguments.length; i++) {
			args.push(arguments[i]);
		}
		args.unshift(null);
		
		var c = sl.getChildNodes(self.el);
		var r = ["href","title","alt"];
		for (i = 0; i < c.length; i++) {
			if (c[i].nodeType == 3 && c[i].textContent.indexOf("%") != -1) {
				args[0] = c[i].textContent;
				c[i].textContent = sl.format.apply(self,args);
			} else if (c[i].nodeType == 1) {
				for (var j = 0; j < r.length; j++) {
					if (c[i][r[j]] && c[i][r[j]].indexOf("%") != -1) {
						args[0] = c[i][r[j]];
						c[i][r[j]] = sl.format.apply(self,args);
						if (r[j] == "href") self.initContentForElement(c[i]);

					}
				}
			}
		}
	};
	
	self.initContentForElement = function(el,listener,params,view,parentView) {
		if (!view) view = self;
		if (!listener) listener = view;
		if (!parentView) parentView = view;
		
		return sl.initContentForElement(el,listener,params,view,parentView);
	};
	
	self.addField = function(o) {
		o.contEl = sl.dg("",o.contEl,"fieldset",{"style":{"cssFloat":"left"}});
		sl.dg("",o.contEl,"label",{"innerHTML":o.label});
		var field = new sl.field(sl.defaultValues({
			"core":self.core,
			"view":self
		},o));
		if (o.width) {
			field.el.style.width = o.width+"px";
			o.contEl.style.width = o.width+"px";
		}
		return field;
	};
	
	self.initContent = function(width,height) {
		self.initContentForElement(self.elInner);
		
		self.setSizeFromContent();
		
		if (width !== undefined || height !== undefined) {
			self.setSize(width,height);
		}
		
	};
	
	self.setSizeFromContent = function() {
		self.width = self.elInner.offsetWidth + self.spaceInner.left + self.spaceInner.right;
		self.height = self.elInner.offsetHeight + self.spaceInner.top + self.spaceInner.bottom;
				
		self.setOutSize();
		self.setOptimal();
		
		self.setSize(self.width,self.height);
	};
	
	self.field = function(id,parent) {
		var el;
		if (
			(el = self.element(id,parent)) &&
			el.slSpecial) return el.slSpecial;
		return null;
	};
	
	self.getFieldValues = function(el) {
		if (!el) el = self.el;
		var rv = {}, field, c = sl.getChildNodes(el);
		for (var i = 0; i < c.length; i++) {
			if (c[i].getAttribute && (field = c[i].slSpecial)) rv[field.n] = field.getValue();
		}
		return rv;
	};
	
	self.element = function(id,parent) {
		if (typeof(id) != "string") return id;
		
		var q = id.split(/(=| contains )/);
		if (q.length != 1) return self.getElement(q,parent ? parent : self.el);
		
		var c = sl.getChildNodes(parent ? parent : self.el);
		
		for (var i = 0; i < c.length; i++) {
			if (c[i].getAttribute && c[i].getAttribute("data-slid") == id) return c[i];
		}
		return null;
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
	
	// Position / size
	self.setPositionAndSize = function(x,y,width,height,setUserState,force) {		
		self.setPosition(x,y,0,1);
		self.setSize(width,height,setUserState,force);
	};
	
	self.setPosition = function(x,y,setUserState,dontRefresh) {
		x = Math.round(x);
		y = Math.round(y);
		
		if (x != self.x || y != self.y) {
			if (x < 0) x = 0;
			if (y < 0) y = 0;
			self.el.style.left = x+"px";
			self.el.style.top = y+"px";
			if (!dontRefresh) self.refresh(setUserState);
		
			self.x = x;
			self.y = y;
		}
		sl.coreOb.mainEl.scrollTop = sl.coreOb.mainEl.scrollLeft = 0;
	};
	
	self.setOuterSize = function(w,h) {
		var is = self.outerSpace();
		self.setSize(w - is.width, h - is.height,false,true);
	};
	
	self.setSize = function(w,h,setUserState,force) {
		if (!(force || self.state != "maximized")) return;

		var is = self.outerSpace(), positionChange = false;
				
		if (w && w + is.width > self.core.width) w = self.core.width - is.width;
		if (w && w != self.width) {	
			if (self.x + (w - is.left) > self.core.width) {
				positionChange = true;
				self.x = Math.max(0,self.core.width - (w + is.left));
			}
			
			self.elInner.style.width = Math.round(w)+"px";
			self.width = Math.max(w,self.elInner.offsetWidth - (self.spaceInner.left + self.spaceInner.right));
			if (self.mPos.width) self.mPos.width -= w - self.width;
			self.needsNewSnapshot = true;
		}
		
		if (h && h + is.height > self.core.height) h = self.core.height - is.height;
		if (h && h != self.height) {
			if (self.y + (h - is.top) > self.core.height) {
				positionChange = true;
				self.y = Math.max(0,self.core.height - (h + is.top));
			}
			
			self.elInner.style.height = Math.round(h)+"px";
			self.height = Math.max(h,self.elInner.offsetHeight - (self.spaceInner.top + self.spaceInner.bottom));
			if (self.mPos.height) self.mPos.height -= h - self.height;
			self.needsNewSnapshot = true;
		}
				
		if (positionChange) self.setPosition(self.x,self.y);
		
		if (self.needsNewSnapshot) {
			self.dispatchEvent("resize",{"width":self.width-(self.spaceInner.left + self.spaceInner.right),"height":self.height-(self.spaceInner.top + self.spaceInner.bottom)});
		}
		self.setOutSize();
		self.refresh(setUserState);
		if (setUserState && !sl.config.isMobile) self.state = "user";
		sl.coreOb.mainEl.scrollTop = sl.coreOb.mainEl.scrollLeft = 0;
	};
	
	self.setOutSize = function() {
		var is = self.outerSpace();
		self.outerWidth = self.width + is.width;
		self.outerHeight = self.height + is.height;
	};
	
	self.outerSpace = function() {
		var left = self.spaceInner.left + self.spaceOuter.left;
		var right = self.spaceInner.right + self.spaceOuter.right;
		var top = self.spaceInner.top + self.spaceOuter.top;
		var bottom = self.spaceInner.bottom + self.spaceOuter.bottom;
		
		return {
			"left":left,
			"right":right,
			"top":top,
			"bottom":bottom,
			"width":left + right,
			"height":top + bottom
		};
	};
	
	self.center = function() {
		self.setPosition(Math.round((self.core.width - self.outerWidth) / 2),Math.round((self.core.height - self.outerHeight) / 2));
		self.setOptimal();
	};
	
	self.calculateSpace = function() {
		var styleOuter = window.getComputedStyle(self.el);
		var styleInner = window.getComputedStyle(self.elInner);
		var t = ["top","right","bottom","left"];
		for (var i = 0; i < t.length; i++) {
			self.spaceOuter[t[i]] = sl.toPx(styleOuter.getPropertyValue("padding-"+t[i])) + sl.toPx(styleOuter.getPropertyValue("border-"+t[i]+"-width")) + sl.toPx(styleInner.getPropertyValue("margin-"+t[i]));
			self.spaceInner[t[i]] = sl.toPx(styleInner.getPropertyValue("padding-"+t[i])) + sl.toPx(styleInner.getPropertyValue("border-"+t[i]+"-width"));
		}
	};
	
	self.setOptimal = function(x,y,width,height) {
		if (sl.config.isMobile) return;
		self.state = "optimal";
		self.userState = null
		self.optimalState = {
			"x":x ? x : self.x,
			"y":y ? y : self.y,
			"width":width ? width : self.width,
			"height":height ? height : self.height
		};
	};
	
	self.sizeOptimal = function() {
		if (!self.optimalState) return false;
		self.setPositionAndSize(self.optimalState.x,self.optimalState.y,self.optimalState.width,self.optimalState.height);
		return true;
	};
	
	self.mainViewChanged = function() {
		self.setState(self.state);
	};
	
	self.refresh = function(setUserState) {
		if (self.widget) return;
		if (self.elTitle) self.elTitle.style.width = (self.width - (self.buttonsWidth - self.spaceOuter.right) - sl.config.theme["button-size"][0]) + "px";
		
		if (self.elResizeLeft && !self.optSet("no-resize")) {
			self.elResizeLeft.style.height = self.elResizeRight.style.height = self.height+"px";
			self.elResizeBottom.style.width = self.width+"px";
		}
		
		if (setUserState) self.userState = {
			"x":self.x,
			"y":self.y,
			"width":self.width,
			"height":self.height,
		};

		for (var i = 0; i < self.messageBox.length; i++) {
			self.messageBox[i].elementPosition();
		}
	};
	
	self.addResizeListener = function(el,cb) {
		self.listeners.push(self.addEventListener("resize",cb));
		var node = el.parentNode;
		while (node && node != self.elInner) {
			if (node.slSpecial) {
				self.listeners.push(node.slSpecial.addEventListener("resize",cb));
			}
			node = node.parentNode;
		}
		//TODO: return listener reference
	};
	
	self.registerAppBar = function() {
		if (self.widget) return;
		self.core.registerAppBar(self);
		self.appBarItem.setIcon(self.icon);
	};
	
	//Other
	self.optSet = function(opt) {
		return self.options.indexOf(opt) != -1;
	};
		
	self.destruct = function(fromCloseButton,force) {
		if (self.destructing) return;
	
		if (!force && ((self.beforeDestruct && self.beforeDestruct() === false) || self.unsavedCheck(!!fromCloseButton))) return;
		
		self.dispatchEvent("destruct");
	
		self.destructing = true;
		
		self.setActive(false);
		
		for (var i = 0; i < buttonNames.length; i++) {
			if (self.buttons[buttonNames[i]+"El"]) self.buttons[buttonNames[i]+"El"].style.cursor = "default";
		}
				
		for (var i = 0; i < self.messageBox.length; i++) {
			self.messageBox[i].destruct();
		}
		
		for (var i = 0; i < self.listeners.length; i++) {
			self.removeEventListener(self.listeners[i]);
		}		
		
		var el;
		while (el = self.specialElements.pop()) {
			el.destruct();
		}
		
		self.core.removeView(self);
		
		if (self.appBarItem) self.appBarItem.destruct();
		
		if (self.isMainView) {
			self.app.destruct();
		}
		
		self.core.showLastActiveView();
		
		self.connectedAppsFunc("destruct");
		
		sl.efx.fade(self.el,function(){
			if (self.el.parentNode) self.el.parentNode.removeChild(self.el);
		});
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
		"connectedApps":[],
		"listeners":[]
	});
	
	if (o) self.setValues(o);
	
	self.init();
};

sl.foldableSection = function(o) {
	var self = this;
	sl.initSlClass(this,"foldable-section");
	
	self.init = function() {
		if (!self.contEl) {
			self.contEl = self.el.parentNode;
			var oldEl = self.el;
			self.el = sl.dg("",self.contEl,"div",{"className":"foldable-section"},{"before":oldEl});
			self.el.setAttribute("data-slid",self.id);
			self.el.slSpecial = self;
			oldEl.parentNode.removeChild(oldEl);
		} else {
			self.el = sl.dg("",self.contEl,"div",{"className":"foldable-section"});
		}
		
		self.titleEl = sl.dg("",self.el,"div",{"className":"title"});
		self.contentEl = sl.dg("",self.el,"div",{"style":{"padding":"10px"}});
		
		if (self.view && self.view.app) {
			self.view.app.request("varGet",["foldable-section",self.getId()],function(r){
				self.show(r !== null ? r : self.open);
			});
		} else self.show(self.open);
			
		self.titleEl.addEventListener("click",function(){
			self.show(!self.shown);
		});
			
		self.setTitle(self.title);
		
	};
	
	self.setTitle = function(title) {
		if (self.titleEl) {
			self.titleEl.innerHTML = title;
		}
		self.title = title;
	};
	
	self.show = function(yes) {
		if (yes === undefined) yes = true;
		self.el.className = "foldable-section "+(yes ? "open" : "closed");
		self.contentEl.style.display = yes ? "block" : "none";
		self.shown = yes;
		if (self.view && self.view.app) {
			self.view.app.request("varSet",["foldable-section",self.getId(),yes],function(r){});
		}
	};
	
	self.hide = function() {
		self.show(false);
	};
	
	self.getId = function() {
		var node = self.el, id;
		if (self.id) {
			id = self.id;
		} else {
			while (node.getAttribute && !(id = node.getAttribute("data-slid"))) {
				node = node.parentNode;
			}
		}
		return (id?"-"+id:"main");
	};
		
	self.setValues({
		"shown":false,
		"holdCnt":0
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
