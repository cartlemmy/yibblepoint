sl.fieldDef.image = {
	"init":function() {	
		var self = this;
		var lo;
		var allowedTypes = ["image/jpeg","image/png","image/gif"];
		
		var thumbCont = sl.dg("",self.contEl,"div",{"className":"thumb"});
		self.thumbEl = sl.dg("",thumbCont,"img",{});
		self.el = sl.dg("",self.contEl,"input",{"type":"file"});
		
		sl.require(["js/core/mime.js","js/file/manager.js"],function(){
			self.el.addEventListener("change",function(e){
				if (e.target.files[0]) {
					self.imageFile = e.target.files[0];
					
					if (allowedTypes.indexOf(self.imageFile.type) != -1) {
						self.manager = new sl.fileManager({"file":self.imageFile});
						
						
						self.manager.addEventListener("progress",function(t,prog) {
							if (!lo) lo = new sl.loadingOverlay({"el":self.view.elInner});
							lo.progress(prog[0],prog[1],"en-us|Uploading Image...");
						});
	
						self.manager.addEventListener("load",function(t,res){
							if (lo) {
								lo.loaded();
								lo.destruct();
							}
							self.applyValue(self.imageFile.name+";"+self.imageFile.type+";"+self.imageFile.size+";"+res.md5+";"+res.dimensions+";"+res.thumb+";"+sl.config.parentUser);
						});
					} else {
						self.view.elementMessage(self.el,sl.format("en-us|%% is not an supported image format, please choose another file.",sl.mimeName(self.imageFile.type)),10);
					}
				}			
			},false);
		});
		return false;
	},
	"setValue":function(value) {
		if (value && this.el) {
			var parts = sl.delimToObject(value,["name","type","size","md5","dimensions","thumbHead","thumb","user"]);
			this.parts = parts;
			this.thumbEl.src = parts.thumbHead+";"+parts.thumb;
		}				
		return true;
	}
};
