sl.valueDef.extra = {
	"toString":function() {
		var self = this;
		var exclude = self.exclude ? self.exclude : [];
		exclude.push("_KEY");
		exclude.push("_NAME");
		exclude.push("id");
		
		var fields = self.fields ? self.fields : {};
		
		var out = [];
		for (var n in self.value) {
			if (exclude.indexOf(n) == -1) {
				var field = fields[n] ? fields[n] : {"type":"text"};
				if (!field.label) field.label = n.camelCaseToReadable();
				switch (field) {
					default:
						out.push(field.label+": "+self.value[n]);
						break;
				}
			}
		}	
		return out.join("\n");
	}
};
