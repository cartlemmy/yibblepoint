sl.net = function(o) {
	var self = this;
		
	sl.initSlClass(this,"net");
	
	self.setPollFrequency = function(pollFrequency) {
		if (pollFrequency < 0.5) {
			self.error("pollFrequency must be at least 0.5 seconds.");		
		} else {
			self.pollFrequency = pollFrequency;
		}
	};
	
	self.forcePollFrequency = function(pf) {
		self.forcedPollFrequency = pf;
	};
	
	self.setConnected = function(connected) {
		if (connected != self.connected) {
			if (connected) {
				if (self.disconnectedNI) {
					self.core.removeNotification(self.disconnectedNI);
					self.disconnectedNI = null;
				}
			} else {
				if (self.core) self.disconnectedNI = self.core.addNotification({"name":"Disconnected","message":"Disconnected","icon":"disconnected"});
			}
			self.dispatchEvent(connected ? "connected" : "disconnected");
		}
		self.connected = connected;
	};
	
	self.connect = function() {
		//self.serializer = new sl.serializer();
		
		self.send("connect",{"pollFrequency":self.pollFrequency},{"queueTime":0},function(response){
			if (response.success) {
				self.uniqueID = response.uid;
				self.setConnected(true);
				self.initialConnect = true;
				self.dispatchEvent("comm-open",response);
			} else {
				self.log(response);
				self.dispatchEvent(response.type ? "comm-"+response.type : "comm-error",response);
				//TODO: failed!!!
			}
		});
	};
	
	self.send = function(action,data,options,callback) {
		if (self.httpLastSendWithData != 0) {
			self.requestDistance.push(sl.unixTS(true) - self.httpLastSendWithData);
		}
		self.httpLastSendWithData = sl.unixTS(true);
		if (self.requestDistance.length > 10) { self.requestDistance.shift(); }
	
		self.getResponseDistanceAverage(true);
		
		if (self.httpQueueTimeout && !self.mustSendNext) { clearTimeout(self.httpQueueTimeout); }
		
		if (!options) options = {};
		
		if (!options.queueTime) options.queueTime = 25;
				
		if (callback) {
			self.httpQueue.push({"action":action,"data":data,"options":options,"callback":callback,"hook": self.getAvailableHook()});
		} else {
			self.httpQueue.push({"action":action,"data":data,"options":options});
		}
	
		if (options && options.blocking) {
			self.httpSend();
		} else {
			if (!self.mustSendNext) self.httpQueueTimeout = setTimeout(self.httpSend,options.queueTime === 0 ? 5 : options.queueTime);
			if (options.queueTime === 0 || sl.unixTS(true) > self.httpLastSend + self.pollFrequency) {
				self.mustSendNext = true;
			} 
		}
	};
	
	self.getAvailableHook = function() {
		for (var i = 0; i < self.hookMap.length; i++) {
			if (self.hookMap[i] == false) {
				self.hookMap[i] = true;
				return i;
			}
		}
		self.hookMap.push(true);
		return self.hookMap.length - 1;
	};
	
	self.httpResponse = function(data) {
		if (data && data.length) {
			for (var i = 0; i < data.length; i++) {
				if (data[i]) {
					if (data[i].requestDistance) {
						self.requestDistance.push(data[i].requestDistance);
						self.getResponseDistanceAverage(true);
					} else if (data[i].hook !== undefined) {
						self.httpResponsesWithData++;
						found = false;
						for (var j = 0; j < self.httpQueue.length; j++) {
							if (data[i].hook === self.httpQueue[j].hook) {
								self.hookMap[self.httpQueue[j].hook] = false;
								found = true;
								break;
							}
						}
						if (found && self.httpQueue[j].callback) {
							self.httpQueue[j].ob = self;
							self.httpQueue[j].callback(data[i].response);
						}
						self.httpQueue.splice(j,1);
					} else if (data[i].event) {
						self.dispatchEvent(data[i].event,data[i].params);
					} else if (data[i].out) {
						self.out(data[i]);
					} else if (data[i].load) {
						var tot = data[i].load[0] + data[i].load[1];
						self.serverLoad = data[i].load[0] / tot;
						self.serverLoadHistory.push([self.serverLoad,tot]);
						if (self.serverLoadHistory.length > 100) self.serverLoadHistory.shift();
					} else if (data[i].error) {
						
						if (sl.view) {
							if (!self.errorView) {
								self.errorView = self.core.createView({
									"title":"Error",
									"icon":"error",
									"contentPadding":"8px"
								},true);
							}
							
							var view = self.errorView;
							//console.log(data[i]);

							view.elInner.innerHTML += "<b>"+data[i].error+"</b><br />";
					
						
							if (data[i].backtrace) {
								if (typeof(data[i].backtrace) == "string") {
									view.elInner.innerHTML += "<pre>"+data[i].backtrace+"</pre>";
								} else {
									for (var j = 0; j < data[i].backtrace.length; j++) {
										view.elInner.innerHTML += data[i].backtrace[j];
									}
								}
							} else if (data[i].text) {
								view.elInner.innerHTML += data[i].text;
							}
		
							view.elInner.innerHTML += "<br /><br />";
							
							view.initContent();
							view.center();
						} else {
							console.log(data[i].error);
							if (data[i].text) {
								console.log(data[i].text);
							}
						}
	
					} else if (data[i].debug) {
						console.log(data[i].debug);
					} else {
						console.log("Unknown Response Item: ",data[i]);
					}
				}
			}
		}
	};
	
	self.httpSend = function() {
		self.mustSendNext = false;
		if (self.pendingRequests <= 1) {
			self.httpLastSend = sl.unixTS(true);
			
			if (!self.initialConnect) {
				//Make sure connection request is first
				var c = null, c2 = null;
				for (var i in self.httpQueue) {
					if (self.httpQueue[i].action == "connect") {
						c = self.httpQueue.splice(i,1);
						break;
					}
				}
				if (c) {
					self.httpQueue.unshift(c[0]);
				} else {
					return;
				}
			}
			
			var blocking = false;
			
			var hq = [];
			if (self.debug) console.log("request "+self.requestCnt);
			for (var i = 0; i < self.httpQueue.length; i++) {
				if (!self.httpQueue[i].sent) {
					var h = self.httpQueue[i];
					if (h.options.blocking) blocking = true;
					if (self.debug) console.log("\t"+h.action);
					if (h.hook != undefined) {
						hq.push({
							"action":h.action,
							"data":h.data,
							"hook":h.hook
						});
					} else {
						hq.push({
							"action":h.action,
							"data":h.data
						});
					}
					self.httpQueue[i].sent = 1;
				}
			}
			
			if (self.connected) {
				var r;
				while (r = self.retry.shift()) {
					hq.push(r);
				}
			}
			
			self.httpRequest(hq,blocking);
			
		} else {
			//console.log("Waiting on ("+self.pendingRequests+") pending requests.");
			//console.log(self.httpQueue);
		}
		
		self.httpQueueTimeout = setTimeout(function(){
			self.httpSend();
		}, (self.forcedPollFrequency ? Math.max(0.5,self.forcedPollFrequency) : self.pollFrequency) * 1000);
	};
	
	self.httpRequest = function(req,blocking,cb) {
		var postData = escape(self.uniqueID+"\n"+sl.jsonEncode(req));
		//console.log(postData);
		var conn = self.newHTTPRequest();
		
		self.httpRequests++;
		self.getResponseDistanceAverage();
		
		function response(text) {
			//console.log(text);
			self.httpResponses++;
			self.recordResponsePerformance({"latency":sl.unixTS(true) - conn.start,"size":conn.size});
			
			if (cb) return cb(true,conn);
			
			var start = sl.unixTS(true);
			
			if ((text = text.trim()) && text.charAt(0) == "[" && text.charAt(text.length-1) == "]") {
				//try {
					var response = sl.jsonDecode(text);
					if (sl.unixTS(true) - start > 0.020) {
						console.log("LONG decode",sl.unixTS(true) - start);
					}
					if (response) {
						self.httpResponse(response);
						return;
					}
				//} catch (e) {
				//	console.log(e);
				//}
			}
			self.log("Not valid JSON: \n'"+text+"'");
		};
		
		function failed(disconnected) {
			clearTimeout(conn.timeoutTimer);
			self.pendingRequests --;
			if (disconnected) self.setConnected(false);
			if (cb) return cb(false,conn);
			
			if (req) {
				for (var i = 0; i < req.length; i++) {
					self.retry.push(req[i]);
				}
				req = null;
			}
		};
		
		conn.size = postData.length;
		conn.start = sl.unixTS(true);
		conn.requestNum = self.requestCnt;
		if (req[0] && req[0].binary) conn.responseType = "arraybuffer";
		
		conn.onreadystatechange = function() {
			switch (conn.readyState) {
				case 4:
					clearTimeout(conn.timeoutTimer);
					self.pendingRequests --;
					if (self.debug) console.log("response "+conn.requestNum,conn.status);
					switch (String(conn.status).substr(0,1)) {			
						case "0":
							failed(true);
							break;
												
						case "2":
							self.setConnected(true);
							response(conn.responseType == "arraybuffer"?false:conn.responseText);
							break;							
							
						default:
							self.error("httpSend response: "+conn.status);
							break;
					}
					break;
			}
		};

		conn.onprogress = function(e) {
			//console.log("progress",e);
		};
		
		conn.onerror = function(e) {
			failed(true);
		};

		conn.onabort = function(e) {
			console.log("abort",e);
		};
		
		self.pendingRequests ++;
		var url = (sl.config.isWeb?(sl.config.webRelRoot?sl.config.webRelRoot.replace('dev/',''):"")+sl.config.core.name+"/":(sl.config.core.fromAPI?sl.config.root:""))+self.url+(sl.config.sessionId?"?"+sl.config.sessionName+"="+sl.config.sessionId:"");
		//self.log(url);
		try {
			conn.open("POST", url, !blocking, self.user ? self.user : undefined, self.password ? self.password : undefined);
			conn.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

			conn.timeoutTimer = setTimeout(function() {
				conn.abort();
			},self.httpTimeout * 1000);
			
			conn.send(postData);
			
			if (blocking) response(conn.responseText);
			self.requestCnt++;
		} catch (e) {
			if (self.requestType < self.requestTypes.length) {
				self.log(e.message);
				self.log(self.requestTypes[self.requestType]+" failed, trying another");
				self.requestType ++;
				failed();
			} else {
				self.error("No possible request types were successful");
			}
		}
	};
	
	self.requestBinary = function(action,data,callback) {
		self.httpRequest([{
			"action":action,
			"data":data,
			"binary":true
		}],false,function(success,conn){
			callback({"success":success,"result":success?conn.response:false,"conn":conn});
		});
	};
	
	self.error = function(txt) {
		console.log(txt);
	};
	
	//Communcation
	self.newHTTPRequest = function() { 
		switch (self.requestType) {
			case 1:
				//return new sl.XDomainRequest(); Obsolete
			case 0:
				if (window.XMLHttpRequest) { 
					 return new XMLHttpRequest();
				} else if (window.ActiveXObject) {
					 return new ActiveXObject("Microsoft.XMLHTTP");
				}
				break;
		
			
		}
	};
	
	//Listener
	self.addServerListener = function(event,cb) {
		for (var i = 0; i < self.serverListeners.length; i++) {
			if (self.serverListeners[i].event == event) {
				self.serverListeners[i].cnt++;
				self.serverListeners[i].cb.push(cb);
				return event+"-"+(self.serverListeners[i].cb.length - 1);
			}
		}
		
		var listener = {"event":event,"cb":[cb],"def":null,"l":null,"cnt":1};
		self.serverListeners.push(listener);
		
		self.send("listener-add",{"event":event},{},function(response){
			if (response) {
				var lDef = listener.def;
				listener.def = response;
				if (lDef === false) {
					self.removeServerListener(event+"-0");
				} else {
					if (listener.def.id.indexOf(",") != -1) {
						listener.l = [];
						var ids = listener.def.id.getBetween("(",")").split(",");
						var user = listener.def.id.split(")").pop();
				
						for (var i = 0; i < ids.length; i++) {
							listener.l.push(self.addEventListener(ids[i]/*+user*/,function(type,o){
								for (var j = 0; j < listener.cb.length; j++) {
									if (listener.cb[j]) listener.cb[j](o);
								}
							}));
						}
					} else {
						var id = listener.def.id.replace("/"+sl.config.user,"");
						
						listener.l = self.addEventListener(id,function(type,o){
							for (var i = 0; i < listener.cb.length; i++) {
								if (listener.cb[i]) listener.cb[i](o);
							}
						});
					}
				}
			}
		});
		return event+"-0";
	};
	
	self.removeServerListener = function(ref) {
		if (!ref) return;
		if (typeof(ref) != "string") console.log(ref);
		var listener = null, event = ref.split("-");
		var cbNum = Number(event.pop());
		event = event.join("-");
		
		for (var i = 0; i < self.serverListeners.length; i++) {
			if (self.serverListeners[i].event == event) {
				listener = self.serverListeners[i];
				listener.cb[cbNum] = null;
				break;
			}
		}
		if (!listener) return;
		
		if (listener.cnt) {
			listener.cnt--;
			if (listener.cnt) return;
		}
		
		var i;
		if ((i = self.serverListeners.indexOf(listener)) != -1) {
			self.serverListeners.splice(i,1);
		}
		
		if (listener.l) {
			if (typeof(listener.l) == "object") {
				for (var i = 0; i < listener.l.length; i++) {
					self.removeEventListener(listener.l[i]);
				}
			} else {
				self.removeEventListener(listener.l);
			}
		}
		
		if (listener.def && listener.def.id) {
			self.send("listener-remove",{"id":listener.def.id},{},function(response){
				//console.log("listener-remove",response);
			});
		} else {
			listener.def = false;
		}
	};
	
	self.broadcastEvent = function(type,params) {
		self.send("broadcast-event",{"type":type,"params":params},{},function(res){
			
		});
	};
	
	self.sendEvent = function(user,type,params) {
		params.from = sl.config.user;
		self.send("send-event",{"user":user,"type":type,"params":params},{},function(res){
			
		});
	};
	
	//Load
	self.getAverageServerLoad = function() {
		if (!self.serverLoadHistory.length) return 0;
		var v = 0, tot = 0;
		for (var i = 0; i < self.serverLoadHistory.length; i++) {
			v += self.serverLoadHistory[i][0] * self.serverLoadHistory[i][1];
			tot += self.serverLoadHistory[i][1];
		}
		return v / tot;
	};
	
	//Timing 
	self.getResponseDistanceAverage = function(v) {
		if (v) {
			var n = 0;
			for (var i = 0; i < self.requestDistance.length; i ++) {
				n += self.requestDistance[i];
			}
			n = self.requestDistance.length ? n / self.requestDistance.length : -1;
		} else {
			var n = self.pollFrequency + 0.25;
		}
		self.setPollFrequency(Math.min(self.pollFrequencyMax,Math.max(self.pollFrequencyMin,n)));
	};
	
	self.getAverageLatency = function() {
		return self.responsePerformanceAverage.latency;
	}
	
	self.getAverageSize = function() {
		return self.responsePerformanceAverage.size;
	}
	
	self.recordResponsePerformance = function(data) {
		if (data.size) self.totalBytesTransfered += data.size;
		self.responsePerformance.push(data);
		if (this.responsePerformance.length > 60 / self.pollFrequency) {
			self.responsePerformance.shift();
		}
		var tot = {}, cnt = {};
		for (var i = 0; i < self.responsePerformance.length; i++) {
			for (var j in self.responsePerformance[i]) {
				if (tot[j]) {
					tot[j] += self.responsePerformance[i][j];
					cnt[j] ++;
				} else {
					tot[j] = self.responsePerformance[i][j];
					cnt[j] = 1;
				}
			}
		}
		
		for (var j in tot) {
			self.responsePerformanceAverage[j] = (tot[j] / cnt[j]);
		}		
	};

	
	self.setValues({
		"requestType":0,
		"requestTypes":["XMLHttpRequest","XDomainRequest","JSONP"],
		"initialConnect":false,
		"connected":false,
		"uniqueID":"new",
		"url":"r",
		"core":null,
		"connections":[],
		"requestCnt":0,
		"pendingRequests":0,
		"httpQueueTimeout":null,
		"httpLastSend":0,
		"httpLastSendWithData":0,
		"responsePerformance":[],
		"responsePerformanceAverage":{},
		"serverLoad":0,
		"serverLoadHistory":[],
		"mustSendNext":false,
		"forcedPollFrequency":false,
		"pollFrequency":2,
		"pollFrequencyMin":sl.config.net.pollFrequencyMin == undefined ? 1 :sl.config.net.pollFrequencyMin,
		"pollFrequencyMax":sl.config.core.fromAPI && sl.config.net.APIPollFrequencyMax !== undefined ?
			sl.config.net.APIPollFrequencyMax :
			(sl.config.net.pollFrequencyMax == undefined ? 5 :sl.config.net.pollFrequencyMax),
		"httpTimeout":sl.config.net.httpTimeout == undefined ? 30 :sl.config.net.httpTimeout,
		"requestDistance":[],
		"httpQueue":[],
		"retry":[],
		"hookMap":[],
		"serverListeners":[],
		"totalBytesTransfered":0
	});
	
	if (o) self.setValues(o);
};

