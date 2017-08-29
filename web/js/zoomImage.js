function zoomImage(el) {
	var src = el.src.replace(/\.[\d]+(w|h)/,'.1024w'),
		zi = document.getElementById('zoom-image'),
		zic = document.getElementById('zoom-image-cont');
	
	zic.style.display = "block";
	zi.src = src;

	zi.onload = function() {
		if (!zi.naturalWidth || !zic.offsetWidth) return;

		var maxW = zic.offsetWidth - 40, maxH = zic.offsetHeight - 40,
		w = zi.naturalWidth, h = zi.naturalHeight,
		nw = w, nh = h;
		if (nw / nh > maxW / maxH)	{
			if (nw > maxW) {
				w = maxW;
				h = maxW * (nh / nw);
			}
		} else {
			if (nh > maxH) {
				h = maxH;
				w = maxH * (nw / nh);
			}
		}
		zi.style.width = w+"px";
		zi.style.height = h+"px";
		zi.style.left = Math.round((zic.offsetWidth - w) / 2)+"px";
		zi.style.top = Math.round((zic.offsetHeight - h) / 2)+"px";
	};
}

function hideZoomImage() {
	document.getElementById('zoom-image-cont').style.display = "none";
	document.getElementById('zoom-image').src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
}

(function(){
	function getScrollTop(){
		if (typeof pageYOffset!= 'undefined'){
			return pageYOffset;
		} else {
			var B = document.body, D = document.documentElement;
			D = (D.clientHeight) ? D : B;
			return D.scrollTop;
		}
	}

	function zoomImageScroll() {
		document.getElementById('zoom-image-cont').style.top = getScrollTop()+"px";
	}
	
	window.addEventListener("scroll",zoomImageScroll);
	window.addEventListener("load",function(){
		document.getElementById('zoom-image-cont').addEventListener("click",hideZoomImage);
		zoomImageScroll();
	});
})();
