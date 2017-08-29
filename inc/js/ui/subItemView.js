sl.subItemView = function(o) {
	var self = this;
	sl.initSlClass(this,"sub-item-view");

	self.setTable = function(table) {
		self.table = table;
		self.init();
	};
	
	self.setFilter = function(filter) {
		self.filter = filter;
		self.init();
	};
	
	var reloadTimer = null;
	function queueReload() {
		if (reloadTimer) clearTimeout(reloadTimer);
		if (self.hold) return;
		reloadTimer = setTimeout(self.scroller.reload,10);
	};
	
	function useField(field) {
		return !field.import && !(field.viewable != undefined && field.viewable === false);
	};
	
	var objectCount = 0;
	function setObjectCount(cnt) {
		objectCount = cnt;
		self.scroller.setItemCount(cnt);
	};
	
	function getObjectCount(cb) {
		self.request("subObjectCount",[],function(cnt){
			setObjectCount(cnt);
			if (cb) cb();
		});
	};
	
	function setFieldValue(scrollerItem,n,v) {
		if (self.info.sourceMap[n]) {
			for (var i = 0; i < self.info.sourceMap[n].length; i++) {
				setFieldValue(scrollerItem,self.info.sourceMap[n][i],v);
			}
		}
		if (!self.info.fields[n] || !useField(self.info.fields[n])) return;
		if ((self.info.fields[n].type || self.info.fields[n].multi) && scrollerItem.element(n).slValue) {
			scrollerItem.element(n).slValue.setValue(v);
		} else {
			scrollerItem.element(n).innerHTML = v;
		}
	};
	
	self.request = function(type,params,cb,loaderElement) {
		params.unshift(self.filter);
		params.unshift(self.table);
		self.app.request(type,params,cb,loaderElement);
	};
	
	self.init = function() {
		if (!self.table || !self.filter || self.isInit) return;
		
		if (self.parentEl) {
			var sh = sl.dg("",self.parentEl,"div",{"className":"scroller-head"});
			sl.dg("",sh,"div");
			
			var scrollerEl = sl.dg("",self.parentEl,"div",{"className":"scroller"});
		
			sl.dg("",scrollerEl,"div",{"className":"scroller-row"});
			//self.scroller.noItemsOverlayEl = 
			sl.dg("",scrollerEl,"div",{"className":"no-items","innerHTML":"en-us|There %2% currently no %1%."});
			
			self.scroller = new sl.scroller({"el":scrollerEl,"view":self.app.view});
		}
		
		self.isInit = true;
		
		self.request("getSubItemInfo",[],function(info){					
			self.info = info;

			self.scroller.noItemsOverlayEl.innerHTML = sl.format(self.scroller.noItemsOverlayEl.innerHTML,info.name,info.linkingVerb);
			self.scroller.orderby = info.orderby;
			
			self.scroller.refreshTotalRow = function(cb) {
				self.request("totalRow",[],cb);
			};

			self.scroller.indirectSize = true;
			self.scroller.clickableHead = true;
			self.scroller.clickableRows = true;
			self.scroller.sortOrderHead = true;

			self.scroller.addColumn("delete",{"label":"X","type":"delete"});

			for (var n in info.fields) {
				var field = info.fields[n];
				if (useField(field)) self.scroller.addColumn(n,field);
			};
			
			if (!self.noNew) {
				var el = self.scroller.addBottomElement();
				var newEl = sl.dg("",el,"button",{
					"innerHTML":"en-us|+ New",
					"style":{"zIndex":200}
				});
				newEl.addEventListener("click",self.newItem);
			}
			
			self.scroller.init();
			
			self.refreshListener = self.app.addServerListener("refresh-"+self.table,function(res){
				queueReload();
			});

			self.addEventListener("destruct",function() {
				self.app.removeServerListener(self.refreshListener);
			});
	
			getObjectCount();
	
			self.scroller.requestObjectCount = getObjectCount;
			
			self.scroller.requestSections = function() {
				self.request("sections",[],function(r){
					self.scroller.setSections(r);
				});
			};
				
			self.scroller.addEventListener("head-click",function(type,o){
				self.request("orderby",[o.id,o.order],function(cnt){
					setObjectCount(cnt);
					queueReload();
				},self.scroller.el);
			});
			
			self.scroller.addEventListener("click",function(type,o){
				self.app.core.open((self.info.customEdit?self.info.customEdit:"edit")+"/?"+self.info.table+"&"+o.item.getExtra("id"));
			});
			
			self.scroller.addEventListener("deactivated",function(t,scrollerItem){
				if (scrollerItem.serverListener) {
					self.app.removeServerListener(scrollerItem.serverListener);
					scrollerItem.serverListener = null;
				}
			});
			
			var reindexing = false;
			self.scroller.requestItem = function(itemIndex) {
				var scrollerItem = this, item = {};
				scrollerItem.loadingMessage((function(){var rv=[];for(var i in self.info.fields){rv.push(i);}return rv;})());
				self.request("item",[itemIndex],function(r){
					if (r === false) {
						if (reindexing) return;
						reindexing = true;
						self.request("reindex",[],function(cnt){
							reindexing = false;
							setObjectCount(cnt);
							queueReload();
						});
						return;
					} 
					
					if (!scrollerItem.delEl) {
						scrollerItem.delEl = sl.dg("",scrollerItem.element("delete"),"div",{
							"style":{
								"float":"left"		
							},
							"title":"en-us|Delete"
						});
						self.app.core.setCommonIcon(scrollerItem.delEl,"delete");
						(function(scrollerItem){
							scrollerItem.delEl.addEventListener("click",function(e){
								self.app.view.elementPrompt(scrollerItem.delEl,sl.format("en-us|Are you sure you want to delete this %%?", self.info.singleName),{"yes":"en-us|Yes, delete it","no":"en-us|No"},function(choice){
									if (choice == "yes") {
										self.request("delete",[scrollerItem.getExtra("id")],function(r){
											queueReload();
										});
									}
								});		
								sl.cancelBubble(e);
								return false;
							},true);
						})(scrollerItem);
					}
			
					function checkDeleteCondition() {
						var enableDelete;
						eval("enableDelete = "+self.deleteCondition);
						scrollerItem.delEl.style.display = enableDelete ? "block" : "none";
					};
					
					if (r[self.info.key]) {
						scrollerItem.serverListener = self.app.addServerListener("change-"+self.info.table+"/"+r[self.info.key],function(res){
							if (res == "DELETE") {
								queueReload();
							} else {
								for (var n in res) {
									setFieldValue(scrollerItem,n,res[n]);
									item[n] = res[n];
								}
								checkDeleteCondition();
							}
						});
					}
					
					scrollerItem.setExtra("id",r[self.info.key]);
					for (var n in self.info.fields) {
						setFieldValue(scrollerItem,n,self.info.fields[n].type == "extra" ? r : r[n])
						item[n] = self.info.fields[n].type == "extra" ? r : r[n];
					}
					scrollerItem.setAsLoaded();
					checkDeleteCondition();
				});
			};
	
		});
	};
	
	self.newItem = function() {
		var defaults = {}, og, n, i;
		if (og = self.app.info.setup.optionGroup) {
			defaults[og.parent] = self.app.info.data._KEY;
			for (i = 0; i < og.defaultFields.length; i++) {
				n = og.defaultFields[i];
				defaults[n] = self.app.info.data[n];				
			}
		}
		if (self.filter) {
			for (n in self.filter) {
				defaults[n] = self.filter[n];
			}
		}
		
		self.app.view.core.open((self.info.customEdit?self.info.customEdit:"edit")+"/?"+sl.refEncode([self.table,"NEW",defaults]));
	};
					
	self.setValues({
		"table":"",
		"filter":null,
		"isInit":false,
		"hold":false,
		"noNew":false
	});
	
	if (o) self.setValues(o);
};
