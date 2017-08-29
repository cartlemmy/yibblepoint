sl.valueDef.currency = {
	"toString":function() {		
		if (!this.value) return "";
		var value = this.value.split(" ",2);
		var type = value.pop();
		value = value[0];
		
		if (!sl.data.currency[type]) type = sl.config.international.currency;
		
		var def = sl.data.currency[type];
		
		if (type == "_YC") return String(value)+"en-us| Credit"+(value==1?"":"s");
		return def[3]+Number(value).format(def[2])+" "+type;
	},
	"fromString":function(text) {
		var value = text.match(/[\d\.\-\(\)\,]+/);
		value = value ? value[0] : "0";
		
		var type = text.match(/[\w]{3}/);
		type = type ? type[0].toUpperCase() : "";
		
		if (!sl.data.currency[type]) type = sl.config.international.currency;
		
		var def = sl.data.currency[type];

		var cs = def[2].match(/(\.|\,)/)[0] == ",";		
		
		value = value.split(cs?",":".").join("");
		if (cs) value = value.split(",").join(".");
		
		if (value.indexOf("(") != -1) {
			value = "-"+value.split("(").join("").split(")").join("");
		}
		
		return value+" "+type;
	}
};
