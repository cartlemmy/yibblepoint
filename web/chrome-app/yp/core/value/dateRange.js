sl.valueDef.dateRange = {
	"fieldType":"text",
	"init":function(){
		this.range = [new sl.value({"type":"date","nullLabel":"Any"}),new sl.value({"type":"date","nullLabel":"Any"})];
	},
	"toString":function(cb) {
		return this.range[0].toString()+" to "+this.range[1].toString();
	},
	"fromString":function(s) {
		s = s.replace(/\-/g,'/').replace(/\s+to\s+/i,"-").split("-");

		return this.value = this.range[0].fromString(s[0])+"-"+this.range[1].fromString(s[1]);
	}
};

sl.valueDef.objectDropDown = sl.valueDef.object;
