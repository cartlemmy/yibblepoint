sl.valueDef.duration = {
	"t":[
		["en-us|Century","en-us|Centuries",3155811800],
		["en-us|Year","en-us|Years",31558118],
		["en-us|Day","en-us|Days",86400],
		["en-us|Hour","en-us|Hours",3600],
		["en-us|Minute","en-us|Minutes",60],
		["en-us|Second","en-us|Seconds",1],
		["en-us|Millisecond","en-us|Milliseconds",0.001],
	],
	"toString":function() {
		if (this.value == 0) return "en-us|None";
		
		var m = this.def.t.length - 1;
		var minUnit = m - (this.minUnit === undefined ? 0 : this.minUnit);
		var maxUnit = m - (this.maxUnit === undefined ? m : this.maxUnit);
		
		var v = this.value, rv = [];
		for (var i = 0; i < this.def.t.length; i++) {
			var t = this.def.t[i];
			if (v >= t[2] && i <= minUnit && i >= maxUnit) {
				var n = Math.floor(v / t[2]);
				rv.push(n == 1 ? "1 "+t[0] : n+" "+t[1]);
				v -= n * t[2];
			}
		}
		return rv.join(" ");
	}
};
