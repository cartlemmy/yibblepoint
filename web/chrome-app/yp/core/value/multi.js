sl.valueDef.multi = {
	"fieldType":"multi",
	"toString":function() {
		return String(this.value);
	},
	"fromString":function(s) {
		return s;
	},
	"encode":function(v) {
		return v.split("\n").join("%OA");
	},
	"decode":function(v) {
		return v.split("%OA").join("\n");
	}
	
};
