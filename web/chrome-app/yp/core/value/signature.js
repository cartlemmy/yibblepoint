sl.valueDef.signature = {
	"toString":function() {
		if (!this.value) return false;
		var d = this.value.split(";");
		d.shift();
		return "<img src=\""+d.join(";")+"\" style=\"height:"+(this.height?this.height:sl.innerSize(this.el).height)+"px\">";
	},
	"fromString":function(text) {
		console.log(text);
		return text;
	}
};
