sl.fieldDef.dateRange = {
	"manager":function(field) {
		var self = this;
		sl.initSlClass(this,"date-range");
		
		self.addEventListener("change",function(t,o){
			var vals = [];
			for (var i = 0; i < 2; i++) {
				var v = field.rangeFields[i].value.value;
				field.value.range[i].value = v;
				vals.push(v);
			}
			field.applyValue(vals.join("-"));
		});
	},
	"init":function() {
		var self = this;
		
		self.el = sl.dg("",self.contEl,"div",{});
		
		self.rangeFields = [], transf = ["nullLabel","format"];
		
		self.manager = new self.def.manager(self);
			
		var vals = self.value.value ? self.value.value.split("-") : [0,0];
		
		for (var i = 0; i < 2; i++) {
			var o = {
				"type":"date",
				"core":self.core,
				"view":self.view,
				"dateRange":true,
				"ending":i == 1,
				"nullLabel":"Any",
				"contEl":self.el,
				"n":i,
				"value":vals[i],
				"listener":self.manager
			};
			
			for (var j = 0; j < transf.length; j++) {
				if (self[transf[j]] !== undefined) o[transf[j]] = self[transf[j]];
			}
					
			var field = new sl.field(o);
			self.rangeFields.push(field);
			
			field.el.style.width = "126px";
			
			if (i == 0) sl.dg("",self.el,"div",{"innerHTML":"en-us|to","style":{"margin":"6px 10px 0 10px","cssFloat":"left"}});
		}
		
		for (var i = 0; i < 2; i++) {
			self.rangeFields[i].siblingField = self.rangeFields[i^1];
		}
		
		self.initSetValue = false;

	
						
		return false;
	},
	"setValue":function(value) {
		if (!this.initSetValue) {
			this.initSetValue = true;
		}
	}
};
