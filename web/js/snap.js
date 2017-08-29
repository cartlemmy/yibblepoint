// Requires jquery.mousewheel.min.js


function getScrollTop(){
	return document.body.scrollTop || document.documentElement.scrollTop; 
};

function setScrollTop(y){
	if (document.documentElement) document.documentElement.scrollTop = y;
	document.body.scrollTop = y;
};

(function(){
	var offset, snapPoints = [], curPoint = 0, curY, yTarg = 0, stt,
		noCancel = false,newTs, lastTs = ts(), yVel = 0, touching = false;
	
	function scrollCancel(force) {
		if (yVel != 0) return;
		
		if (yVel == 0 && (force === true || !noCancel)) {
			yTarg = getScrollTop();
			if (stt) clearTimeout(stt);
		}
		noCancel = false;
	};
	
	function ts() {
		return (new Date()).getTime();
	}
	
	function scrollTo(dy) {
		var v;
		progScroll = false;

		newTs = ts();
		var scrolling, D = Math.min(1,Math.min(50,newTs - lastTs)/125);
		
		if (typeof(dy) == "number") {
			scrolling = yVel != 0;
			if (!scrolling) curY = getScrollTop();
			yVel *= 0.8;
			yVel += dy;
			if (scrolling) return true;
		} else if (dy === undefined) yVel = 0;
		
		if (Math.abs(yVel) > 0) {
			if (!window._LOCK_SCROLL) {
				yVel *= 0.9;
				curY += yVel * D * 2;
				if (Math.abs(yVel) < 0.1) {
					yVel = 0;
					return;
				}
			}
		} else {
			v = (yTarg - curY);
			if (v > 150) v = 150;
			if (v < -150) v = -150;
			v *= D;
			
			curY = curY + v;
					
			if (Math.abs(curY - yTarg) < 1) {
				noCancel = true;
				setScrollTop(yTarg);
				return;
			}
		}
		
		noCancel = true;
		setScrollTop(Math.round(curY));
		stt = setTimeout(function(){scrollTo(true)},20);
		lastTs = newTs;
		
		return yVel != 0;
	};
	
	window.scrollSnap = function(y,pos) {
		return;
		var el;
		if (typeof(y) == "string") {
			el = $(y);
			if (!el.length) return;
			
			switch (pos?pos:'top') {
				case 'bottom':
					y = el.offset().top + el.outerHeight();
					break;
					
				case 'mid':
					y = el.offset().top + el.outerHeight() / 2;
					break;
				
				default: case 'top':
					y = el.offset().top;
					break;
			}			
		}
		
		yVel = 0;
		scrollCancel(true);
		yTarg = y;
		noCancel = true;
		curY = getScrollTop();
		lastTs = ts();
		scrollTo();
	};
	
	function scroll(dy) {
		
		scrollCancel(true);
		
		findCurPoint(dy);
				
		var y, wh = $(window).height(), next = dy < 0 ? curPoint : (curPoint >= snapPoints.length - 1 ? false : curPoint + 1);
	
		if (next === false) return scrollTo(dy);
		
		y = snapPoints[next];
		
		if (Math.abs(y - getScrollTop()) / wh <= (dy >= 0 ? 1 : 0.5)) {
			yVel = 0;
			scrollCancel(true);
			yTarg = y;
			noCancel = true;
			setScrollTop(getScrollTop() + dy);
			curY = getScrollTop();
			lastTs = ts();
			scrollTo();
			return true;
		}	
		return scrollTo(dy);
	};
	
	function addSnapPoint(y) {
		if (snapPoints.indexOf(y) == -1) snapPoints.push(y);
		snapPoints.sort(function(a,b){
			return a < b ? -1 : 1;
		});
	};
	
	var lockedDelta = 0;
	
	function doScrollEvt(e) {
		if (window._LOCK_SCROLL) {
			lockedDelta += e.deltaY;
			
			if (lockedDelta > $(window).height() || lockedDelta < 0-$(window).height()) {
				window._LOCK_SCROLL = 0;
			} else {
				e.preventDefault();
				return;
			}
		}
		lockedDelta = 0;
		if (e.cancelable && scroll( e.deltaY )) e.preventDefault();
	}
	
    
    $(window).mousewheel(function(e){
		e.deltaY *= e.deltaFactor * -1.5;
		doScrollEvt(e);
	});
    
    var touchLast = 0, deltaY = 0;
    
    function touch(e) {
		var touches = e.changedTouches, t = touches[0];
		
		if (touchLast - t.clientY != 0) deltaY = touchLast - t.clientY;
		
		if (touches.length > 1) return;
		
		switch (e.type) {
			case 'touchstart':
				touching = true;
				break;
			
			case 'touchend':
			case 'touchleave':
			case 'touchcancel':
				touching = false;
				if (deltaY < 0 && getScrollTop() < 10) break;
				e.deltaY = deltaY;
				//doScrollEvt(e);
				break;
		}
		touchLast = t.clientY;
	}	

	document.body.addEventListener("touchstart", touch, false);
	document.body.addEventListener("touchend", touch, false);
	document.body.addEventListener("touchcancel", touch, false);
	document.body.addEventListener("touchleave", touch, false);
	document.body.addEventListener("touchmove", touch, false);

    function findCurPoint(dy) {
		var i;
		
		for (i = 0; i < snapPoints.length; i ++) {
			if (getScrollTop() + dy >= snapPoints[i]) curPoint = i;
		}
	};
	
    function refresh() {
		snapPoints = [0];
		$('.snap-scroll').each(function(i,el){
			offset = $(el).offset();
			addSnapPoint(offset.top);
			addSnapPoint(offset.top + $(el).outerHeight(true));
			$(el).find('img').load(refresh);
		});
		findCurPoint(0);
	};
	
	$( document ).ready(refresh);
	$( window ).resize(refresh);	
	
	$( window ).scroll(scrollCancel);
	
})();
