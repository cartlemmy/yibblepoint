self.request("setup",[],function(setup){
	self.hold = false;
	self.debug = true;
	
	self.createView({
		"title":setup.name,
		"icon":setup.icon?setup.icon:null,
		"contentPadding":"0px",
		"tools":["menu","search"],
		"noScroll":true
	});
	
	var mode = self.args.length >= 2 ? self.args[1] : "normal";
	var titleSet = [false,false], reCount = false, objectCount = 0;
	
	function isSearchableFieldType(type) {
		return ["currency"].indexOf(type) == -1;
	}
	
	function setTitle() {
		var t = [];
		
		switch (mode) {
			case "select":
				t.push(sl.format("en-us|Select %%",setup.singleName));
				break;
				
			default:
				t.push(setup.name);
				break;
		}
		
		for (var i = 0; i < arguments.length; i++) {
			if (arguments[i] !== null && arguments[i] !== undefined) titleSet[i] = arguments[i] ? arguments[i] : false;
		}
		
		for (var i = 0; i < titleSet.length; i++) {
			if (titleSet[i]) t.push(titleSet[i]);
		}

		self.view.setTitle(t.join(sl.config.sep)+(objectCount?" ("+objectCount+")":"")); 
	};
	
	setTitle(null,null);
	
	self.view.setContentFromHTMLFile();
	
	var scroller = self.view.element("view-scroller").slSpecial;
	
	scroller.noItemsOverlayEl.innerHTML = sl.format(scroller.noItemsOverlayEl.innerHTML,setup.name);
	scroller.orderby = setup.orderby;
	if (setup.useViewMode) scroller.setViewMode(setup.useViewMode);
	
	scroller.refreshTotalRow = function(cb) {
		self.request("totalRow",[],cb);
	};
	
	scroller.clickableHead = true;
	scroller.clickableRows = true;
	scroller.sortOrderHead = true;
	scroller.checkableRows = true;
	
	var labelMenu = [
		{"label":"en-us|QR (5159)","action":"export","params":["qr"]},
		{"label":"en-us|Barcode (5160)","action":"export","params":["barcode"]}
	];
	
	var doWithMenu = [];
	
	if (!setup.disableDelete) doWithMenu.push({"label":"en-us|Delete","action":"delete"});
	
	doWithMenu.push({"label":"en-us|Print Labels","children":labelMenu});
	
	var showMenu = [{"label":"en-us|All","action":"show","field":"ALL","value":"ALL"}];
	
	if (setup.queryFilters) {
		for (var n in setup.queryFilters) {
			var filter = setup.queryFilters[n];
			showMenu.push({"label":filter.label,"title":filter.label,"action":"show","field":"queryFilter","value":n});
		}
	}
	
	var searchByFields = {}, searchByFieldsText = {};
	function searchByField(field,icon) {
		function updateSearchTitle() {
			var p = [];
			for (var n in searchByFields) {
				if (searchByFields[n] !== undefined) {
					var f = setup.fields[n];
					p.push(f.label+" is '"+searchByFieldsText[n]+"'");
				}
			}
			if (p.length) {
				p = p.join("en-us| and ");
				setTitle(null, sl.format("en-us|Search - %%",p));
			} else {
				setTitle(false,false);
			}
			scroller.setNoItemsMessage(sl.format("en-us|There are no results for the search %%.",p));
		};
		
		function beginSearchField() {
			var lo = new sl.loadingOverlay({"el":scroller.el});
			
			updateSearchTitle();
			
			function doSearchField() {
				self.request("searchField",[searchByFields],function(cnt){
					if (typeof(cnt) == "object") { //Indexing
						lo.progress(cnt[0],cnt[1]);
						setTimeout(function(){doSearchField()},1000);
					} else {
						lo.loaded();
						lo.destruct();
						setObjectCount(cnt);
						queueReload(true);
					}
				});
			};
			doSearchField();
		};
		
		function showField(field,cont) {
			var o = {
				"core":self.core,
				"view":self.view,
				"contEl":cont,
				"value":'',
				"listener":self
			};
			
			for (var i in field) {
				o[i] = field[i];
			}
			
			if (o.readOnlyField) o.readOnlyField = null;
			if (o.readOnly) o.readOnly = null;
			if (o.multi) o.multi = null;
			o.dontCreateNew = true;
			
			sl.dg("",cont,"h3",{"innerHTML":sl.format("en-us|Search by '%%'",field.label)});
			
			return new sl.field(o);
		};
				
		if (searchByFields[field.n] !== undefined) {
			searchByFields[field.n] = undefined;
			icon.setSrc("themes/"+sl.config.core.theme+"/search-white.png");
			beginSearchField();
		} else {			
			if (!isSearchableFieldType(field.type)) return;
			
			var overlay = new sl.viewOverlay({"view":self.view});
			
			var cont = sl.dg("",overlay.elContent,"div",{"style":{"width":"300px"}});
			
			var fieldOb = [];
			switch (field.type) {
				case "date":
					var o = {};
					
					for (var n in field) {
						o[n] = field[n];
					}
					o.type = "dateRange";

					fieldOb.push(showField(o,cont));
					break;
					
				default:
					fieldOb.push(showField(field,cont));
					break;
			}
			
			var searchEl = sl.dg("",cont,"button",{"innerHTML":"en-us|Search","style":{"marginTop":"15px"}});
			searchEl.addEventListener("click",function(){
				var v, customText = false;
				
				switch (field.type) {
					case "date":
						customText = "en-us|between " + fieldOb[0].value.toString();
						var range = fieldOb[0].getValue().split("-");
						v = ["range",range[0]=="0"?null:Number(range[0]),range[1]=="0"?null:Number(range[1])];
						break;
						
					default:
						v = fieldOb[0].getValue();
						break;
				}
				
				if (customText) {
					searchByFieldsText[field.n] = customText;
				} else {
					searchByFieldsText[field.n] = fieldOb[0].value.toString(function(v){
						searchByFieldsText[field.n] = v;
						updateSearchTitle();
					});
				}
				
				searchByFields[field.n] = v;
				
				icon.setSrc("themes/"+sl.config.core.theme+"/rem-search-white.png");
				
				beginSearchField();
				overlay.destruct();
			});
				
			overlay.updateContentSize();
		}
	};
	
	for (var n in setup.fields) {
		var field = setup.fields[n];
		field.n = n;
		
		if (useField(field)) {
			if (isSearchableFieldType(field.type)) {
				(function(field){
					field.headIcons = [{
						"label":"en-us|Search...",
						"src":"themes/"+sl.config.core.theme+"/search-white.png",
						"click":function(o){
							searchByField(field, o);
						}
					}];
				})(field);
			}
			scroller.addColumn(n,field);
		}
		switch (field.type) {			
			case "group":
				var s = [["add-to","en-us|Add To Group"],["remove-from","en-us|Remove From Group"]];
				for (var i = 0; i < s.length; i++) {
					var c = [];	
					for (var j = 0; j < setup.topGroups.length; j++) {
						if (i == 0) showMenu.push({"label":setup.topGroups[j][2],"action":"show","field":"group","value":setup.topGroups[j][2]});
						c.push({"label":setup.topGroups[j][2],"action":"set","actionName":s[i][0],"field":field,"value":(s[i][0]=="add-to"?"+":"-")+setup.topGroups[j][2]});
					}
					c.push({"label":"en-us|Other...","action":"select","selectAction":s[i][0],"field":field});
					doWithMenu.push({"name":s[i][0]+"-group","label":s[i][1],"children":c});
				}
				break;
				
			case "object":
				if (field.multi) {
					var s = [["add-to","en-us|Add %%..."],["remove-from","en-us|Remove %%..."]];
					for (var i = 0; i < s.length; i++) {
						doWithMenu.push({"label":sl.format(s[i][1],field.label),"field":field,"action":"select","selectAction":s[i][0]});		
					}
				}
				break;
		}
		if (field.massChange) {
			doWithMenu.push({"label":sl.format("en-us|Set %% to...",field.label),"field":field,"action":"set"});
		}
	};
	
	//console.log(setup);
	
	var manage = [
		{"label":"en-us|Import...","action":"import"},
		{"label":"en-us|Export...","children":setup.exporters}/*,
		{"label":"en-us|Report...","action":"report"}*/
	];
	
	if (setup.groupField) {
		var f = setup.fields[setup.groupField];
		manage.push({"label":f.label+"...","action":"manage-groups"});
	}
	
	var menu = [
		{"label":"en-us|+ New","action":"new"},
		{"label":"en-us|Manage","children":manage}
	];
	
	if (showMenu.length > 1) {
		menu.push({"label":"en-us|Show","children":showMenu});
	}
	
	if (setup.reports) {
		var c = [], cnt = 0, rn;
		for (var n in setup.reports) {
			if (cnt == 0) rn = n;
			cnt++;
		}
		if (cnt > 1) {
			for (var n in setup.reports) {
				c.push({"label":setup.reports[n].name,"action":"report","report":n});
			}
			menu.push({"label":"en-us|Reports","children":c});
		} else {
			menu.push({"label":"en-us|Report","action":"report","report":rn});
		}
		
	}
	
	self.view.setMenu(menu);
	
	self.view.addEventListener("menu-click",function(type,o){
		switch (o.item.action) {
			case "new":
				self.core.open((setup.customEdit?setup.customEdit:"edit")+"/?"+self.args[0]+"&NEW");
				break;
				
			case "import":
				self.core.open("import/?"+self.args[0]);
				break;
			
			case "manage-groups":
				self.core.open("manage-groups/?"+self.args[0]);
				break;
				
			case "report":
				self.core.open("report/?"+sl.refEncode([self.args[0],o.item.report]));
				break;
				
			case "show":
				switch (o.item.field) {
					case "ALL":
						setTitle(false,false);
						break;
						
					case "group":
						setTitle(o.item.value);
						break;
						
					default:
						setTitle(o.item.title ? o.item.title : ((setup.fields[o.item.field] ? setup.fields[o.item.field].label : o.item.field)+": "+o.item.value));
						break;
				}
				
				var lo = new sl.loadingOverlay({"el":scroller.el});
				function doFilter(v) {
					scroller.setNoItemsMessage(v?"en-us|There are no results.":false);
					self.request("filter",v,function(cnt){
						if (typeof(cnt) == "object") { //Indexing
							lo.progress(cnt[0],cnt[1]);
							setTimeout(function(){doFilter(v)},1000);
						} else {
							lo.loaded();
							lo.destruct();
							setObjectCount(cnt);
							queueReload(true);
						}
					});
				};
				doFilter(o.item.value == "ALL"?["ALL"]:[o.item.field,o.item.value]);
				break;
			
			case "export":
				if (o.item.options.indexOf("field-selection") == -1) {
					self.request("export",[o.item.uid,true],function(res){
						self.core.action.apply(self.core,res.action);
					});
				} else {
					self.request("getExtraFields",[],function(res){
						var overlay = new sl.viewOverlay({"view":self.view});
						var cont = overlay.elContent, fs, label, cb, n,
							exportFields = {}, but;

						function doExport() {
							self.request("export",[o.item.uid,exportFields],function(res){
								self.core.action.apply(self.core,res.action);
								overlay.destruct();
							});
						};
						
						but = sl.dg("",cont,"button",{"innerHTML":"Export"});
						but.addEventListener("click",doExport);
						
						sl.dg("",cont,"h3",{"innerHTML":"Export Fields"});

						for (n in setup.exportFields) {
							exportFields[n] = true;
							(function(n){
								fs = sl.dg("",cont,"fieldset",{});
								label = sl.dg("",fs,"label",{});
								cb = sl.dg("",label,"input",{"type":"checkbox","checked":true});
								cb.addEventListener("change",function(){
									exportFields[n] = this.checked;
								});
								sl.dg("",label,"text",setup.exportFields[n].label);
							})(n);
						}
						
						for (n in res) {
							(function(n){
								exportFields[n] = false;
								fs = sl.dg("",cont,"fieldset",{});
								label = sl.dg("",fs,"label",{});
								cb = sl.dg("",label,"input",{"type":"checkbox"});
								cb.addEventListener("change",function(){
									exportFields[n] = this.checked;
								});
								sl.dg("",label,"text",n);
							})(n);
						}
						
						but = sl.dg("",cont,"button",{"innerHTML":"Export"});					
						but.addEventListener("click",doExport);
						
						overlay.updateContentSize();
					},true);
				}
				break;
		}
	});
		
	scroller.checkboxMenu = [
		{"label":"en-us|Check All","action":"check-all"},
		{"label":"en-us|Uncheck All","action":"uncheck-all"},
		{"label":"en-us|Check All In View","action":"check-all-in-view"},
		{"label":"en-us|Uncheck All In View","action":"uncheck-all-in-view"},
		"",
		{"label":"en-us|Do With Checked","children":doWithMenu},
	];
	
	scroller.init();

	var reloadTimer = null;
	function queueReload(nrc) {
		if (nrc) reCount = true;
		if (reloadTimer) clearTimeout(reloadTimer);
		if (self.hold) return;
		reloadTimer = setTimeout(function(){scroller.reload(!reCount);reCount=false;},10);
	};
	
	function useField(field) {
		return !field['import'] && !(field.viewable != undefined && field.viewable === false) && !(setup.optionGroup && setup.optionGroup.exclusiveFields && setup.optionGroup.exclusiveFields.indexOf(field.n) != -1);
	};
	
	function setObjectCount(cnt) {
		objectCount = cnt;
		scroller.setItemCount(cnt);
		setTitle();
	};
	
	function getObjectCount(cb) {
		self.request("objectCount",[],function(cnt){
			setObjectCount(cnt);
			if (cb) cb();
		});
	};

	getObjectCount();
	
	scroller.requestObjectCount = getObjectCount;
	
	scroller.requestSections = function() {
		self.request("sections",[],function(r){
			scroller.setSections(r);
		});
	};
		
	scroller.addEventListener("checkbox-menu",function(type,action,o,e){
		var areYouSure = false;
		var names = {
			"delete":["en-us|delete","en-us|deleting"],
			"add-to":["en-us|add to","en-us|adding to"],
			"set":["en-us|set","en-us|setting"],
			"remove-from":["en-us|remove from","en-us|removing from"],
		};
		
		function process2(ids,pos,extra) {
			self.hold = true;
			self.request("action",[action,o.params,ids,pos,extra],function(pos){
				var done = false;
				if (sl.typeOf(pos) == "object") {
					done = true;
					if (pos.action) {
						self.core.action(pos.action[0],pos.action[1]);
					}
				} else if (pos && pos[0]) {
					if (!lo) lo = new sl.loadingOverlay({"el":self.view.elInner});
					var n = o.actionName?o.actionName:action;
					lo.progress(pos[0],pos[1],names[n] ? names[n][1].ucFirst() + " "+setup.name+"..." : false);
					process2(null,pos[0],extra);
				} else done = true;
				
				if (done) {
					if (lo) {
						lo.loaded();
						lo.destruct();
						self.hold = false;
						queueReload();
					} else {
						self.hold = false;
						queueReload();
					}
				}
			});
		};
				
		switch (action) {
			case "check-all":
				scroller.checkAll(true);
				return;
			
			case "uncheck-all":
				scroller.checkAll(false);
				return;		

			case "check-all-in-view":
				scroller.checkAllInView(true);
				return;
				
			case "uncheck-all-in-view":
				scroller.checkAllInView(false);
				return;
			
			case "select":
				var prompt = new sl.fieldPrompt({
					"view":self.view,
					"fields":[
						{
							"n":o.field.n,
							"label":o.field.label,
							"ref":o.field.ref,
							"type":o.field.type,
							"multi":!!o.field.multi,
							"value":""
						}
					],
					"message":sl.format("en-us|Which %% would you like to add to the checked %%?",o.field.label,setup.name),
					"goName":names[o.selectAction][0].split().shift().ucFirst()+" "+setup.name,
					"cb":function(v) {
						action = o.selectAction;
						process2(scroller.getCheckedAsMostEfficient(),0,{"field":o.field.n,"value":v[o.field.n]});
					}
				});			
				break;
			
			case "set":
				if (!o.value) {
					var f = {};
					for (var n in o.field) {
						f[n] = o.field[n];
					}
					var prompt = new sl.fieldPrompt({
						"view":self.view,
						"fields":[f],
						"message":sl.format("en-us|Set %% to:",o.field.label),
						"goName":"en-us|Set",
						"cb":function(v) {
							if (v) process2(scroller.getCheckedAsMostEfficient(),0,{"field":o.field.n,"value":v[o.field.n]});
						}
					});
					break;
				}
			case "export": case "add-to": case "remove-from":
				process2(scroller.getCheckedAsMostEfficient(),0);
				break;
				
			case "delete":
				areYouSure = true;
				var lo = null;
				if (confirm(sl.format("en-us|Are you sure you want to %% the checked %%?",names[action][0],setup.name))) {
					function process(ids,pos) {
						self.hold = true;
						self.request("action",[action,[],ids,pos],function(pos){
							if (pos && pos[0]) {
								if (!lo) lo = new sl.loadingOverlay({"el":self.view.elInner});
								lo.progress(pos[0],pos[1],names[action][1].ucFirst() + " "+setup.name+"...");
								process(null,pos[0]);
							} else if (lo) {
								lo.loaded();
								lo.destruct();
								self.hold = false;
								queueReload(true);
							} else {
								self.hold = false;
								queueReload(true);
							}
						});
					};
					process(scroller.getCheckedAsMostEfficient(),0);
				}				
				break;
					
			default:
				console.log(action);
				break;
		}
	});
	
	scroller.addEventListener("head-click",function(type,o){
		self.request("orderby",[o.id,o.order],function(cnt){
			setObjectCount(cnt);
			queueReload();
		},scroller.el);
	});
	
	scroller.addEventListener("click",function(type,o){
		switch (mode) {
			case "select":
				self.dispatchEvent("selected",{"_KEY":o.item.getExtra("_KEY"),"_UNIQUE":o.item.getExtra("_UNIQUE"),"_NAME":o.item.getExtra("_NAME")});
				self.destruct();
				break;
				
			default:
				self.core.open((setup.customEdit?setup.customEdit:"edit")+"/?"+self.args[0]+"&"+o.item.getExtra("_KEY"));
				break;
		}
	});

	scroller.addEventListener("deactivated",function(t,scrollerItem){
		if (scrollerItem.serverListener) {
			self.removeServerListener(scrollerItem.serverListener);
			scrollerItem.serverListener = null;
		}
	});
	
	function setFieldValue(scrollerItem,n,v) {
		if (v === null || v === undefined) v = "";
		if (setup.sourceMap[n]) {
			for (var i = 0; i < setup.sourceMap[n].length; i++) {
				setFieldValue(scrollerItem,setup.sourceMap[n][i],v);
			}
		}
		if (!setup.fields[n] || !useField(setup.fields[n])) return;
		
		var field = setup.fields[n];
		
		if ((field.type || field.multi) && scrollerItem.element(n).slValue) {
			scrollerItem.element(n).slValue.setLabel(setup.useViewMode == "icon"&&n!=setup.nameField?field.label:false);
			scrollerItem.element(n).slValue.setValue(v);
		} else {
			scrollerItem.element(n).innerHTML = setup.useViewMode == "icon"&&n!=setup.nameField?field.label+": "+v:v;
			scrollerItem.element(n).style.display = setup.useViewMode == "icon" && !v ? "none" : "";
		}
	};
	
	var reindexing = false;
	
	scroller.requestItem = function(itemIndex) {
		var scrollerItem = this;
		scrollerItem.loadingMessage((function(){var rv=[];for(var i in setup.fields){rv.push(i);}return rv;})());
				
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
			
			scrollerItem.serverListener = self.addServerListener("change-"+self.args[0]+"/"+r[setup.key],function(res){
				if (res.DELETE === true) {
					queueReload(true);
				} else {
					for (var n in res) {
						setFieldValue(scrollerItem,n,res[n]);
					}
				}
			});
			
			scrollerItem.setExtra("_KEY",r._KEY);
			scrollerItem.setExtra("_NAME",r._NAME);
			if (r._IMAGE) scrollerItem.setExtra("_IMAGE",r._IMAGE.charAt(0) == "!" ? r._IMAGE.substr(1) : r._IMAGE);
			
			if (r._UNIQUE) scrollerItem.setExtra("_UNIQUE",r._UNIQUE);
			
			for (var n in setup.fields) {
				setFieldValue(scrollerItem,n,setup.fields[n].type == "extra" ? r : r[n])
			}
			scrollerItem.setAsLoaded();
		});
	};

	self.refreshListener = self.addServerListener("refresh-"+self.args[0],function(res){
		queueReload(true);
	});

	self.view.addEventListener("search-*",function(type,v){
		if (type == "search-click") {
			var lo = new sl.loadingOverlay({"el":scroller.el});
			
			setTitle(null,v.trim()?sl.format("en-us|Search for '%%'",v):"");
			
			function doSearch(v) {
				scroller.setNoItemsMessage(v?sl.format("en-us|There are no results for the search '%%'.",v.escapeHtml()):false);
				self.request("search",[v],function(cnt){
					if (typeof(cnt) == "object") { //Indexing
						lo.progress(cnt[0],cnt[1]);
						setTimeout(function(){doSearch(v)},1000);
					} else {
						lo.loaded();
						lo.destruct();
						setObjectCount(cnt);
						queueReload(true);
					}
				});
			};
			doSearch(v);
		}
	});

	self.view.maximize();

	self.addEventListener("destruct",function() {
		self.removeServerListener(self.refreshListener);
	});
});
