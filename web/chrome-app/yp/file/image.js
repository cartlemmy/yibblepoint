sl.fileManagers["image"] = function() {
	var self = this;
	
	var pos = 0, partSize = 1024 * 128;
	
	function getPart(pos,len,cb) {
		var reader = new FileReader();
		var blob = self.file.slice(pos, pos + len);
		reader.onloadend = function(e) {
			if (e.target.readyState == FileReader.DONE) {
				curPartPos = pos;
				curPart = e.target.result;
				cb(e.target.result);
			}
		};
		reader.readAsDataURL(blob);
	};
	
	self.init = function() {
		function process() {
			getPart(pos,partSize,function(part){
				sl.coreOb.net.send("file-store",{"pos":pos,"uid":sl.coreOb.net.uniqueID+"-"+self.handle,"type":self.file.type,"size":self.file.size,"data":part.split(",").pop()},{"queueTime":0},function(response){
					delete part;
					if (response && response.success) {
						self.dispatchEvent("progress",[pos,self.file.size]);
						if (response.md5) {
							if (!response.thumb) {
								sl.require("js/ui/generateThumb.js",function(){
									sl.generateThumb(self.file,192,function(res){
										response.dimensions = res.dimensions;
										response.thumb = res.thumb;
										self.dispatchEvent("load",response);
									});
								});
								return;
							}
							//Done
							self.dispatchEvent("load",response);
							return;
						}
						setTimeout(process,5);
					} else {
						//TRY AGAIN?
					}
				});
				pos += partSize;
			});
		};
		pos = 0;
		process();
	};

	self.init();
};
