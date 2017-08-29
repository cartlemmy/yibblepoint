self.require(["manage-groups.css?v=2"],function(){
	self.request("setup",[],function(setup){
		//console.log(setup);
		
		self.groupField = setup.fields[setup.groupField];
		
		self.createView({
			"title":sl.format("en-us|Manage %%",self.groupField.label),
			"contentPadding":"8px",
			"tools":[]
		});
		
		self.view.setContentFromHTMLFile();
		
		function addGroupType(id) {
			return new groupType({"id":id},self);
		}
		
		console.log(addGroupType(0));
			
		
		
		self.addEventListener("destruct",function() {
			
		});
		
		self.view.maximize();
	});
});

var groupType = function(o,app) {
	var self = this;
	sl.initSlClass(this,"group-type");
	
	self.setName = function(name) {
		self.label.innerHTML = name;
		self.name = name;
	};
	
	self.setGroups = function(groups) {
		if (self.groups) {
			var g;
			while (g = self.groups.pop()) {
				g.remove();
			}
			
			for (var i = 0; i < groups; i++) {
				//TODO
			}
		} else {
			self.groups = [];
		}
	};
	
	self.refresh = function() {		
		app.request("getGroupType",[o.id],function(res){
			console.log(res);
			self.setValues(res);
		});
	};
	
	self.init = function() {
		self.el = sl.dg("",app.view.element("types"),"section",{"className":"group-type-box"});
		self.label = sl.dg("",self.el,"label");
		self.refresh();
	};
	
	self.setValues({
		"el":null,
		"groups":[]
	});
	
	self.init();
	
	if (o) self.setValues(o);
};
