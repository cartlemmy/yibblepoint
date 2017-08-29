sl.fileManagers = {};

sl.fileManager = function(o) {
	var self = this;
	sl.initSlClass(this,"file-manager");

	self.setFile = function(file) {
		if (file == self.file) return;	
		self.ready = false, name = file.type.safeName();
		self.file = file;
		if (file) {
			sl.require("js/file/"+name+".js",function(failed){
				if (failed) {
					name = file.type.split("/").shift().safeName();
					sl.require("js/file/"+name+".js",function(failed){
						if (failed) {
							self.error("there is no manager for "+file.type);
						} else {
							sl.fileManagers[name].call(self);
							self.ready = true;
						}
					});
				} else {
					sl.fileManagers[name].call(self);
					self.ready = true;
				}
			});
		}
	};
	
	self.setValues({
		"ready":false
	});
	
	if (o) self.setValues(o);
};

