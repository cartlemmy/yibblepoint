sl.valueDef.object = {
	"fieldType":"text",
	"checking":false,
	"toString":function(cb) {
		var id = Number(this.value);
		if (!isNaN(id) && id > 0) {
			if (cb) {
				var name;
				if ((name = sl.loadObjectName(this,id,cb,!!this.el)) === false) {
					return "en-us|Loading...";
				} else return name;
			}
			return "#"+id;
		}
		return typeof(this.value) == "string" && this.value !== "0" ? String(this.value).split(";",2).pop() : "";
	},
	"fromString":function(s) {
		return s;
	}
};

sl.valueDef.objectDropDown = sl.valueDef.object;
