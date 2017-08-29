sl.valueDef.percent = {
	"toString":function() {
		var v = (this.value * 100).toString().split(".");
		var decimal = 2;
		if (v.length == 2) {
			v[1] = v[1].substr(0,decimal);
		}
		return v.join(".")+"%";
	}
};
