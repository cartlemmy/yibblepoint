/* Attempts to keep the specified focal point of the background image visible
 * 
 * Set element attributes as follows:
 * 
 * data-bg-focal-x and/or data-bg-focal-y	Sets the position of the focal point of the background image (this is the pixel position of the source image)
 * data-bg-center-x	and/or data-bg-center-y	Sets the desired center as a percentage of the element the background image is in
 */

(function(){
	function init() {
		var focalBG = [];
		
		function resized() {		
			var i, o, elH, elH, elR, contW, contH, contR, xPad, yPad, xFocal, yFocal, resizedW, resizedH, resizeR;
			for (i = 0; i < focalBG.length; i++) {
				o = focalBG[i];
				
				elW = o[1].naturalWidth;
				if (!elW) continue;
				
				elH = o[1].naturalHeight;
				elR = elW / elH;
				
				contW = $(o[0]).width();
				contH = $(o[0]).height();
				contR = contW / contH;
				
				switch ($(o[0]).css("background-size")) {
					case "cover":
						if (elR > contR) {
							resizedW = Math.round(contH * elR);
							resizedH = contH;
							resizeR = contH / elH;
							
							xPad = resizedW - contW;
							yPad = 0;
						} else {
							resizedW = contW;
							resizedH = Math.round(contW / elR);
							resizeR = contW / elW;
							
							xPad = 0;
							yPad = resizedH - contH;
						}
						
						xFocal = o[2] === false ? resizedW / 2 : o[2] * resizeR;
						yFocal = o[3] === false ? resizedH / 2 : o[3] * resizeR;						
						
						$(o[0]).css({"background-position":Math.round(Math.min(0,Math.max(contW-resizedW,((contW * (o[4] ? o[4] : 0.5)) - xFocal))))+"px "+Math.round(Math.min(0,Math.max(contH-resizedH,((contH * (o[5] ? o[5] : 0.5)) - yFocal))))+"px"});
						break;
				}
			}
		};
		
		$('*').each(function(i,el){
			var
				focalX = el.getAttribute('data-bg-focal-x'),
				focalY = el.getAttribute('data-bg-focal-y'),
				centerX = el.getAttribute('data-bg-center-x'),
				centerY = el.getAttribute('data-bg-center-y');
			if (focalX || focalY) {
				var img, m = $(el).css("background-image").match(/url\(('|")?(.*?)('|")?\)/);
				if (m && m[2]) {
					img = new Image();
					img.src = m[2];
					$(img).load(resized);
					focalBG.push([
						el,
						img,
						focalX===undefined?false:Number(focalX),
						focalY===undefined?false:Number(focalY),
						centerX===undefined?false:Math.max(0,Math.min(1,Number(centerX) / 100)),
						centerY===undefined?false:Math.max(0,Math.min(1,Number(centerY) / 100))
					]);
				}
			}
			
		});
		
		$( document ).ready(resized);
		$( window ).resize(resized);
		window.focalBgUpdate = resized;
	};
	if (window._FL_INFO) {
		function check() {
			if (window._FL_COMPLETE) {
				init();
			} else {
				setTimeout(check,200);
			}
		}
		
		check();
	} else init();
})();
