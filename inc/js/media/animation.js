sl.animation = function(o) {
	var self = this;
	sl.initSlClass(this,"animation");

	self.init = function() {
		
	};
		
	self.setValues({
		"el":null,
		"width":null,
		"height":null,
		"data":{
			"resources":{},
			"layers":[]
		}
	});
	
	if (o) self.setValues(o);
	
	self.init();
};
