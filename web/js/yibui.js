function getScrollTop(){
	return document.body.scrollTop || document.documentElement.scrollTop; 
};

function setScrollTop(y){
	if (document.documentElement) document.documentElement.scrollTop = y;
	document.body.scrollTop = y;
};

(function(){
	function resize(e){
		var pEl, size = 0, rem = 0, fillSize = 0;
		
		$(".vertical").each(function(i,el){
			pEl = el.parentNode;
			if ($(el).hasClass('fill')) {
				$(el).css('height','');
				fillSize += $(el).outerHeight();
			} else {
				size += $(el).outerHeight();
			}
		});
		
		rem = $(pEl).height() - size;
		
		$(".vertical").each(function(i,el){
			if ($(el).hasClass('fill')) {
				$(el).height(rem * (fillSize == 0 ? 1 : ($(el).outerHeight() / fillSize)));
			}
		});
		
		
		$(".horizontal").each(function(i,el){
			pEl = el.parentNode;
			if ($(el).hasClass('fill')) {
				$(el).css('width','');
				fillSize += $(el).outerWidth();
			} else {
				size += $(el).outerWidth();
			}
		});
		
		rem = $(pEl).width() - size;
		
		$(".horizontal").each(function(i,el){
			if ($(el).hasClass('fill')) {
				$(el).width(rem * (fillSize == 0 ? 1 : ($(el).outerWidth() / fillSize)));
			}
		});
		
		
		$(".cell-cont").each(function(i,pEl){
			if (pEl.getAttribute('data-ideal-width')) {
				var i, els = pEl.childNodes, c,
					iw = toPx(pEl.getAttribute('data-ideal-width'),pEl),
					pw = $(pEl).width(), perRow = Math.max(1,Math.floor(pw / iw)),
					w = pw / perRow, x = 0, y = 0, h, rh, missing = 0,
					rows = 0, cols = 0, addPerCol = 0, a = 0;
				

				for (i = 0; i < els.length; i++) {
					c = els[i];
					if (c.nodeName == "A") c = c.childNodes[0];
					if (c.nodeType == 1 && $(c).width()) {
						//$(c).css('height','1px');
						$(c).css('width',(Math.round(x+w)-Math.round(x))+'px');
						x += w;
						cols ++;
					}				
				}
				
				if (!$(pEl).hasClass("same-width")) {
					rows = Math.ceil(x / pw);					
					missing = x % pw == 0 ? 0 : (pw - (x % pw)) / w;
					
					addPerRow = missing / rows;
					
					h = $(pEl).hasClass("fill-height") ? $(pEl).height() / rows : false;
		
					x = 0; y = h;
					for (i = 0; i < els.length; i++) {
						c = els[i];
						if (c.nodeName == "A") c = c.childNodes[0];
						if (c.nodeType == 1 && $(c).width()) {
							if (Math.round(x % pw) == 0) {
								a += addPerRow;
								w = pw / (perRow -(Math.round(a + addPerRow) - Math.round(a)));
								
								x = 0;
								rh = (Math.round(y+h)-Math.round(y));
								y += h;
							}
							
							$(c).css('width',(Math.round(x+w)-Math.round(x))+'px');							
							x += w;
							if (h) $(c).css('height',rh+'px');
						}
					}
				}
			}
		});
		
		$(".height-match-width").each(function(i,el){
			$(el).height($(el).width());
		});
		
		$(".width-match-height").each(function(i,el){
			$(el).width($(el).height());
		});
		
		$(".contain").each(function(i,el){
			var pEl = el.parentNode,pw = $(pEl).innerWidth(), ph = $(pEl).innerHeight(), pr = pw / ph,
			cw = $(el).width(), ch = $(el).height(), cr = cw / ch;
			
			if (cr > pr) {
				$(el).css({
					"width":"100%",
					"height":"auto",
					"left":0,
					"top":Math.round((ph - (pw / cr)) / 2)
				});
			} else {
				$(el).css({
					"width":"auto",
					"height":"100%",
					"left":Math.round((pw - (ph * cr)) / 2),
					"top":0
				});
			}
			
		});
	}
	
	window.yibUIRefresh = resize;
	
	$(".blur-bg").each(function(i,el){
		$(el.parentNode).mouseover(function(){
			$(el).stop().fadeIn();
		});
		$(el.parentNode).mouseout(function(){
			$(el).stop().fadeOut();
		});
		el.parentNode.style.backgroundImage = el.style.backgroundImage;
	});
	
	function toPx(m,el) {
		m = m.match(/(\-?[\d\.]+)(.*)/);
		switch (m[2]) {
			case "": case "px": return Number(m[1]);
			case "%": return $(el).width() * Number(m[1]) / 100;
		}
		return 0;
	}
	
	window.addEventListener('resize',resize);
	window.addEventListener('load',resize);
	
	$('img').each(function(i,el){
		if (!el.complete) el.addEventListener('load',resize);
	});
	
	var sources = [], yft = 'date-range,date'.split(',');
	
	window.yibFieldScan = function() {
		$('.yibfield').each(function(i,el){
			if (el._YIB_FIELD) return;
			
			var found = false;
			for (i = 0; i < yft.length; i++) {
				if ($(el).hasClass(yft[i])) {
					require('field/'+yft[i],found ? null : el);
					found = true;
				}
			}
		});
	};
	window.yibFieldScan();
	
	function require(source,params,cb,folder,noPload) {
		var desc, i, sp = source.split('/');
		
		if (!noPload && sp.length > 1) {
			sp.pop();
			require(sp.join('/'),null,function(){
				require(source,params,cb,false,true);
			},true);
			return;
		}
		
		function callOne(p) {
			var c = null, n = (
			function(){
				var i, s = source.split(/[^A-Za-z0-9]+/);
				for (i = 0; i < s.length; i++) {
					s[i] = s[i].charAt(0).toUpperCase()+s[i].substr(1);
				}
				return s.join('');
			})();
			
			if (window[n]) {
				c = new window[n](p[0]);
			}
			if (p[1]) p[1](c);
		}
		
		if (sources[source]) {
			desc = sources[source];
		} else {
			desc = {
				"loaded":false,
				"load":function(){
					desc.loaded = true;
					for (i = 0; i < desc.p.length; i++) {
						callOne(desc.p[i]);
					}
				},
				"s":document.createElement('SCRIPT'),
				"p":[]
			};
			desc.s.src = sl.config.webRelRoot+'js/'+source+(folder?'/Main':'')+'.js';
			document.body.appendChild(desc.s);
			if (desc.s.complete) {
				desc.load();
			} else {
				desc.s.addEventListener('load',desc.load);
			}
			sources[source] = desc;
		}
		
		if (params || cb) {
			if (desc.loaded) {
				callOne([params, cb]);
			} else {
				desc.p.push([params, cb]);
			}
		}
	}
	
	window.yibRequire = require;
	 
	window.yibPopover = function(id, pel, content) {
		if (!$('#'+id).length) {
			$(document.body).append('<div id="'+id+'" class="yibui-popover"><div><div class="close">x</div>'+(content !== undefined ? content : '')+'</div></div>');
		}
		
		function fixPos() {
			var el = $('#'+id)[0], pOff = $(pel).offset(), cOff = $(el).offset(), screenMid = getScrollTop() + $(window).height() / 2;

			if (screenMid < pOff.top + $(pel).height() / 2) {
				$('#'+id).css({
					'left':Math.round(pOff.left+$(pel).width()/2),
					'top':pOff.top
				});
				$('#'+id+">div").css({
					'top':'',
					'bottom':0,
					'left':0-($('#'+id+">div").outerWidth()/2)
				});
			} else {
				$('#'+id).css({
					'left':Math.round(pOff.left+$(pel).width()/2),
					'top':pOff.top+$(pel).outerHeight()
				});
				$('#'+id+">div").css({
					'top':0,
					'bottom':'',
					'left':0-($('#'+id+">div").outerWidth()/2)
				});
			}
			
			cOff = $('#'+id+">div").offset();
			cOff.right = cOff.left + $('#'+id+">div").outerWidth();
			
			if (cOff.left < 10) $('#'+id+">div").css('left',Number($('#'+id+">div").css('left').replace('px','')) + (10 - cOff.left));
			if (cOff.right > ($(window).width() - 10)) $('#'+id+">div").css('left',Number($('#'+id+">div").css('left').replace('px','')) - (cOff.right - ($(window).width() - 10)));
			
		}
		
		$('#'+id+">div")[0].yibuiFixPos = fixPos;
		
		$('#'+id+" .close").click(function(){
			$('#'+id+">div")[0]._ACTIVE = false;
			$('#'+id).stop().fadeOut();
		});
		
		$('#'+id).stop().fadeIn();
		fixPos();
		
		$('#'+id+">div")[0]._ACTIVE = true;
		
		return $('#'+id+">div")[0];
	}	
})();
