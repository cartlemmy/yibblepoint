sl.toolbar = function(o) {

	var self = this;
	sl.initSlClass(this,"toolbar");

	self.setTools = function(tools) {
		self.tools = tools;
		if (tools && self.isInit) {
			for (var i = 0; i < self.tools.length; i++) {
				var tool = self.tools[i];
				if (typeof(tool) == "string") {
					switch (tool) {
						case "sep":
							sl.dg("",self.el,"div",{"className":"sep"});
							break;
					}
				} else {
					self.addTool(tool);
				}
			}
		}		
	};
	
	self.setIconSrc = function(src) {
		self.iconsSrc = (self.app?"app/"+self.app.ref+"/":"")+src;
	};
	
	self.addTool = function(tool) {
		if (tool.icon !== undefined) {
			tool.iconEl = sl.dg("",self.el,"div",{"className":"icon"});
			if (tool.href) {
				tool.aEl = sl.dg("",tool.iconEl,"a",{"href":tool.href});
				if (tool.target) tool.aEl.target = tool.target;
				sl.dg("",tool.aEl,"img",{"src":"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7","style":{"width":"32px","height":"32px"}});
			}
			if (typeof(tool.icon) == "number") {
				if (self.iconsSrc) tool.iconEl.style.backgroundImage = "url('"+self.iconsSrc+"')";
				if (tool.title) tool.iconEl.title = tool.title;
				tool.iconEl.style.backgroundPosition = "-"+(tool.icon * 32)+"px 0px";
			} else {
				tool.iconEl.style.backgroundImage = "url('"+(self.app?"app/"+self.app.ref+"/":"")+tool.icon+"')";
			}
			if (!tool.href) tool.iconEl.addEventListener("click",function(){
				self.dispatchEvent("click",tool);
			});
		} else {
			
		}
	};
	
	self.getTool = function(o) {
		for (var i = 0; i < self.tools.length; i++) {
			var pass = true;
			for (var n in o) {
				if (o[n] != self.tools[i][n]) {
					pass = false;
					break;
				}
			}
			if (pass) return self.tools[i];
		}
		return null;
	};
	
	self.init = function() {
		self.el = sl.dg("",self.contEl,"div",{"className":"toolbar"});
		if (self.fromFile) {
			if (self.app) {
				self.app.loadAsData(self.fromFile,self.set,{"self":self});
			} else {
				sl.loadAsData(self.fromFile,self.set,{"self":self});
			}
		}
		self.isInit = true;
		self.setTools(self.tools);
	};
	
	self.setValues({
		"isInit":false,
		"tools":[]
	});
	
	self.destruct = function() {
		
	};
	
	if (o) self.setValues(o);
	
	self.init();
};
