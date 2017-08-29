sl.valueDef.address = {
	"fieldType":"text",
	"checking":false,
	"toString":function() {
		return unescape(String(this.value).split(";").shift());
	},
	"fromString":function(s) {
		return s;
	}
};
