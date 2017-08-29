sl.fieldDef.group = {
	"init":function() {
		var self = this;
		
		self.directChange = false;
		self.customBlur = true;
		
		this.addEventListener("select",function(t,m){
			self.def.addGroup.call(self,m.name);
		});
		
		self.groupEl = sl.dg("",self.contEl,"div",{});
		
		self.el = sl.dg("",self.contEl,"input",{"type":"text","style":{"display":"none"}});
		
		sl.addEventListener(self.el,"focus",function(){
			self.focus = true;
		},false);
		
		sl.addEventListener(self.el,"blur",function(){
			self.focus = false;
			if (self.suggestionClick()) return;
			self.def.addGroup.call(self,this.value.split(",").join(" "));
		},false);
		
		sl.addEventListener(self.el,"keydown",function(e) {
			if (e.keyCode == 13 && self.suggestions.cursor == -1) {
				self.def.addGroup.call(self,this.value.split(",").join(" "));
			}
		},false);
				
		this.addButtonEl = sl.dg("",this.contEl,"div",{
			"className":"sl-icon common-icon",
			"style":{
				"backgroundPosition":"-72px 0px"
			},
			"title":sl.format("en-us|Add another %%",this.singleLabel?this.singleLabel:this.label)
		});
		
		this.addButtonEl.addEventListener("click",function(){self.def.show.call(self)});
		return true;
	},
	"show":function(){
		this.el.value = "";
		this.el.style.display = "block";
		this.el.focus();
	},
	"hide":function() {
		this.el.value = "";
		this.el.style.display = "none";
	},
	"removeGroup":function(value) {
		for (var i = 0; i < this.groups.length; i++) {
			if (this.groups[i].value.safeName() == value.safeName()) {
				this.groups[i].el.parentNode.removeChild(this.groups[i].el);
				this.groups.splice(i,1);
				this.def.refresh.call(this);
				return;
			}
		}
	},
	"addGroup":function(value,fromInit) {
		var self = this;
		if (value.trim() == "") {
			if (!fromInit) this.def.refresh.call(this);
			return;
		}

		this.def.hide.call(this);

		for (var i = 0; i < this.groups.length; i++) {
			if (this.groups[i].value.safeName() == value.safeName()) return;
		}
		
		var el = sl.dg("",this.groupEl,"div",{"className":"sl-group"});
		
		var del = sl.dg("",el,"div",{"className":"del","innerHTML":"x"});
		sl.dg("",el,"div",{"className":"label","innerHTML":value});
		
		del.addEventListener("click",function(){
			self.def.removeGroup.call(self,value);
		});
		
		this.groups.push({
			"el":el,
			"value":value			
		});
		
		if (!fromInit) this.def.refresh.call(this);		
	},
	"setValue":function(value) {
		value = value.split(",");

		if (this.groups) {
			var group;
			while (group = this.groups.pop()) {
				group.el.parentNode.removeChild(group.el);
			}
		} else this.groups = [];
		
		for (var i = 0; i < value.length; i++) {
			if (value[i].trim() != "") this.def.addGroup.call(this,value[i],1);
		}
		return true;
	},
	"refresh":function(){
		var v = [];
		for (var i = 0; i < this.groups.length; i++) {
			v.push(this.groups[i].value);
		}
		this.value.value = v.join(",");
		if (this.listener) this.listener.dispatchEvent("blur",{"field":this.n,"value":this.value.value,"changed":true});
	},
	"checking":false,
	"change":function(text) {
		text = text.trim();
		if (text.length < 2) return;
		var self = this;
		if (!self.def.checking) {
			self.def.checking = true;
			self.core.net.send("search",{"ref":"db/groups","match":{"ref":self.ref},"text":text},{},function(res){
				self.def.checking = false;
				self.clearSuggestions();
				if (res.matches) {
					for (var i = 0; i < res.matches.length; i++) {
						var m = res.matches[i], v = m._UNIQUE ? m._UNIQUE : m._KEY;
						if (m._NAME.safeName() == text.safeName()) {
							self.el.value = m._NAME;
						} else {
							if (self.focus) {
								var found = false;
								for (var j = 0; j < self.groups.length; j++) {
									if (self.groups[j].value.safeName() == v) {
										found = true;
										break;
									}
								}
								if (!found) {
									self.addSuggestion({
										"value":v,
										"name":m._NAME,
										"formatted":sl.highlightMatch(m._NAME,text)
									});
								}
							}
						}
					}
				}
			});
		}
	}
};
