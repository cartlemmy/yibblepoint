sl.fieldDef.phone = {
	"init":function() {
		this.typeV = "";
		return true;
	},
	"postInit":function() {
		var self = this;
		this.typeEl = new sl.dg("",this.el,"select",{"style":{"marginLeft":"8px","width":"80px"}});
		this.typeEl.addEventListener("change",function(){
			self.def.update.call(self,self.typeEl.options[self.typeEl.selectedIndex].value);
		});
		this.el.parentNode.appendChild(this.typeEl);
		this.el.style.width = ((this.el.offsetWidth - sl.getTotalElementSize(this.el,true).width) - sl.getTotalElementSize(this.typeEl).width)+"px";
		
		var options = {
			"main":"en-us|Main",
			"mobile":"en-us|Mobile",
			"work":"en-us|Work",
			"home":"en-us|Home",
			"home-fax":"en-us|Home Fax",
			"work-fax":"en-us|Work Fax",
			"pager":"en-us|Pager",
			"emergency":"en-us|Emergency"
		};
		for (var n in options) {
			sl.dg("",this.typeEl,"option",{
				"value":n,
				"innerHTML":options[n]
			});
		}
	},
	"setValue":function(value) {
		var s = value.split(";");
		
		//this.geo = {};
		
		if (s.length > 1) {
			s.shift();
			this.def.setType.call(this,s.shift());
			//if (s.length) this.geo.lat = s.shift();
			//if (s.length) this.geo.lng = s.shift();
		}		
	},
	"setType":function(type) {
		for (var i = 0; i < this.typeEl.options.length; i++) {
			if (this.typeEl.options[i].value == type) {
				this.typeEl.selectedIndex = i;
				break;
			}
		}
		this.typeV = type;
	},
	"update":function(type) {
		if (type !== undefined) this.typeV = type;
		if (type !== undefined) {
			this.applyValue(this.el.value.split(";").join("%3B")+";"+this.typeV);
		} else {
			this.setValue(this.el.value.split(";").join("%3B")+";"+this.typeV);
		}
	},
	"change":function(text) {
		this.def.update.call(this);
	}
};

