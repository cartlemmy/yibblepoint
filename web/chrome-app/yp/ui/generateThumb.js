sl.generateThumb = function(file,size,cb) {
	var thumbReader = new FileReader();
	thumbReader.onloadend = (function(e) {
		var thumb = new Image;
		thumb.src = e.target.result;
		
		setTimeout(function(){
			var canvas = document.createElement('canvas');
			canvas.setAttribute('width',size);
			canvas.setAttribute('height',size);
			
			var w,h;
			var ratio = thumb.width / thumb.height;
			var srcSize = ratio > 1 ? thumb.height : thumb.width;
			
			var ctx = canvas.getContext('2d');
			ctx.drawImage(
				thumb,
				Math.round((thumb.width-srcSize)/2),Math.round((thumb.height-srcSize)/2),srcSize,srcSize,
				0,0,size,size
			);
			
			cb({
				"dimensions":thumb.width+"x"+thumb.height,
				"thumb":canvas.toDataURL("image/jpeg",0.7)
			});			
		},50);		
	});
	thumbReader.readAsDataURL(file);
	return;
};
