sl.core = function(o) {
	var self = this;
	sl.initSlClass(this,"core");
	sl.coreOb = this;
	
	self.setMainEl = function(el) {
		if (el) {			
			sl.addEventListener(el,"mousemove",self.mouse,true);
			sl.addEventListener(el,"mouseup",self.mouse,true);
			sl.addEventListener(el,"mouseout",self.mouse,true);
			sl.addEventListener(el,"mouseover",self.mouse,true);
			sl.addEventListener(el,"click",function(e){
				self.dispatchEvent("click",e);
			},true);
		}
		sl.addEventListener(window,"resize",self.refresh,false);
		sl.addEventListener(window,"beforeunload",self.unload,false);
		self.mainEl = el;
						
		self.refresh();
	};
	
	
	self.unload = function() {
		var app, appUID = [];
		while (app = self.apps.pop()) {
			app.destruct();
			appUID.push(app.uid);
		}
		if (self.net) self.net.send("app-req",{"request":"destruct","windowDestruct":true,"uid":appUID},{"blocking":true},function(){});
	};
	
	self.init = function() {
		self.net.debug = sl.config.dev.netDebug;
		self.originalTitle = document.title;
		self.net.connect();
		self.dlIframe = sl.dg('slDlIframe',document.body,'iframe',{'style':{'position':'absolute','top':'0','border':'none','width':'0px','height':'0px'}});
		
		sl.net = self.net;
		
		self.updateTitle();
		
		self.loadData("timezone",function(d){
			
		});
		
		self.loadData("currency",function(d){
			
		});
		
		self.loadData("abbreviations",function(d){
			
		});

		if (document.getElementById('loadMessageDetail')) {
			var lm = sl.dg("loadMessage");
			sl.efx.fade(lm,function(){
				lm.parentNode.removeChild(lm);
			});
		}
		
		self.objectNameCache = [];
		self.objectNameCacheById = function(ref,id) {
			for (var i = 0; i < self.objectNameCache.length; i++) {
				if (self.objectNameCache[i].ref == ref && self.objectNameCache[i].id == id) return self.objectNameCache[i];
			}
			return null;
		};
		
		sl.loadObjectName = function(value,id,cb,html) {
			var cache;
			if (cache = self.objectNameCacheById(value.ref, id)) return cache.name;
			
			self.net.send("item-name",{"ref":value.ref,"id":id},{},function(response){
				if (response && response.success) {
					self.objectNameCache.push({"ref":value.ref,"id":id,"name":response.name});
					if (self.objectNameCache.length > 50) self.objectNameCache.shift();
					cb(html?'<a href="javascript:sl.coreOb.open(\'edit/?'+value.ref+'&'+id+'\')">'+response.name+'</a>':response.name);
				}
			});
			return false;
		};
		if (sl.onready) sl.onready(self);
	};
	
	self.initInterface = function() {
		self.initAppBar();
		if (sl.config.package.noStartMenu) return;
		self.open("start-menu");
	};
	
	self.initAppBar = function() {
		self.appBarEl = sl.dg("",self.mainEl,"div",{
			"className":"sl-app-bar"
		});

		if (!sl.config.package.noStartMenu) {
			self.appBarMenuEl = sl.dg("",self.appBarEl,"div",{
				"className":"sl-app-bar-menu-button"
			});

			self.appBarMenuTextEl = sl.dg("",self.appBarMenuEl,"div",{
				"className":"sl-app-bar-menu-start",
				"innerHTML":"en-us|Start"
			});
		}
		
		self.appBarCenterEl = sl.dg("",self.appBarEl,"div",{
			"className":"sl-app-bar-center"
		});	

		self.appBarInfoEl = sl.dg("",self.appBarEl,"div",{
			"className":"sl-app-bar-info"
		});

		self.appBarSessionEl = sl.dg("",self.appBarInfoEl,"div",{
			"className":"session"
		});	

		self.appBarNotificationsEl = sl.dg("",self.appBarInfoEl,"div",{
			"className":"notifications"
		});
		
		sl.addEventListener(self.appBarSessionEl,"click",function() {
			self.open("session-info");
		},false);
		
		self.bottomPad = 32;
		
		self.refreshAppBarInfo();
		self.refresh();
	};
	
	self.refreshAppBarInfo = function() {
		if (sl.config.loggedIn) {
			self.appBarSessionEl.innerHTML = "<div>en-us|Logged in as</div>"+sl.config.loggedIn.escapeHtml();
		} else {
			console.log(sl.config.loggedIn);
		}
	};
	
	self.registerAppBar = function(view) {
		var abi = new sl.appBarItem({"core":self,"view":view,"appBarEl":self.appBarCenterEl});
		self.appBarItems.push(abi);
		return abi;
	};
	
	self.removeAppBarItem = function(item) {
		self.appBarItems.splice(self.appBarItems.indexOf(item),1);
	};
	
	self.loadData = function(name,cb) {
		var ref = name.split(/[^A-Za-z\d\_]/), data;
		if (sl.data[ref[0]] === undefined) sl.data[ref[0]] = {};
		if (data = sl.getDeepRef(sl.data,ref.join("."))) cb(data);
		var p = name.split("?");
		name = p.shift();
		sl.require("/data/"+name+".js"+(p.length?"?"+p:""),function(failed){
			if (data = sl.getDeepRef(sl.data,ref.join("."))) cb(data);
		});
	};
	
	self.login = function() {
		var a;
		while (a = self.loginAppQueue.shift()) {
			self.open({"ref":a[0],"args":a[1]});
		}
	};
	
	self.logout = function() {
		self.net.send("logout",{},{"queueTime":0},function(response){
			if (response && response.success) {
				window.location.reload();	
			}
		});
	};
	
	self.mouse = function(e) {
		self.dispatchEvent(e.type,e);
		if (self.activeView) {
			if (e.type == "mouseout") {
				self.mouseOutTimer = setTimeout(function() {
					if (self.activeView) self.activeView.cancelMouse();
				},10);
			} else if (e.type == "mouseover") {
				if (self.mouseOutTimer) clearTimeout(self.mouseOutTimer);	
			} else {
				self.activeView.mouse(e);
			}
		}	
	};
		
	//notifications
	self.addNotification = function(o) {
		if (sl.config.core.fromAPI || !sl.notification) return;
		
		var notification;
		if (o.id && (notification = self.getNotificationById(o.id)) !== null) {
			if (o.message && notification) notification.showMessage(o.message);
		} else {
			o.core = self;
			
			if (o.requiresAnswer) {
				self.unreadNotifications ++;
				self.updateTitle();
			}
			
			notification = new sl.notification(o);
			self.notifications.push(notification);
		}
		return notification;
	};
			
	self.getNotificationById = function(n) {
		if (typeof(n) == "string") {
			for (var i = 0; i < self.notifications.length; i++) {
				if (self.notifications[i].id == n && self.notifications[i]) return self.notifications[i];
			}
			return null;
		}
		return n;
	};
	
	self.removeNotification = function(o) {
		var i;
		if ((i = self.notifications.indexOf(o)) != -1) {
			if (self.notifications[i].requiresAnswer) {
				self.unreadNotifications --;
				self.updateTitle();
			}
			self.notifications[i].destruct();
			self.notifications.splice(i,1);
		}
	};
	
	self.action = function(action,params) {
		switch (action) {
			case "open":
				return self.open(params);
				
			case "open-url":
				return self.openUrl(params);
		}
		return false;
	};
	
	self.openUrl = function(url) {
		if (url.indexOf("?download") != -1) {
			self.dlIframe.src = url;
		} else {
			var w = window.open(url, '_blank');
			w.focus();
		}
	};
	
	//app
	self.isOpen = function(ref) {
		ref = ref.split("/?");
		for (var i = 0; i < self.apps.length; i++) {
			if (ref[0] == self.apps[i].ref) {
				for (var j = 0; j < ref.length - 1; j++) {
					if (ref[j + 1] != self.args[i]) return false;
				}
				return self.apps[i];
			}
		}
		return false;
	};
	
	self.open = function(o) {
		var app;
		if (typeof(o) == "string") {
			var ref = o.split("/?");
			var args = ref.length == 1 ? [] : sl.refDecode(ref.pop(),true);
			var o = {"ref":ref[0],"args":args};
		}
		
		if (typeof(o.args) != "object") o.args = [o.args]
				
		for (var i = 0; i < self.apps.length; i++) {
			app = self.apps[i];
			if (app.singleInstance && app.ref == o.ref) {
				self.setActiveView(app.view);
				if (app.open) app.open.apply(app,o.args);
				return;
			}
		}
		
		o.core = self;
		app = new sl.app(o);
		self.apps.push(app);
		return app;
	};
	sl.open = self.open;
	
	self.openUponLogin = function(ref,args) {
		self.loginAppQueue.push([ref,args]);
	};
	
	self.removeApp = function(app) {
		self.apps.splice(self.apps.indexOf(app),1);
		self.dispatchEvent("app-close",app);
	};
	
	self.closeAppByUID = function(uid) {
		for (var i = 0; i < self.apps.length; i++) {
			if (self.apps[i].uid == uid) {
				self.apps[i].destruct();
				return;
			}
		}
	};
	
	//view
	self.refresh = function() {
		if (self.noInterface) {
			if (self.mainEl) {
				self.width = self.mainEl.offsetWidth;
				self.height = self.mainEl.offsetHeight;
			}
		} else {
			self.width = self.mainEl.offsetWidth;
			self.height = self.mainEl.offsetHeight - (self.appBarEl ? sl.getTotalElementSize(self.appBarEl).height : 0);
			
			var efxWidth = Math.floor(self.width / self.efxScale), efxHeight = Math.floor(self.height / self.efxScale);
			
			if (self.appBarMenuEl) {
				var w = sl.getTotalElementSize(self.appBarMenuEl).width;
				self.appBarCenterEl.style.width = (self.width - (w + sl.getTotalElementSize(self.appBarInfoEl).width)) + "px";
				self.appBarCenterEl.style.left = w + "px";
			}
			
			for (var i = 0; i < self.views.length; i++) {
				self.views[i].mainViewChanged();
			}
			
			if (sl.supports("canvas")) {
				if (!self.efxCanvas || efxWidth != self.efxCanvas.width || efxHeight != self.efxCanvas.height) {
					if (self.efxCanvas) {
						self.efxCanvas.setAttribute("width",efxWidth);
						self.efxCanvas.setAttribute("height",efxHeight);
						self.efxCanvas.style.width = self.width+"px";
						self.efxCanvas.style.height = self.height+"px";
					} else {
						sl.efx.canvas = self.efxCanvas = sl.dg("",self.mainEl,"canvas",{
							"width":efxWidth,
							"height":efxHeight,
							"style":{
								"width":self.width+"px",
								"height":self.height+"px",
								"zIndex":2
							}
						});
						sl.efx.ctx = self.efxCtx = self.efxCanvas.getContext('2d');
					}
					sl.efx.width = self.width;
					sl.efx.height = self.height;
					sl.efx.canvasScale = {
						"width":(efxWidth / self.width),
						"height":(efxHeight / self.height)
					};
				}
			}
			self.updateTitle();
		}
	};

	self.createView = function(o,initAndShow) {
		if (!o) o = {};
		o.contEl = self.mainEl;
		o.core = self;
		var view = new sl.view(o);
		self.views.push(view);		
		
		if (initAndShow) {
			(function(view){
				view.appBarItem = self.registerAppBar(view);
				self.setActiveView(view);
				view.show();
				view.addEventListener("destruct",function(){
					self.removeAppBarItem(view.appBarItem);
				});
			})(view);
		}
		
		return view;
	};
	
	self.removeView = function(view) {
		self.views.splice(self.views.indexOf(view),1);
		if (view == self.activeView) self.activeView = null;
		self.updateTitle();
	};

	self.setActiveView = function(view) {
		if (view != self.activeView) {
			if (self.activeView) self.activeView.setActive(false);
			if (view) view.setActive(true);
		}
		self.activeView = view;
		self.updateTitle();
	};
	
	self.showLastActiveView = function() {
		var views = [];
		for (var i = 0; i < self.views.length; i++) {
			if (self.views[i].isVisible()) views.push(self.views[i]);
		}
		views.sort(function(a,b){
			return b.activeNum - a.activeNum;
		});
		self.setActiveView(views[0]);
	};
		
	self.updateTitle = function() {
		document.title = (self.unreadNotifications ? "("+self.unreadNotifications+") " : "") + (sl.config.core.fromAPI ? self.originalTitle : sl.config.package.name) + (self.activeView ? sl.config.sep + self.activeView.title + self.activeView.extraTitle : "");
	};
	
	self.setCommonIcon = function(el,icon) {
		var i;
		if ((i = self.commonIcons.indexOf(icon)) != -1) {
			el.className = "sl-icon common-icon";
			el.style.backgroundPosition = "-"+(i * 24)+"px 0px";
			el.style.backgroundImage = "";
		}
	};
	
	//Widgets
	function boxCollision(x1,y1,w1,h1,x2,y2,w2,h2) {

		if ( x1+w1 <= x2 || x1 >= x2+w2 ) return false;
    if ( y1+h1 <= y2 || y1 >= y2+h2 ) return false;

    return true;
	};
	
	self.requestWidgetSpace = function(w,h) {
		for (var y = 0; y < self.height; y += 100) {
			for (var x = 0; x < self.width; x += 100) {
				var empty = true;
				for (var i = 0; i < self.widgets.length; i++) {
					var wid = self.widgets[i];
					if (boxCollision(x,y,w,h,wid.x,wid.y,wid.outerWidth,wid.outerHeight)) {
						empty = false;
						break;
					}
				}
				if (empty) return {"x":x,"y":y};
			}			
		}
		return null;
	};
	
	self.registerWidget = function(wid) {
		self.widgets.push(wid);
	};
		
	self.setValues({
		"originalTitle":document.title,
		"efxScale":3,
		"language":sl.config.international.language,
		"mouseOutTimer":null,
		"activeView":null,
		"activeViewNum":0,
		"commonIcons":[
			"disconnected","important","chat","plus","prev","prev-disabled",
			"next","next-disabled","delete","scan","date-pick"
		],
		"apps":[],
		"loginAppQueue":[],
		"appBarItems":[],
		"views":[],
		"widgets":[],
		"width":0,
		"height":0,
		"bottomPad":0,
		"notifications":[],
		"unreadNotifications":0,
		"noInterface":o && !!o.noInterface,
		"mainEl":sl.dg('slMain'),
		"net":new sl.net({"core":self})		
	});
	
	if (o) self.setValues(o);
};
