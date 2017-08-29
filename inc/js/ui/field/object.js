sl.fieldDef.object = {
	"init":function() {
		var self = this;
		this.addEventListener("select",function(t,m){
			self.applyValue(m.value+";"+m.name);
		});
		return true;
	},
	"postInit":function() {
		var self = this;
		if (!this.el.parentNode) return;
		
		if (!self.noIcon) {
			this.icon = new sl.icon({"core":this.core});
		
			this.el.parentNode.appendChild(this.icon.el);
		}
		this.defWidth = this.el.offsetWidth - sl.getTotalElementSize(this.el,true).width - 24;
		
		if (!self.noIcon) {
			var scanIcon = sl.dg("",this.el.parentNode,"div");
			self.view.core.setCommonIcon(scanIcon,"scan");
			scanIcon.addEventListener("click",function(){
				var app = self.view.openConnectedApp("scanner");
				app.addEventListener("scan",function(t,o){
					if (o.ref) {					
						if (o.ref != self.ref) {
							app.setScanError(sl.format("en-us|Incompatible object type (%%)",o.ref));
						} else {
							self.applyValue(o.data._UNIQUE+";"+o.data._NAME);
							app.destruct();
						}
					} else app.setScanError("en-us|Scanned item is not a YibblePoint object.");
				});
			});
		}
	
		function otherSelect() {
			var f = sl.dg("",self.contEl,"button",{"style":{"float":"left","marginRight":"8px"},"innerHTML":"en-us|Pick..."});
			f.addEventListener("click",function(){
				var app = self.view.openConnectedApp("view/?"+self.ref+"&select");
				app.addEventListener("selected",function(t,o){
					self.applyValue(o._UNIQUE+";"+o._NAME);
				});
			});
		};
		
		if (this.popularSelect) {
			self.view.app.request("varGet",["popular-ob-inc",self.ref+"-"+(self.parent?self.parent.n:self.n)],function(r){
				if (r) {
					var l = [];
					for (var i in r) {
						l.push([i,r[i]]);
					}
					l.sort(function(a,b){
						return b[1] - a[1];
					});
					for (var i = 0; i < Math.min(typeof(this.popularSelect)=="number"?this.popularSelect:5,l.length); i++) {
						var f = sl.dg("",self.contEl,"button",{"style":{"float":"left","marginRight":"8px"},"innerHTML":l[i][0].split(";").pop()});
						f.addEventListener("click",function(){
							self.applyValue(this.fv);
						});
						f.fv = l[i][0];
					}
				}
				if (self.otherSelect) otherSelect();
			});
		} else if (self.otherSelect) otherSelect();
	},
	"setValue":function(value) {
		//console.log(this.popularSelect);
		var self = this;

		var s = typeof(value) == "string" ? value.split(";",2) : [""];
		
		if (s.length == 2) {
			if (!self.noIcon) self.icon.setSource({"request":"item","params":{"ref":this.ref,"id":s[0]}});
			if (this.popularSelect && self.popLast !== undefined && self.popLast != value) {
					if (self.view) {
					var ref = self.ref+"-"+(self.parent?self.parent.n:self.n);
					self.view.app.request("varGet",["popular-ob-inc",ref],function(r){
						if (r === null) r = {};
						if (r[value] === undefined) r[value] = 0;
						r[value] ++;
						self.view.app.request("varSet",["popular-ob-inc",ref,r]);
					});
				}
			}
			self.popLast = value;
		} else self.popLast = false;
		
		if (!self.noIcon) {
			self.icon.el.style.display = s.length == 2 ? "" : "none";

			self.el.style.width = (self.defWidth - (s.length == 2 ? sl.getTotalElementSize(self.icon.el).width : 0))+"px";
		}
	},
	"blur":function() {
		if (this.suggestionClick()) return;
		
		var self = this;
		self.startWait();
		
		function go() {
			if (self.def.checking) {
				setTimeout(go,50);
				return;
			}
			value = self.value.value;
			if (value.trim() == "") return;
			if (value.split(";").length == 1) {
				self.core.net.send("item-info",{"ref":self.ref},{},function(item){					
					if (item.info && !self.dontCreateNew) {
						new sl.messageBox({
							"element":self.el,
							"message":sl.format("en-us|The %% '%%' does not exist, would you like to create it?",item.info.singleName,value),
							"core":self.core,
							"view":self.listener && self.listener.view ? self.listener.view : null,
							"choices":{"yes":"en-us|Yes, create it","no":"en-us|No"},
							"callback":function(choice){
								if (choice == "yes") {
									self.core.net.send("create-item",{"ref":self.ref,"data":{"_NAME":value},"options":{"returnUnique":1}},{},function(res){
										if (res.id) {
											self.applyValue(res.id+";"+value);
											new sl.messageBox({
												"element":self.el,
												"message":sl.format("en-us|%% '%%' has been created. Would you like to edit the details?",item.info.singleName,value),
												"core":self.core,
												"view":self.listener.view,
												"choices":{"yes":"en-us|Yes, edit it","no":"en-us|No"},
												"callback":function(choice){
													if (choice == "yes") {
														self.core.open("edit/?"+self.ref+"&"+res.id);
													}
													self.endWait();
												}
											});
										}
									});
								} else {
									self.applyValue(value);
									self.endWait();
								}
							}
						});
					} else self.endWait();
				});
			}
		};
		go();			
	},
	"change":function(text) {
		text = text.trim();
		if (text.length < 2) return;
		var self = this;
		if (!self.def.checking) {
			self.def.checking = true;
			self.core.net.send("search",{"ref":self.ref,"text":text},{},function(res){
				self.def.checking = false;
				self.clearSuggestions();
				if (res.matches) {
					for (var i = 0; i < res.matches.length; i++) {
						var m = res.matches[i], v = m._UNIQUE ? m._UNIQUE : m._KEY;
						if (m._NAME.safeName() == text.safeName()) {
							self.setValue(v+";"+m._NAME);
						} else {
							if (self.focus) {
								self.addSuggestion({
									"value":v,
									"name":m._NAME,
									"formatted":sl.highlightMatch(m._NAME,text)
								});
							}
						}
					}
				}
			});
		}
	}
};

