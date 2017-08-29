sl.valueDef.localTime = {
	"toString":function() {
		return sl.getLocalTime(String(this.value));
	},
	"autoRefresh":1000,
	"fromString":function(text) {
		return "";
	}
};
