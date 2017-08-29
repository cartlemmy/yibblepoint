self.request("getAll",[],function(info){
	
	var isOptionChild = false;
	if (info.data || self.args[1] == "NEW") {
		self.info = info;
		var fields = {}, tabModule = {};
		var item = info.data, isNew = false;
		
		var menu = [], tools = [], changedFields = {};

		
		if (self.args[1] == "NEW") {
			isNew = true;
			if (sl.config.preferences.autoSave) menu.unshift({"label":"en-us|Create","action":"create"});
			
			var def;
			for (var n in item) {
				if (info.setup.fields[n] && (def = info.setup.fields[n]["default"])) {
					if (typeof(def) == "string" && def.charAt(0) == "=") {
						eval("item[n] = "+def.substr(1));
					} else {
						item[n] = def;
					}
				}
				if (self.args[2] && self.args[2][n]) item[n] = self.args[2][n];
			}
		}
		
		var og;	
		if (og = info.setup.optionGroup) {
			if (info.data[og.parent] == "0" || (info.data[og.parent] === undefined && !self.args[2])) {
				if (!info.setup.tabs) {
					var f = [];
					info.setup.tabs = {"en-us|Info":f};
				}
				info.setup.tabs["en-us|Options"] = [["custom",'/edit/options-view']];
			} else {
				isOptionChild = true;
			}
		}
		
		if (!sl.config.preferences.autoSave) tools.push("save");
		
		function getTitle() {
			var dn = typeof(info.setup.displayName) == "string" ? [info.setup.displayName] : info.setup.displayName;
			
			var displayName = "";
			for (i = 0; i < dn.length; i++) {
				eval("displayName = "+dn[i]);
				if (displayName) displayName = displayName.trim();
				if (displayName != "") break;
			}
			
			return (self.args[1] == "NEW" ? "en-us|New " : "en-us|Edit ")+info.setup.singleName+(displayName.trim() != ""?sl.config.sep+displayName:"")+(isOptionChild?sl.config.sep+og.name:"");
		};
		
		function tabRefresh() {
			for (var n in info.setup.tabs) {
				for (var fieldN in info.setup.tabs[n]) {
					var field = info.setup.tabs[n][fieldN];
					if (typeof(field) == "object" && field[0] == "custom") {
						var tm = sl.parseExpression(field[1],{"item":info.data});
						if (tabModule[n].name != tm) {
							tabModule[n].ob.destruct(function(){
								tabModule[n].name = tm;
								tabModule[n].ob = self.addModule(tm, tabModule[n].section);
							});
						}
					}
				}
			}
		};
		
		if (menu.length) tools.push("menu");
		
		self.createView({
			"title":getTitle(),
			"contentPadding":"0px",
			"tools":tools,
			"noScroll":info.setup.tabs?true:false
		});
		
		if (!sl.config.preferences.autoSave) {
			self.view.setSaveState(self.args[1] == "NEW" ? "new" : "saved");
		}
		
		self.view.setMenu(menu);
		
		function save() {
			self.view.setSaveState("saving");
			if (isNew) {
				if (info.setup.required) {
					var s = info.setup.required.split(/([^\w\d_]+)/);
					var check = [], text = [];
					for (var i = 0; i < s.length; i++) {
						var t = s[i].trim();
						switch (t) {
							case "||":
								text.push("en-us| or ");
								break;
							
							case "&&":
								text.push("en-us| and ");
								break;
								
							case "(":
								text.push(",");
							case ")":
								break;
							
							default:
								text.push(info.setup.fields[t] ? info.setup.fields[t].label : t);
								t = "info.data."+t+".trim() != ''";
								break;
						}
						check.push(t);
					}
					
					var pass;
					eval("pass = "+check.join(" "));
					if (!pass) {
						alert(sl.format("en-us|%% required.",text.join("")));
						return;
					}
				}
				
				if (self.args[2]) { //Default values defined in app parameters
					for (var n in self.args[2]) {
						if (!info.data[n]) info.data[n] = self.args[2][n];
					}
				}
					
				self.request("create",[info.data],function(res){
					if (self.args[1] == "NEW") self.view.removeMenuItem(0);
					info.setup.args[1] = self.args[1] = res;
					self.view.setSaveState("saved");
					self.dispatchEvent("id-set",res);
				});
				isNew = false;
				
			} else {
				var saved = 0;
				for (var i in changedFields) {
					self.request("set",[i,changedFields[i]],function(res){
						saved --;
						if (saved <= 0) self.view.setSaveState("saved");
					});
					saved ++;
				}
				self.request("apply",[],function(res){
					//console.log('applied',res);
				});
			}
			changedFields = {};
		};
		
		self.view.save = save;
		
		self.view.addEventListener("save-click",function(type,o) {
			save();
		});
		
		self.view.addEventListener("menu-click",function(type,o){
			switch (o.item.action) {
				case "create":
					save();
					break;
			}
		});
		
		function createChangeListener() {
			self.serverListener = self.addServerListener("change-"+self.args[0]+"/"+info.setup.args[1],function(res){
				if (res.DELETE === true) {
					self.destruct();
				} else {
					for (var n in res) {
						if (fields[n]) fields[n].setValue(res[n]);
						self.info.data[n] = info.data[n] = res[n];
					}
					self.view.setTitle(getTitle());
				}
				self.dispatchEvent("server-change",res);
			});
		};
		
		self.addEventListener("id-set",createChangeListener);
		
		if (!isNew) self.dispatchEvent("id-set",info.setup.args[1]);
		
		var tabMap = {}, fieldCont = null;
		
		function getFieldCont(n) {
			if (info.setup.tabs) {
				if (tabMap[n]) return tabMap[n].section;
			}
			return fieldCont;
		};
		
		if (info.setup.tabs) {
			var tabEl = self.view.appendElement("","div",{"className":"tabbed"});
			var tabbed = new sl.tabbed({"el":tabEl,"view":self.view});
			for (var n in info.setup.tabs) {
				var tab = tabbed.addTab(n);
				for (var fieldN in info.setup.tabs[n]) {
					if (!fieldCont) fieldCont = tab.section;
					
					var field = info.setup.tabs[n][fieldN];
					switch (typeof(field) == "string" ? "field" : field[0]) {
						case "field":
							tabMap[fieldN] = info.setup.tabs[n];
							break;
						
						case "custom":
							var name = sl.parseExpression(field[1],{"item":info.data});
							var m = self.addModule(name,tab.section);
							m.info = info;
							tabModule[n] = {
								"name":name,
								"ob":m,
								"section":tab.section
							};
							break;
					}
				}
			}
			tabbed.setSelected(0);
			self.tabbed = tabbed;
			self.tabs = tabbed.tabs;
		} else fieldCont = self.view.elInner;
		
		self.addField = function(cont,n,field,noLabel) {
			var cont = sl.dg("",cont,"fieldset",{"className":(field.vertical?"vertical":"horizontal")});
			if (!noLabel) {
				sl.dg("",cont,"label",{"innerHTML":field.label});
			}
			
			if (field.width) cont.style.width = field.width;
			
			if (field.custom) {
				return sl.dg("",cont,field.tag);
			} else {
				var o = {
					"core":self.core,
					"view":self.view,
					"contEl":cont,
					"fields":fields,
					"n":n,
					"cleaners":field.cleaners ? field.cleaners : [],
					"value":info.data[n],
					"listener":self
				};
				
				if (info.setup.table == "db/user") o.userID = info.data._KEY;
				
				for (var i in field) {
					o[i] = field[i];
				}
				fields[n] = new sl.field(o);
				return fields[n];
			}
		};
		
		for (var n in info.setup.fields) {
			var field = info.setup.fields[n];
			//TODO: why aren't the option and option type fields showing
			if (
				!field['import'] && field.editable !== false &&
				!(og && og.exclusiveFields && og.exclusiveFields.indexOf(n) != -1 && !isOptionChild)
			) {
					self.addField(getFieldCont(n),n,field);
			}
		}
		
		if (info.setup.showExtraData) {
			var exclude = ["_KEY","_UNIQUE","_NAME",info.setup.userField,info.setup.key];
			for (var n in info.data) {
				if (info.data[n].trim() != "" && !info.setup.fields[n] && exclude.indexOf(n) == -1) {
					self.addField(getFieldCont(n),n,{
						"readOnly":true,
						"type":"text",
						"value":info.data[n].trim(),
						"label":info.setup.extraDataLabels[n] ? info.setup.extraDataLabels[n] : n	
					});
				}
			}
		}

		self.addEventListener("blur",function(t,o){
			if (o.changed && o.value !== false) {
				if (sl.config.preferences.autoSave) {
					self.request("set",[o.field,o.value],function(res){});
				} else {
					changedFields[o.field] = o.value;
					self.view.setSaveState("unsaved");
				}
			}
		});
		
		self.changeField = function(n,v) {
			if (!sl.config.preferences.autoSave) self.view.setSaveState("unsaved");
			changedFields[n] = info.data[n] = v;
			self.view.setTitle(getTitle());
			if (info.setup.fields[n] && info.setup.fields[n].tabRefresh) tabRefresh();
			self.dispatchEvent("change-field",{"field":n,"value":v});
		};
		
		self.addEventListener("change",function(t,o){
			if (o.value !== false) {
				self.changeField(o.field,o.value);
			}
		},true);
		
		self.view.initContent(info.setup.editView && info.setup.editView.maximize ? null : 680);
		
		self.addEventListener("destruct",function() {
			if (self.serverListener) self.removeServerListener(self.serverListener);
		});
	} else {
		self.createView({
			"title":info.setup ? info.setup.singleName : "en-us|Error",
			"contentPadding":"8px"
		});
		
		self.view.setContentAsHTML("<div class=\"warn\">"+(info.setup ? sl.format("en-us|%% not found.",info.setup.singleName) : info.error)+"</div>");
	}
	
	self.disableField = function(n,disable,message) {
		if (fields[n].el.nodeName == "SELECT") {
			fields[n].el.disabled = disable;
		} else {
			fields[n].el.readOnly = disable;
		}
		fields[n].el.onclick = message && disable ? function(e){
			sl.preventDefault(e);
			alert(message);			
			return false;
		} : undefined;
	};	
	
	self.view.center();
	
	if (info.setup.editView) {
		if (info.setup.editView.maximize) self.view.maximize();
	}
});
