function ypPhotoId(params) {
	var self = this;
	
	self.scan = function(im,cb) {
		function go() {
			self.canvas = document.createElement('CANVAS');
			self.canvas.width = self.srcIm.width;
			self.canvas.height = self.srcIm.height;
			
			self.ctx = self.canvas.getContext("2d");
			
			self.ctx.drawImage(self.srcIm, 0, 0);			
			
			self.id = 
		}
		
		if (typeof(im) == "string") {
			self.srcIm = new Image();
			self.srcIm.src = im;
			if (self.srcIm.complete) {
				go();
			} else self.srcIm.addEventListener('load',go);
		}
	}
}
