sl.app = function(o) {
	var self = this;
	sl.initSlClass(this,"app",{"subName":o.ref});
	
	self.setCore = function(core) {
		self.core = core
		self.init();
	};
	
	self.setUid = function(uid) {
		self.uid = uid;
	};
	
	function setAsLoaded() {
		self.isLoaded = true;
		self.dispatchEvent("loaded",self);
	}

	self.init = function() {
		if (!self.core || !self.ref || self.isInit) return;
		self.isInit = true;
	
		self.core.net.send("app",{"ref":self.ref,"args":self.args},{"queueTime":0},function(response){
			if (response) {
				if (response._LOG) {
					console.log("APP '"+self.ref+"' says:");
					console.log("\t"+response._LOG.split("\n").join("\n\t"));
				}
				if (response.uid) self.setUid(response.uid);
				if (response.manifest) {
					self.manifest = response.manifest;
					self.title = response.manifest.name;

					var mParams = ["noClose","singleInstance","startWidth","startHeight"];
					for (var i = 0; i < mParams.length; i++) {
						self[mParams[i]] = response.manifest[mParams[i]];
					}
					//if (response.manifest.noClose) self.noClose = true;
					//if (response.manifest.singleInstance) self.singleInstance = true;
				}
				self.parseResponse("main",response);
				if (!response.success) {
					self.core.removeApp(self);
					self.error("Failed to open app '"+self.ref+"'",response);
					return;
				}
			}
			self.show();
		});
	};
	
	self.parseResponse = function(module,response,moduleNum) {
		var js = false, needsPerm = [];
		if (response.r) {
			for (var i = 0; i < response.r.length; i++) {
				var r = response.r[i];
				switch (r.type) {
					case "needsPemission":
						if (r.perm == "user") {
							self.core.openUponLogin(self.ref,self.args);
						} else {
							needsPerm.push(r.perm);
						}
						break;
						
					case "js":
						js = r.js;
						break;
					
					case "html":
						if (module == "main") {
							self.html = r.html.split(" id=").join(" data-slid=");
						} else {
							self.modules[moduleNum].html = r.html.split(" id=").join(" data-slid=");
						}
						break;
						
					case "icon":
						self.icon = r.file;
						break;
				}
			}

			if (needsPerm.length) {
				alert(sl.format("en-us|You do not have permission to open this app.\n\nNeeds Permission(s): %%\nApp: %%",needsPerm.join(", "),self.ref));
			} else if (js) {
				var jsEl = document.createElement("script");
				jsEl.type = "text/javascript";

				jsEl.src = sl.parseLink((js.charAt(0) == "/" ? "app"+js : "app/"+self.ref+"/js/"+js)+(js.indexOf("?")==-1?"?":"&")+"aid="+self.handle+(moduleNum!=undefined?"&mn="+moduleNum:""));
			
				if (module == "main") {
					sl.onScriptLoad(jsEl,function() {
						self.core.dispatchEvent("app-open",self);
						if (!self.requireLoading) setAsLoaded();
					});
				}
				document.body.appendChild(jsEl);
				self.jsEls.push(jsEl);
				if (module != "main") {
					sl.onScriptLoad(jsEl,function() {
						self.modules[moduleNum].init();
						self.modules[moduleNum].dispatchEvent("appeared");
						self.lastModule = self.modules[moduleNum];
					});					
				}
			} else {
				self.core.dispatchEvent("app-open",self);
			}
		}	
	};
	
	//View
	self.createView = function(o) {
		o.app = self;
		if (self.startWidth) o.width = self.startWidth;
		if (self.startHeight) o.height = self.startHeight;

		if (!o.title) o.title = self.manifest.name;
		
		if (self.icon && !o.icon) o.icon = self.icon;
		if (self.html) o.html = self.html;
		o.startHidden = true;
		self.view = self.core.createView(o);
		if (!self.mainView) {
			self.mainView = self.view;
			self.view.isMainView = true;
		}
		self.views.push(self.view);
		self.appBarItem = self.view.registerAppBar();
		self.core.setActiveView(self.view);
		self.show();
		self.core.dispatchEvent("app-update",self);
	};
	
	self.show = function() {
		if (!self.view || !self.isInit) return;
		self.view.show();
	};
	
	//Navigate
	self.navigate = function(moduleName) {
		if (self.lastModule) self.lastModule.dispatchEvent("disappeared");
		for (var i = 0; i < self.modules.length; i++) {
			if (self.modules[i].name == moduleName) {
				self.view.navigateTo(self.modules[i].el);
				self.modules[i].dispatchEvent("appeared");
				self.lastModule = self.modules[i];
				return;
			}
		}
		self.addModule(moduleName,null,function(moduleNum,module,response) {
				module.setEl(self.view.addToNav(""));
				self.parseResponse(moduleName,response,moduleNum);
				self.view.navigateTo(moduleNum);
		});
	};
	
	self.setConnected = function(view) {
		self.connected = view;
		view.addConnectedApp(self);
	};
	
	self.addModule = function(moduleName,contEl,cb) {
		var module = new sl.appModule({"name":moduleName,"parentView":self.view,"core":self.core});
		self.modules.push(module);
		(function(moduleNum){
			self.core.net.send("app-module",{"uid":self.uid,"module":moduleName},{"queueTime":0},function(response){
				if (response && response.success) {
					if (cb) {
						cb(moduleNum,module,response);
					} else {
						module.setEl(contEl);
						self.parseResponse(moduleName,response,moduleNum);
					}
				} else {
					var i;
					if ((i = self.modules.indexOf(module)) != -1) {
						self.modules.splice(i,1);
					}
					console.log("App module '"+moduleName+"' not found.");
				}
				
			});
		})(self.modules.length - 1);
		return module;
	};
	
	self.destruct = function() {
		self.dispatchEvent("destruct");
		
		var view;
		while (view = self.views.pop()) {
			view.destruct();
		}
		
		var modules;
		while (module = self.modules.pop()) {
			module.destruct();
		}
		
		var listener;
		while (listener = self.listeners.pop()) {
			self.core.net.removeEventListener(listener);
		}
		
		while (listener = self.serverListeners.pop()) {
			self.removeServerListener(listener);
		}
		
		self.core.net.send("app-req",{"request":"destruct","uid":self.uid},{},function(r){
			while (el = self.jsEls.pop()) {
				el.parentNode.removeChild(el);
			}
			self.core.removeApp(self);
		});
	};
	
	self.addServerListener = function(event,cb) {
		var listener = self.core.net.addServerListener(event,cb);
		self.serverListeners.push(listener);
		return listener;
	};
	
	self.removeServerListener = function(listener) {
		var i;
		if ((i = self.serverListeners.indexOf(listener)) != -1) {
			self.serverListeners.splice(i,1);
		}
		self.core.net.removeServerListener(listener);
	};
	
	self.listen = function(pattern,cb) {
		self.listeners.push(self.core.net.addEventListener(pattern,cb));		
	};
		
	self.request = function(type,params,cb,loaderElement) {
		if (loaderElement === true) loaderElement = self.view.elInner;
		var lo = loaderElement ? new sl.loadingOverlay({"el":loaderElement}) : false;

		self.core.net.send("app-req",{"request":type,"params":params,"uid":self.uid},{"queueTime":0},function(r){
			if (lo) {
				lo.loaded();
				lo.destruct();
			}
			if (cb) cb(r && r.success ? r.result : false);
		});
	};
	
	self.requestBinary = function(type,params,cb,loaderElement) {
		var lo = loaderElement ? new sl.loadingOverlay({"el":loaderElement}) : false;
		self.core.net.requestBinary("app-req",{"request":type,"params":params,"uid":self.uid},function(r){
			if (lo) {
				lo.loaded();
				lo.destruct();
			}
			if (cb) cb(r && r.success ? r.result : false);
		});
	};

	self.require = function(sources,cb) {
		if (typeof(sources) != "object") sources = [sources];
		for (var i = 0; i < sources.length; i++) {
			sources[i] = "app/"+self.ref+"/"+sources[i];
		}
		self.requireLoading++;
		sl.require(sources,function(){
			self.requireLoading--;
			if (cb) cb();
			if (self.requireLoading == 0 && !self.isLoaded) setAsLoaded();
		});
	};
	
	self.loadAsData = function(scripts,cb,params) {
		if (typeof(scripts) != "object") scripts = [scripts];
		for (var i = 0; i < scripts.length; i++) {
			scripts[i] = "app/"+self.ref+"/"+scripts[i];
		}
		self.requireLoading++;
		sl.loadAsData(scripts,function(data){
			self.requireLoading--;
			if (cb) cb(data);
			if (self.requireLoading == 0 && !self.isLoaded) setAsLoaded();
		},params);
	};
	
	self.setValues({
		"jsEls":[],
		"uid":false,
		"isInit":false,
		"isLoaded":false,
		"requireLoading":0,
		"core":null,
		"listeners":[],
		"serverListeners":[],
		"ref":"",
		"view":null,
		"lastModule":null,
		"mainView":null,
		"views":[],
		"modules":[],
		"icon":false,
		"noClose":false,
		"singleInstance":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
}

