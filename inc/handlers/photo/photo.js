var timer = null, img = null, imgCont = null, prevBut = null, nextBut = null,
	currentImageI = 0, imScan = [0,0], imScanEnable = [true,true], imageEls = [], scanning = true;

function repos(force) {	
	if (imgCont) {
		imgCont.style.top = document.body.scrollTop+"px";
		imgCont.style.left = document.body.scrollLeft+"px";
	}

	if (force !== true) {
		scanning = true;
		imScanEnable = [true,true];
	}
	
	var src;
	if (!imageEls.length) {
		var c = document.getElementsByTagName("img");
		for (var i = 0; i < c.length; i++) {
			if (c[i].getAttribute("data-load-vis")) {
				imageEls.push(c[i]);
			}
		}
	}
	if (!sl.getElementPosition) {
		timer = setTimeout(function(){repos(force);},50);
		return;
	}
	
	function step() {
		var found = false;
		for (var dir = 0; dir < 2; dir++) {
			if (imScanEnable[dir]) {
				imScan[dir] += (dir ? -1 : 1) * (scanning ? 5 : 1);
				if (imScan[dir] < 0 || imScan[dir] >= imageEls.length) {
					imScan[dir] = imScan[dir ^ 1] + (dir ? -1 : 1);
					imScanEnable[dir] = false;			
					continue;		
				}
				var i = imScan[dir];
				if (scanning) {
					if (check(imageEls[i])) {
						var c = Math.max(1,i);
						imScan[0] = c - 1;
						imScan[1] = c;
						imScanEnable = [true,true];
						scanning = false;
					}
					found = true;
				} else {
					if (check(imageEls[i])) {
						found = true;
					} else {
						imScanEnable[dir] = false;
					}
				}
			}
		}
		
		//console.log(imScan.join(",")," ",scanning?"SCANNING":""," ",found?"FOUND":"");
		
		if (!found) {
			scanning = true;
			imScanEnable = [true,true];
			var c = Math.max(1,Math.round((imScan[0] + imScan[1]) / 2));
			imScan[0] = c - 1;
			imScan[1] = c;
			return false;
		}
		return true;
	};
	
	for (var i = 0; i < 50; i++) {
		if (!step()) return;
	}
	
	if (timer) clearTimeout(timer);
	timer = setTimeout(function(){repos(true);},20);
};

function check(el) {
	var y1 = document.body.scrollTop - 300, y2 = y1 +	 window.innerHeight + 600;
	pos = sl.getElementPosition(el);
	var show = pos.y - 120 > y1 && pos.y < y2;
	el.src = show ? el.getAttribute("data-load-vis") : "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
	return show;
};

window.addEventListener("scroll",repos);
sl.addLoadListener(repos);

function show(id) {
	currentImageI = photos.indexOf(id);
	if (!imgCont) {
		imgCont = sl.dg("",document.body,"div",{"style":{"position":"absolute","top":"0px","left":"0px","backgroundColor":"rgba(255,255,255,0.8)"}});
		img = sl.dg("",imgCont,"img",{"style":{"position":"absolute"}});
				
		prevBut = sl.dg("",imgCont,"div",{"className":"photo-button","style":{"left":"0px"}});
		sl.dg("",prevBut,"div",{"innerHTML":"&lt;"});
		prevBut.addEventListener("click",function() {
			currentImageI--;
			if (currentImageI < 0) currentImageI = photos.length - 1;
			show(photos[currentImageI]);
		});
		
		nextBut = sl.dg("",imgCont,"div",{"className":"photo-button","style":{"right":"0px"}});
		sl.dg("",nextBut,"div",{"innerHTML":"&gt;"});
		nextBut.addEventListener("click",function() {
			currentImageI++;
			if (currentImageI >= photos.length) currentImageI = 0;
			show(photos[currentImageI]);
		});
		
		var close = sl.dg("",imgCont,"a",{"href":"javascript:;","innerHTML":"CLOSE","style":{"display":"block","padding":"20px;","backgroundColor":"#FFF","position":"absolute","top":"0px","left":"0px","fontSize":"32px"}});
		close.addEventListener("click",function() {
			imgCont.style.display = "none";
		});
	}
	
	imgCont.style.display = "";
	
	var w = window.innerWidth, h = window.innerHeight;
	
	imgCont.style.width = w+"px";
	imgCont.style.height = h+"px";
	
	img.src = "?ph="+id;
	img.style.maxWidth = w+"px";
	img.style.maxHeight = h+"px";
	img.onload = function() {
		if (img.naturalWidth / img.naturalHeight > w / h) {
			img.style.left = "0px";
			img.style.top = Math.round((h - img.offsetHeight) / 2)+"px";
		} else {
			img.style.left = Math.round((w - img.offsetWidth) / 2)+"px";
			img.style.top = "0px";
		}		
	};
	
	repos();
}
