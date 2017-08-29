sl.initSlClass = function(self,name,options) {
	self.className = name;
	self.classSubName = options && options.subName ? options.subName : false;
	self._destructStack = [];
	self.chainDestruct = function(o) {
		self._destructStack.push(o);
	};
	
	self._destruct = function() {
		var o;
		while (o = self._destructStack.pop()) {
			o._destruct();
		}
		if (self._destruct != self.destruct) self.destruct();
	};	
	self.destruct = self._destruct;
	
	if (!options) options = {};
	
	if (!options.noHandle) {
		if (!global.handles[name]) global.handles[name] = [];
		self.handle = global.handles[name].length;
	
		global.handles[name].push(self);
	}
	
	self.makeUID = function(id) {
		return name+"-"+self.handle+"-"+id;
	};
	
	self.getAllInstances = function() {
		var rv = [];
		for (var i = 0; i < global.handles[name].length; i++) {
			var instance = global.handles[name][i];
			if (instance) rv.push(instance);
		}
		return rv;
	};
	
	self.setValues = function(o) {
		for (i in o) {
			var n = "set" + i.charAt(0).toUpperCase() + i.substr(1);
			if (self[n] && typeof(self[n]) == "function" && n != "setValues") { // Has a setter
				self[n](o[i]);
			} else {
				if (self.debug) console.log(i," = ",o[i]);
				self[i] = o[i];
			}
		}
	};
	
	self.set = function(o,v) {
		if (typeof(o) == "string") {
			var s = {};
			s[o] = v;
			self.setValues(s);
		} else if (typeof(o) == "object") {
			self.setValues(o);
		}
	};
		
	self.addEventListener = function(type,callback,first) {
		var id = "n"+self.eventListenerCnt;
		self.eventListener[id] = {
			"type":type,
			"f":callback,
			"lgClass":self,
			"first":!!first
		};
		self.eventListenerCnt++;
		return id;
	};
	
	self.removeEventListener = function(id) {
		if (!self.eventListener[id]) return;
		var type = self.eventListener[id].type;
		delete self.eventListener[id];
	};
	
	self.dispatchEvent = function(type,data,p2) {
		var e = [];
		for (var n in self.eventListener) {
			e.push(self.eventListener[n]);
		}
		
		e.sort(function(a,b){
			return (b.first?1:0)-(a.first?1:0);
		});
		
		for (var i = 0; i < e.length; i++) {
			var r = new RegExp((e[i].pureRegEx ? e[i].type : "^"+e[i].type.split("*").join(".*").split("?").join(".")+"$"),"gi");
			if (type.search(r) != -1) {
				var rv;
				if ((rv = e[i].f(type,data,p2)) !== undefined) return rv;
			}
		}
	};
	
	self.error = function() {
		if (!window.console) return;
		var p = [self.className+" (error):"];
		for (var i = 0; i < arguments.length; i++) {
			if (arguments[i] !== undefined) {
				p.push(sl.OS == "Android" ? sl.jsonEncode(arguments[i]).substr(0,200) : arguments[i]);
			} else break;
		}
		if (sl.OS == "Android") {
			console.log(p.join("\n\t"))+"\n\t";
			return;
		}
		try {
			console.log.apply(console,p);
		} catch (e) {
			console.log(p.join("\n\t"));
		}
	};
	
	self.log = function() {
		if (!window.console) return;
		var p = [self.className+(self.classSubName?"."+self.classSubName:"")+":"];
		for (var i = 0; i < arguments.length; i++) {
			if (arguments[i] !== undefined) {
				p.push(sl.OS == "Android" ? sl.jsonEncode(arguments[i]).substr(0,200) : arguments[i]);
			} else break;
		}
		if (sl.OS == "Android") {
			console.log(p.join("\n\t"))+"\n\t";
			return;
		}
		try {
			console.log.apply(console,p);
		} catch (e) {
			console.log(p.join("\n\t"));
		}
	};
	
	self.pipeEvent = function(t,ob) {
		if (typeof(t) == "string") t = [t];
		for (var i = 0; i < t.length; i++) {
			self.addEventListener(t[i],function(t,o){
				ob.dispatchEvent(t,o);
			});
		}
	};
	
	self.setEventListeners = function(o) {
		while (l = o.pop()) {
			self.addEventListener(l.type,l.callback);
		}		
	};
		
	// Progressive calls
	if (options.progressiveCall) {
		self.progressiveCallCount = 0;
		self.progressiveCallStack = [];
		
		self.progressiveCall = function(func) {
			if (self.progressiveCallCount) {
				self.progressiveCallStack.push(func);
			} else {
				func();
			}
			self.progressiveCallCount ++;
		};
		
		self.progressiveCallComplete = function() {
			self.progressiveCallCount --;
			if (self.progressiveCallStack.length > 0) {
				var func = self.progressiveCallStack.shift();
				func();
				return false;
			}
			return true;
		};
		
		self.progressiveCallInProgress = function() {
			return self.progressiveCallCount > 0;
		};
		
		self.async = null;
		self.beginAsync = function(name) {
			if (self.async === null || self.async.cancel) {
				self.async = {
					"name":name?name:"Processing",
					"self":self,
					"start":sl.unixTS(true),
					"lastProgress":sl.unixTS(true),
					"pass":0,
					"rate":[],
					"rateAvg":0,
					"complete":0,
					"remaining":0,
					"estSecondsRemaining":0,
					"cancel":false
				};
				return self.async;
			}
			return false;
		};
		
		self.endAsync = function() {
			if (self.async !== null) {
				self.async = null;
				self.dispatchEvent("async-complete",self.async);
				return true;
			}
			return false;
		};
		
		self.cancelAsync = function() {
			if (self.async !== null) self.async.cancel = true;
		};
		
		self.progress = function(prog) {
			if (typeof(prog[0]) != "object") prog = [prog];
			var pos = 0, tot = 1;
			for (var i = prog.length - 1; i >= 0; i--) {
				pos += prog[i][0] * tot;
				tot *= prog[i][1];
			}
			self.async.complete = pos / tot;
			self.async.remaining = tot - pos;
			self.async.pass ++;
			if (self.async.pass > 2) {
				self.async.rate.push(sl.unixTS(true) - self.async.lastProgress);
				if (self.async.rate.length > 50) self.async.rate.shift();
				self.async.rateAvg = 0;
				for (var i = 0; i < self.async.rate.length; i++) {
					self.async.rateAvg += self.async.rate[i];
				}
				self.async.rateAvg /= self.async.rate.length;
				
				self.async.estSecondsRemaining = self.async.rateAvg * self.async.remaining;
			}
			self.async.lastProgress = sl.unixTS(true);
		
			self.dispatchEvent("async-progress",self.async);
		};
	}
	
	//Raw connections
	if (options.rawConnect) {
		self.rawConnect = function(o,cb,extra) {			
			for (var i = 0; i < self.rawConnections.length; i++) {
				if (self.rawConnections[i] && extra.connection.sessionID == self.rawConnections[i].sessionID) {
					self.rawConnections[i].connection = extra.connection;
					return self.rawConnections[i].handle;
				}
			}
			
			o.sessionID = extra.connection.sessionID;
			o.connection = extra.connection;
			
			for (var i = 0; i < self.rawConnections.length; i++) {
				if (!self.rawConnections[i]) {
					self.rawConnections[i] = extra.comm.newRawConnectionServer(o);
					self._initRawConnection(self.rawConnections[i]);
					self.rawConnections[i].receive(["open",o.connection.open]);
					return self.rawConnections[i].handle;
				}
			}
			
			var rc = extra.comm.newRawConnectionServer(o);
			self.rawConnections.push(rc);	
			self._initRawConnection(rc);
			rc.receive(["open",o.connection.open]);
			return rc.handle;
		};
		
		self._initRawConnection = function(rc) {
			rc.open = rc.connection.open;
			if (self.initRawConnection) self.initRawConnection(rc);
		};
	}
				
	self.eventListener = {};
	self.eventListenerCnt = 0;
};
