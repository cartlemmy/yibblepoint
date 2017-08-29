self.setContentFromHTMLFile();

self.formatContent({"name":app.setup.name});

self.addEventListener("appeared",function(){
	var out = [], found = false;
	for (var n in app.fieldDupCheck) {
		if (app.fieldDupCheck[n]) {
			out.push(app.fieldMap[n]?app.fieldMap[n]:n);
			found = true;
		}
	}
	app.fieldDupCheck = found ? out : false;
			
	var lo = new sl.loadingOverlay({"el":app.view.elInner});
	
	var i = 0, complete = 0, len = app.manager.count();
	
	function done() {
		lo.loaded();
		lo.destruct();
		app.request("importComplete",[app.importFile.name],function(res){
			app.navigate("import-complete");
		});
	}

	app.inserted = new sl.bigArray("Uint32Array");
	app.updated = new sl.bigArray("Uint32Array");
	
	app.defaults = {};
	
	var g = app.importGroups ? app.importGroups.split(",") : [];	
	g.push(app.importFile.name);
	for (var j = 0; j < g.length; j++) {
		g[j] = "+"+g[j];
	}
	app.defaults[app.importGroupsField] = g.join(",");
	
	var def;
	for (var n in app.setup.fields) {
		if (n == "creationType") {
			app.defaults[n] = "user-import";
		} else if (app.setup.fields[n] && (def = app.setup.fields[n]["default"])) {
			if (typeof(def) == "string" && def.charAt(0) == "=") {
				eval("app.defaults[n] = "+def.substr(1));
			} else {
				app.defaults[n] = def;
			}
		}
	}
	
	function process() {
		app.manager.getRows(i,200,function(rawRows){
			var skipped = 0, rows = [];
			for (var j = 0; j < rawRows.length; j++) {
				var d = {}, use = false;
				for (n in rawRows[j]) {
					use = true;
					d[app.fieldMap[n]?app.fieldMap[n]:n] = rawRows[j][n];
				}
				if (use) {
					rows.push(d);
				} else {
					skipped ++;
					console.log(i + j);
				}
			}
			
			i += 200;

			if (rows.length) {
				app.request("imp",[rows,app.defaults,app.fieldDupCheck],function(res){
					if (res && res.accepted) {
						
						var j;
						
						for (j = 0; j < res.inserted.length; j++) {
							app.inserted.push(res.inserted[j]);
						}
						
						for (j = 0; j < res.updated.length; j++) {
							app.updated.push(res.updated[j]);
						}
						
						complete += res.accepted + skipped;
						var progMessage = sl.format("en-us|Importing %% / %%",complete,len);
						self.setTitle(progMessage);
						lo.progress(i,len,progMessage+"<br />"+sl.format("en-us|%% inserted<br />%% updated",app.inserted.count,app.updated.count));
						if (complete >= len) {
							done();
						} else {
							process();
						}
					}
				});
			}
		});
	};

	process();

});
	

