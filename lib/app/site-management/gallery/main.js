sl.require("js/file/uploader.js",function(){
	self.require(["gallery.css"],function(){
		self.request("setup",[],function(setup){
			var pageData = {}, changedPageData = {}, editingPage, pages,
				galleryName = "", galleryId = "", reCount = false,
				objectCount = 0;

			function updateTitle() {
				var t = [self.manifest.name];
				if (galleryName) t.push(galleryName);
				self.view.setTitle(t.join(sl.config.sep));
				self.view.element("add-title").innerHTML = sl.format("en-us|Add Images to %%",galleryName);
			}
			
			self.createView({
				"contentPadding":"0px",
				"tools":["menu"]
			});

			self.view.setMenu([
				/*{"label":"en-us|+ New","action":"new"}*/
			]);
			
			self.view.addEventListener("menu-click",function(type,o){
				switch (o.item.action) {
					/*case "new":
						newPage();
						break;*/
				}
			});
				
			self.view.setContentFromHTMLFile();
			self.view.maximize();

			var hLayout = self.view.element("galleryFrames");

			var hView = new sl.heirarchicalView({
				"el":self.view.element("galleries"),
				"data":{},
				"clickable":true,
				"childrenName":"_CHILDREN",
				"callback":function(name,o,tree){
					var cont = sl.dg("",null,"span");
					sl.dg("",cont,"text",o._NAME);
					var delBut = sl.dg("",cont,"span",{"className":"s-gallery-icon"});
					delBut.addEventListener("click",function(e){
						sl.cancelBubble(e);
						if (confirm("en-us|Are you sure you want to delete this gallery?")) {
							self.request("deleteGallery",[o._KEY],function(res){
								if (o._KEY == galleryId) setGalleryId(0);
								refreshGalleries();
							});
						}
					});
					return cont;
				}
			});
			
			function setGalleryId(id) {
				galleryId = id;
				if (id) {
					self.request("setGalleryId",[id],function(res){
						setObjectCount(res.cnt);
						galleryName = res.name;
						updateTitle();
						queueReload();
					},scroller.el);
				} else {
					setObjectCount(0);
					galleryName = "";
					updateTitle();
					queueReload();
				}
			};
			
			hView.addEventListener("click",function(t,n,o){
				setGalleryId(o._KEY);
				galleryName = o._NAME;
				hView.select(o._ID);
				updateTitle();
			});
			
			function refreshGalleries() {
				self.request("getGalleries",[],function(res){
					if (res && JSON.stringify(res) != "[]") hView.setData(res);
					if (node = hView.getDataNodeBySearch("_KEY",galleryId)) {
						hView.select(node._ID);
					}
				});
			};
			refreshGalleries();
			
			self.view.element("add-gallery").addEventListener("click",function(){
				var app = self.core.open("edit/?db.gallery&NEW");
				app.addEventListener("id-set",function(t,id){
					setGalleryId(id);
				});
			});		
			
			self.galleryListener = self.addServerListener("refresh-db.gallery",function(res,o){
				refreshGalleries();
			});
			
			self.addEventListener("destruct",function() {
				self.removeServerListener(self.galleryListener);
			});
		
			var uploader = new sl.uploader({
				"el":self.view.element("uploader"),
				"allow":["image/(jpeg|png|gif)"]
			});
			
			uploader.fileInputEl.addEventListener("click",function(e) {
				if (!galleryId) {
					alert("en-us|Please select a gallery first");
					sl.preventDefault(e);
					return false;
				}
			});
			
			uploader.addEventListener("uploaded",function(t,file){
				if (file._IMAGE) {
					self.request("addToGallery",[galleryId,file._IMAGE],function(id){
						if (id) {	
							var fs = sl.dg("",file.infoEl,"fieldset");
							sl.dg("",fs,"label","Caption");
							var captionEl = sl.dg("",fs,"input",{"type":"text"});
							captionEl.addEventListener("change",function(){
								self.request("updateGalleryImage",[id,{"caption":captionEl.value}],function(res){
									console.log(res);
								});
							});
							queueReload(true);
						}
					
					});
				} else {
					//TODO: something went wrong
				}
			});
			
			var scroller = self.view.element("view-scroller").slSpecial;
			
			scroller.orderby = "sortOrder";
			scroller.setViewMode("icon");
			
			scroller.refreshTotalRow = function(cb) {
				return {};
			};
			
			scroller.indirectSize = true;
			scroller.clickableHead = true;
			scroller.clickableRows = true;
			scroller.sortOrderHead = true;
			scroller.checkableRows = true;
			
			function useField(field) {
				return !field['import'] && !(field.viewable != undefined && field.viewable === false);
			};
		
			var reloadTimer = null;
			function queueReload(nrc) {
				if (nrc) reCount = true;
				if (reloadTimer) clearTimeout(reloadTimer);
				if (self.hold) return;
				reloadTimer = setTimeout(function(){scroller.reload(!reCount);reCount=false;},10);
			};
		
			for (var n in setup.fields) {
				var field = setup.fields[n];
				field.n = n;
				
				if (useField(field)) {
					scroller.addColumn(n,field);
				}
			}
			
			scroller.checkboxMenu = [
				{"label":"en-us|Check All","action":"check-all"},
				{"label":"en-us|Uncheck All","action":"uncheck-all"},
				{"label":"en-us|Check All In View","action":"check-all-in-view"},
				{"label":"en-us|Uncheck All In View","action":"uncheck-all-in-view"},
				"",
				{"label":"en-us|Delete Checked","action":"delete"}
			];
		
			scroller.init();
			
			scroller.addEventListener("checkbox-menu",function(type,action,o){
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
					break;
				
				case "uncheck-all":
					scroller.checkAll(false);
					break;		

				case "check-all-in-view":
					scroller.checkAllInView(true);
					break;
					
				case "uncheck-all-in-view":
					scroller.checkAllInView(false);
					break;
				
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
			
			function setObjectCount(cnt) {
				objectCount = cnt;
				scroller.setItemCount(cnt);
				updateTitle();
			};
		
			function getObjectCount(cb) {
				self.request("objectCount",[],function(cnt){
					setObjectCount(cnt);
					if (cb) cb();
				});
			};

			getObjectCount();
			
			scroller.requestObjectCount = getObjectCount;
		
			/*scroller.requestSections = function() {
				self.request("sections",[],function(r){
					scroller.setSections(r);
				});
			};*/
		
			scroller.addEventListener("head-click",function(type,o){
				self.request("orderby",[o.id,o.order],function(cnt){
					setObjectCount(cnt);
					queueReload();
				},scroller.el);
			});
			
			scroller.addEventListener("click",function(type,o){
				self.core.open("edit/?db.galleryImages&"+o.item.getExtra("_KEY"));
			});
			
			scroller.addEventListener("deactivated",function(t,scrollerItem){
				if (scrollerItem.serverListener) {
					self.removeServerListener(scrollerItem.serverListener);
					scrollerItem.serverListener = null;
				}
			});
			
			function setFieldValue(scrollerItem,n,v) {
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
			
			self.addEventListener("destruct",function() {
				self.removeServerListener(self.refreshListener);
			});
			
			hLayout.slSpecial.addEventListener("resize",function() {
				scroller.refreshSize();
			});
			
			setGalleryId(false);
		});
	});
});
