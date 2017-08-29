sl.fieldDef.objectDropDown = {
	"init":function() {
		var self = this;
		self.el = sl.dg("",self.contEl,"select",{});
		
		var i, m, extraFields = [];
		if (self.where) {
			if (m = self.where.match(/item\.[\w]+/g)) {
				for (i = 0; i < m.length; i++) {
					extraFields.push(m[i].replace('item.',''));
				}
			}
		}
		self.core.net.send("get-object-list",{"ref":self.ref,"filter":self.filter,"fields":extraFields},{},function(r){
			var i, items, item, pass;
			if (r.list) {
				
				if (self.where) {
					items = r.list;
					for (var i in items) {
						item = items[i];
						if (typeof(item) == 'object') {
							eval('pass = '+self.where+';');
							r.list[i].show = pass;
						}
					}
				}
				
				var parent = false;
				
				if (r.info.parentField) {
					parent = r.info.parentField;
				} else if (r.info.optionGroup) {
					parent = r.info.optionGroup.parent;
				}
				
				sl.dg("",self.el,"option",{
					"value":"",
					"innerHTML":"en-us|Select One..."
				});
				
				function buildBranch(parentId,level) {
					var found = false;
					for (var i = 0; i < r.list.length; i++) {
						var o = r.list[i];
						if (o.show === undefined || o.show) {
							if (parent === false || String(o[parent]) == String(parentId)) {
								found = true;
								var el = sl.dg("",self.el,"option",{
									"value":o._KEY,
									"innerHTML":((new Array(level+1)).join("&nbsp;&nbsp;&nbsp;&nbsp;"))+o._NAME+(r.info.optionGroup&&parentId!=0?" ("+o[r.info.optionGroup.typesField]+": "+o[r.info.optionGroup.nameField]+")":"")
								});
								
								
								if (parent !== false && buildBranch(o._KEY,level + 1)) {
									el.disabled = true;
								}
							}
						}
					}
					return found;
				};
				
				buildBranch(0,0);
				
				for (i = 0; i < self.el.options.length; i++) {
					if (self.value.value == self.el.options[i].value) {
						self.el.selectedIndex = i;
						break;
					}
				}
			}
		});		
		
		self.el.addEventListener("change",function(){
			self.changed(self.el.options[self.el.selectedIndex].value,true);
		},false);
		return false;
	},
	"setValue":function(value) {
		for (var i = 0; i < this.el.options.length; i++) {
			if (value == this.el.options[i].value) {
				this.el.selectedIndex = i;
				return true;
			}
		}
		return true;
	}
};
