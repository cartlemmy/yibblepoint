self.request("setup",[],function(setup){
	
	//console.log(setup);
	
	self.hold = false;
	self.createView({
		"title":setup.name,
		"icon":setup.icon?setup.icon:null,
		"contentPadding":"8px",
		"tools":[]
	});
	
	var orderBy = -1, curZoom, iframeEl, cont, outputField, n, mode = self.args.length >= 2 ? self.args[1] : "normal";
	var titleSet = [false,false], outOptionsCnt = 0, outOption;
	var fields = {};
	
	for (n in setup.report.inputs) {
		var field = setup.report.inputs[n];

		cont = sl.dg("",self.view.elInner,"fieldset",{"className":"horizontal"});

		sl.dg("",cont,"label",{"innerHTML":field.label});
		
		var o = {
			"core":self.core,
			"view":self.view,
			"contEl":cont,
			//"fields":fields,
			"n":n,
			"cleaners":field.cleaners ? field.cleaners : [],
			//"value":info.data[n],
			"listener":self
		};
				
		for (var i in field) {
			o[i] = field[i];
		}
		fields[n] = new sl.field(o);		
	};
	
	var options = {"":"en-us|Select One..."};
	for (n in setup.report.outputOptions) {
		outOptionsCnt++;
		outOption = n;
		options[n] = setup.report.outputOptions[n].name;
	}
	
	if (outOptionsCnt > 1) {
		cont = sl.dg("",self.view.elInner,"fieldset",{"className":"horizontal"});
		sl.dg("",cont,"label",{"innerHTML":"en-us|Report Output"});
		outputField = new sl.field({
			"core":self.core,
			"view":self.view,
			"contEl":cont,
			"n":"reportOutput",
			"type":"select",
			"options":options,
			"listener":self
		});
	}
	
	sl.cb(self.view.elInner);
	
	cont = sl.dg("",self.view.elInner,"fieldset",{"className":"horizontal","style":{"cssFloat":"right"}});
	var buttonEl = sl.dg("",cont,"button",{"innerHTML":"en-us|Generate Report"});
	buttonEl.addEventListener("click",function(){		
		doRequest();
	});
	
	function doRequest() {
		var params = {};
		for (n in fields) {
			params[n] = fields[n].getValue();
		}
		
		var t = (outOptionsCnt>1?outputField.getValue():outOption).split("/");
		var rep = t.shift();
		
		self.request("generate",[params,rep,t.join("/"),orderBy],function(res){
			//console.log(res);
			if (res.data && res.data.file) setFrameSrc(res.data.file);
		});
	}
	
	var printAEl = sl.dg("",cont,"a",{"href":"about:blank","target":"_BLANK"});
	sl.dg("",printAEl,"button",{"innerHTML":"en-us|Print","style":{"marginRight":"8px"}});
	
	sl.cb(self.view.elInner);
	
	iframeEl = sl.dg("",self.view.elInner,"iframe",{"src":"about:blank","style":{"width":"8.5in","height":"11in"}});
	
	function setFrameSrc(src) {
		iframeEl.src = src;
		printAEl.href = src+"?print=1";
		iframeEl.onload = function(){
			iframeEl.contentWindow.sl = sl;
			iframeEl.contentWindow.report = self;		
			setFrameZoom(curZoom);
		};
	}
	
	self.sortBy = function(col) {
		orderBy = col;
		doRequest();
	}
	
	function setFrameZoom(zoom) {
		iframeEl.style.zoom = curZoom = zoom;
		var p = iframeEl.contentWindow.document.getElementById('paper');
		if (p) p.style.zoom = zoom
	}
	setFrameZoom("75%");
	
	function setTitle() {
		var t = ["en-us|Report"];
		
		t.push(setup.name);
		
		for (var i = 0; i < arguments.length; i++) {
			if (arguments[i] !== null && arguments[i] !== undefined) titleSet[i] = arguments[i] ? arguments[i] : false;
		}
		
		for (var i = 0; i < titleSet.length; i++) {
			if (titleSet[i]) t.push(titleSet[i]);
		}

		self.view.setTitle(t.join(sl.config.sep)); 
	};
	
	setTitle();
	
	self.view.initContent();
	self.view.center();

});
