sl.valueDef.group = {
	"fieldType":"group",
	"toString":function() {
		if (v == "") return "";
		var v = String(this.value).split(",");
		return v[0] + (v.length > 1 ? " (+"+(v.length - 1)+")" : "");
	},
	"fromString":function(s) {
		return s;
	}	
};
