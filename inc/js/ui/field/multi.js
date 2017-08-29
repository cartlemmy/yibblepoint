sl.fieldDef.multi = {
	"manager":function(field) {
		var self = this;
		sl.initSlClass(this,"multi-manager");
		
		self.add = function(value) {

			var o = {};
			
			for (var i in field.origValues) {
				o[i] = field.origValues[i];
			}
			
			o.multEl = sl.dg("",field.el,"div",{"className":"multi"});
			o.multi = false;
			o.label = false;
			o.deleteable = true;
			o.type = field.multiType;			
			o.contEl = o.multEl;
			o.n = String(self.fields.length);
			o.value = value;
			o.listener = self;
			o.parent = field;
		
			self.fields.push(new sl.field(o));
			sl.dg("",o.multEl,"div",{"style":{"clear":"both"}});
		};
		
		self.remove = function(n) {
			var i;
			for (i = 0; i < self.fields.length; i++) {
				if (self.fields[i].n == n) {
					self.fields[i].destruct();
					self.fields[i].multEl.parentNode.removeChild(self.fields[i].multEl);
					self.fields.splice(i,1);
					break;
				}
			}
			for (i = 0; i < self.fields.length; i++) {
				self.fields[i].set("n",String(i));
			}
			self.update();
		};
		
		self.update = function(n) {
			var comb = [];
			for (var i = 0; i < self.fields.length; i++) {
				comb.push(field.value.def.encode(self.fields[i].value.value));
			}
			comb = comb.join("\n");
			
			field.value.setValue(comb);
			if (field.listener) field.listener.dispatchEvent("change",{"field":field.n,"value":comb});
		};
		
		self.addEventListener("blur",function(t,o){
			if (o.changed && o.value !== false) self.update(o.field);
		});
		
		self.addEventListener("change",function(t,o){
			if (o.value == "" && self.fields.length > 1) self.remove(o.field);
		});
		
		self.fields = [];
	},
	"init":function() {
		var self = this;
		this.el = sl.dg("",this.contEl,"div",{});

		this.addButtonEl = sl.dg("",this.contEl,"div",{
			"className":"sl-icon common-icon",
			"style":{
				"backgroundPosition":"-72px 0px"
			},
			"title":sl.format("en-us|Add another %%",this.singleLabel?this.singleLabel:this.label)
		});
		this.addButtonEl.addEventListener("click",
			function(){self.manager.add("");}
		);
		this.initSetValue = false;

		this.manager = new this.def.manager(this);
						
		return false;
	},
	"setValue":function(value) {
		if (!this.initSetValue) {
			this.initSetValue = true;
			value = value.split("\n");
			for (var i = 0; i < value.length; i++) {
				this.manager.add(this.value.def.decode(value[i]));
			}
		}
	}
};
