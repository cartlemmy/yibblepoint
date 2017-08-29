sl.require("js/file/manager.js",function(){
	var lo;
	
	self.setContentFromHTMLFile();

	self.formatContent({"singleName":app.setup.singleName});
	
	app.manager = new sl.fileManager({"file":app.importFile});
	
	function appeared() {
		if (app.processingFile) return;
		app.processingFile = true;
		lo = new sl.loadingOverlay({"el":app.view.elInner});
		app.manager.setFile(app.importFile);
	};
	
	self.addEventListener("appeared",function(){
		appeared();
	});
	
	app.manager.addEventListener("progress",function(t,prog) {
		var progMessage = sl.format("en-us|Processing Import File (%% rows)",sl.shortNum(prog[2]));
		self.setTitle(progMessage);
		if (lo) lo.progress(prog[0],prog[1],progMessage);
	});
	
	app.manager.addEventListener("load",function() {
		
		self.setTitle("en-us|Import Setup");
		lo.loaded();
		lo.destruct();
		
		app.processingFile = false;
		
		app.fieldMap = {};
		app.fieldDupCheck = {};
		
		var matched = 0, selectors = [];
		
		var fieldCorEl = app.view.element('field-correlation');
		
		for (var i = 0; i < app.manager.labels.length; i++) {
			
			var tr = sl.dg("",fieldCorEl,"tr",{});
			sl.dg("",tr,"td",{"innerHTML":app.manager.labels[i]});
			var td = sl.dg("",tr,"td",{});
			
			var select = sl.dg("",td,"select",{});
			select.impField = app.manager.labels[i];
			
			sl.dg("",select,"option",{
				"value":"",
				"innerHTML":"NONE"
			});
			
			select.addEventListener("change",function() {
				for (var i = 0; i < selectors.length; i++) {
					var sel = selectors[i], v = this.options[this.selectedIndex].value;
					if (sel != this && sel.options[sel.selectedIndex].value == v) {
						sel.selectedIndex = 0;
						app.fieldMap[this.impField] = "";
					}
				}
				app.fieldMap[this.impField] = v;
			});
			
			selectors.push(select);
			
			var label = app.manager.labels[i].charNormalize().replace(/[^A-Za-z\d]+/gi,"").toLowerCase();

			var match = null, num = 1;
			
			
			for (var j in app.setup.fields) {
				var field = app.setup.fields[j];
				if (field.importNames) {
					var option = sl.dg("",select,"option",{
						"value":j,
						"innerHTML":field.label ? field.label : j
					});
					
					if (field.importNames.split(",").indexOf(label) != -1) {
						matched++;
						match = j;
						select.selectedIndex = num;
					}
					num++;
				}				
			}
			app.fieldMap[app.manager.labels[i]] = match;
			
			td = sl.dg("",tr,"td",{});
			
			var cb = sl.dg("",td,"input",{"type":"checkbox"});
			cb.addEventListener("change",function() {
				app.fieldDupCheck[this.impField] = this.checked;
			});
			cb.impField = app.manager.labels[i];
		}
		
		app.importGroups = "";
		var groupFieldFound = false;
		for (var n in app.setup.fields) {
			var field = app.setup.fields[n];
			if (field.type == "group") {
				groupFieldFound = true;
				app.importGroupsField = n;
				
				var importGroups = new sl.field({
					"core":self.core,
					"contEl":app.view.element("import-groups"),
					"n":n,
					"type":"group",
					"cleaners":[],
					"value":"",
					"listener":app
				});
				
				app.addEventListener("blur",function(t,o){
					var field;
					if (field = app.setup.fields[o.field]) {
						if (field.type == "group") {
							app.importGroups = o.value;
						}
					}
				});
			}
		}
		
		
		
		if (!groupFieldFound) {
			app.view.element("setup-tabs").slSpecial.hideTab(2);
		}
		
		app.view.element("begin").addEventListener("click",function(){
			app.navigate("importing");
		});
		
		app.view.element("import-setup").innerHTML = sl.format(
			"en-us|You are about to import %% %%.",
			 app.manager.count(), app.setup.name
		);
		//sl.format("en-us|There are %% columns that do not match with an internal field.",matched)
	});
	
	appeared();
	
});

