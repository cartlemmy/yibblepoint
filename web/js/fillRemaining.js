function fillRemaining(elId, verticalElIds) {
	var el = document.getElementById(elId), i;
	var verticalEls = [];
	
	if (verticalElIds.length) {
		for (i = 0; i < verticalElIds.length; i++) {
			verticalEls.push(document.getElementById(verticalElIds[i]));
		}
	}
	
	function resize() {
		var h = window.innerHeight;
		for (i = 0; i < verticalEls.length; i++) {
			h -= sl.getTotalElementSize(verticalEls[i]).height; 
		}
		el.style.height = h+"px";
	};
	
	resize();
	window.addEventListener("resize",resize);
};
window.noFooterFix = true;
