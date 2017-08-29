sl.valueDef.image = {
	"init":function(){
		var self = this;
		self.getUrl = function() {
			if (!self.value) return false;
			var parts = sl.delimToObject(self.value,["name","type","size","md5","dimensions","thumbHead","thumb","user"]);
			return parts.type ? "our-files/"+parts.type.split("/").shift()+"/"+parts.md5+"."+parts.type.split("/").pop() : false;
		}
	},
	"fieldType":"text",
	"checking":false,
	"toString":function(cb) {
		return typeof(this.value) == "string" ? String(this.value).split(";",2).shift() : "";
	},
	"fromString":function(s) {
		return s;
	}
};
