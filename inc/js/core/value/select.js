sl.valueDef.select = {
	"fieldType":"select",
	"toString":function() {
		if (this.options) {
			if (sl.typeOf(this.options) == "array") {
				if (!isNaN(Number(this.value)) && this.options[Number(this.value)] != undefined) return this.options[Number(this.value)];
			} else if (this.options[this.value] != undefined) {
				return this.options[this.value];
			}
		}
		return String(this.value);
	},
	"fromString":function(s) {
		if (sl.typeOf(this.options) == "array" && !this.indexIsValue) return s; 
		var norm = s.charNormalize().replace(/[^\w\d]+/gi,"").toLowerCase();
		if (sl.typeOf(this.options) == "array") {
			for (var i = 0; i < this.options.length; i++) {
				if (norm == this.options[i].charNormalize().replace(/[^\w\d]+/gi,"").toLowerCase()) return i;
			}
		} else {
			for (var i in this.options) {
				if (norm == this.options[i].charNormalize().replace(/[^\w\d]+/gi,"").toLowerCase()) return i;
			}		
		}
		return s;
	}
};
