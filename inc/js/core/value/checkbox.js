sl.valueDef.checkbox = {
	"fieldType":"checkbox",
	"toString":function() {
		return !!Number(this.value) ? "✓" : "";
	},
	"fromString":function(s) {
		return s == "✓" || s == "on" || s == 1 || s == true || s == "yes";
	}
};
