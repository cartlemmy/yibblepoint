

function ActivViewer(params) {	
	var self = this, sessCnt = 0, sessMap = {}, T, lastTS = ts(),
		canvas = false, ctx = [null,null,null], PI2 = Math.PI * 2,
		scaleW = 0, scaleH = 0;

	if (!params.overlayScale) params.overlayScale = 5;
	
	if (window.parent.top != window) return;
	
	function getScrollTop() {
		return document.body.scrollTop || document.documentElement.scrollTop; 
	};

	function ts() {
		return (new Date()).getTime() / 100;
	}
	
	function fromUnixTs(ts) {
		return Math.round(ts * 10);
	}
	
	function toUnixTs(T) {
		return T / 10;
	}
	
	var i, n, o;
	
	function FP(n) {
		return vars.indexOf(n) == -1 ? n : vars.indexOf(n);
	};
	
	function TP(i) {
		return vars[i];
	};
	
	function compA(A) {
		var n, rv = {};
		if (typeof(A) == "object") {
			for (n in A) {
				rv[FP(n)] = A[n];
			}
			return rv;
		}
		return 0;
	};
	
	function resized() {
		var i;
		self.w = $( document ).width();
		self.h = $( document ).height()
		if (!canvas) {
			$(document.body).append('<canvas id="_AV_CANVAS" style="display:none;position:absolute;top:0;left:0;z-index:1000"></canvas>');
			canvas = [$('#_AV_CANVAS')[0],document.createElement('CANVAS'),document.createElement('CANVAS')];
		}
		for (i = 0; i < 3; i++) {
			$(canvas[i]).width(self.w);
			$(canvas[i]).height(self.h);
		
			canvas[i].width = Math.round(self.w/params.overlayScale);
			canvas[i].height = Math.round(self.h/params.overlayScale);
		
			ctx[i] = canvas[i].getContext("2d");
			
			scaleW = canvas[i].width/self.w;
			scaleH = canvas[i].height/self.h;
			
			ctx[i].scale(scaleW,scaleH);
		}
	}
	
	self.showOverlay = function() {
		$(canvas).show();
	};
	
	self.parseEvent = function(sid, json) {
		try {
			event = JSON.parse(json);
		} catch (e) {
			console.log(e);
			console.log(json);
		}
		
		if (!sessMap[sid]) {
			sessCnt++;
			sessMap[sid] = new ActivViewerSession(self);
		}
		sessMap[sid].add(event);
	};
	
	self.anim = function() {
		var n, curTS = ts(), T = curTS - lastTS, sess;
		
		ctx[1].save();
		ctx[1].scale(1/scaleW,1/scaleH);
		ctx[1].clearRect(0, 0, canvas[1].width, canvas[1].height);
		ctx[1].globalAlpha = 0.195;
		
		ctx[1].drawImage(canvas[0],-1,0);
		ctx[1].drawImage(canvas[0],1,0);
		ctx[1].drawImage(canvas[0],0,-1);
		ctx[1].drawImage(canvas[0],0,1);
		
		ctx[1].globalAlpha = 0.4;
		ctx[1].drawImage(canvas[0],0,0);
		
		ctx[1].globalAlpha = 1;
		ctx[1].restore();
		
		var gradient;
		
		for (n in sessMap) {
			sess = sessMap[n];
			if (sess.noAnim) continue;
			sess.anim(T);
		
			gradient = ctx[1].createRadialGradient(sess.x, sess.y, 1, sess.x, sess.y, 30);
			gradient.addColorStop(0, 'rgba(128,255,0,1)');
			gradient.addColorStop(0.3, 'rgba(0,255,0,0.5)');
			gradient.addColorStop(1, 'rgba(0,255,0,0.05)');
			ctx[1].fillStyle = gradient;
		
			ctx[1].beginPath();
			ctx[1].arc(sess.x, sess.y, 30, 0, PI2);
			ctx[1].fill();
			
			if (sess.click) {
				sess.click = false;
				gradient = ctx[2].createRadialGradient(sess.x, sess.y, 1, sess.x, sess.y, 30);
				gradient.addColorStop(0, 'rgba(255,128,128,0.6)');
				gradient.addColorStop(0.2, 'rgba(255,0,0,0.6)');
				gradient.addColorStop(0.95, 'rgba(255,0,0,0.1)');
				gradient.addColorStop(1, 'rgba(0,255,0,0.05)');
				ctx[2].fillStyle = gradient;
		
				ctx[2].beginPath();
				ctx[2].arc(sess.x, sess.y, 50, 0, PI2);
				ctx[2].fill();
			}
			
			
		}
		
		ctx[0].save();
		ctx[0].scale(1/scaleW,1/scaleH);
		ctx[0].clearRect(0, 0, canvas[0].width, canvas[0].height);
		ctx[0].drawImage(canvas[1],0,0);
		ctx[0].drawImage(canvas[2],0,0);
		ctx[0].restore();
		lastTS = curTS;
	}
	
	self.play = function(secs) {
		if (!secs) secs = 0;
		var n;
		for (n in sessMap) {
			sessMap[n].seek(secs * 10);
			sessMap[n].play();
		}
		lastTS = ts();
		T = setInterval(self.anim,30);
		self.showOverlay();
	};
	
	function fetch(s) {
		var d = new Date(), page = window.location.href.split('//').pop().split('?').shift().split('#').shift().split('/');
		
		try {
			page.shift();
			page = page.join('/').replace(/^\/+|\/+$/gm,'').split('/').join('-2F');
		} catch (e) {
			console.log(e);
			console.log(window.location.href);
			console.log('');
		}
		
		$.ajax({
			url:params.u+"?t="+params.token+"&s="+s+"&l=50000&fetch="+d.getFullYear()+"%2f"+(d.getMonth()+1)+"%2fpages%2f"+encodeURIComponent(page),
			success: function(data) {
				var i, event;
				data = data.split("\n");
				if (data[0].charAt(0) == "[") {
					for (i = 0; i < data.length; i++) {
						self.parseEvent('U',data[i]);
					}
				} else {
					for (i = 0; i < data.length; i++) {
						event = data[i].split(':');
						if (event.length >= 2) self.parseEvent(event.shift(),event.join(":"));
					}
				}
				
				self.play();
			},
			error: function(e){
				console.log(e);
			}
		});
	}

	$( document ).ready(resized);
	$( window ).resize(resized);
	
	if (params.token) {
		fetch(0);
	}
			
};

window._ACTIVE_VS = 0;

function ActivViewerSession(viewer) {
	var self = this, 
		vars = ['x','y','child','top','scroll','move','sel','out','over','down','up','click','requested','load','size','n','href','w','h','exit','fp'],
		page = {"startTs":0,"e":[],"map":{}};
	
	self.vs = _ACTIVE_VS++;
	
	self.noMoreEvents = false;
	self.pos = 0;
	self.posI = 0;
	self.playTs = 0;
	self.x = -1;
	self.y = -1;
	self.tx = -1;
	self.ty = -1;
	self.vx = 0;
	self.vy = 0;
	self.noAnim = false;
	
	function ts() {
		return (new Date()).getTime() / 100;
	}
	
	function T(ts) {
		return ts - page.startTs;
	}
	
	function EL(sel,x,y) {
		if (!sel) return false;
		var el, i;
		sel = sel.split('>');
		if (!isNaN(Number(sel[sel.length-1]))) {
			i = Number(sel.pop());
			el = $(sel.join('>'))[i];
		} else {
			el = $(sel.join('>'))[0];
		}
		if (el) {
			var o = $(el).offset();
			return {"el":el,"x":o.left+$(el).width()*(x/100),"y":o.top+$(el).height()*(y/100)};
		}
		return false;
	}
	
	function parseParams(p) {
		var rv = {}, n;
		for	(n in p) {
			if (n == 6) {
				rv[vars[Number(n)]] = page.map[p[n]];
			} else {
				rv[vars[Number(n)]] = p[n];
			}
		}
		return rv;
	}
	
	self.add = function(event) {
		if (self.noMoreEvents) return;
		
		switch (event[0]) {
			case 12:
				page.startTs = 0;
				break;
				
			case 13:
				page.startTs = event[1];
				break;
			
			case 15:
				page.map[""+event[1]] = event[2];
				return;
				
			case 19:
				self.noMoreEvents = true;
				break;
		}
		page.e.push(event);		
	}
	
	self.done = function() {
		self.x = self.y = self.tx = self.ty = -1;
		self.noAnim = true;
		//Reached the end
	}
	
	self.seek = function(pos) {
		var i;
		for (i = 0; i < page.e.length; i++) {
			if (T(page.e[i][1]) >= pos) {
				self.posI = i;
				self.pos = pos;
				return true;
			}		
		}
		return false;
	}
	
	self.anim = function(T) {
		if (self.vx || self.vy) {
			self.x += self.vx * T;
			self.y += self.vy * T;
		} else {
			self.x += (self.tx - self.x) * (T / 20);
			self.y += (self.ty - self.y) * (T / 20);
		}
	}
	
	self.setCursor = function(x,y) {
		self.tx = x;
		self.ty = y;
	}
	
	self.findNextMouseEvent = function() {
		var e, i = self.posI + 1;
		while (i < page.e.length) {
			e = page.e[i];
			if (e[0] == 5 || e[0] == 7 || e[0] == 8) {
				return e;
			}
			i++;
		}
		return false;
	}
	
	self.step = function() {
		wait = T(page.e[self.posI + 1][1]) - self.pos;

		function go() {
			self.posI++;
			self.curE = page.e[self.posI];
			self.pos = ts() - self.playTs;
			var el, e, p = parseParams(self.curE[2]);
			
			switch (self.curE[0]) {
				case 4: //scroll
					break;
				
				case 5: //move
				case 7: //out
				case 8: //over
					if (el = EL(p.sel,p.x,p.y)) {
						self.setCursor(el.x,el.y);
						self.vx = self.vy = 0;
						if (e = self.findNextMouseEvent()) {
							p = parseParams(e[2]);
							if (el = EL(p.sel,p.x,p.y)) {
								var D = (e[1] - self.curE[1]);
								if (D > 0) {
									self.vx = (el.x - self.x) / D;
									self.vy = (el.y - self.y) / D;
								}
							}
						}
					}
					break;
				
				case 9: //down
					break;
				
				case 10: //up
					break;
				
				case 11: //click
					self.click = true;
					break;
				
				case 12: //requested
					break;
				
				case 13: //load
					break;
					
				case 14: //size
					break;
				
				case 19: //exit
					break;

			}
			
			if (self.posI < page.e.length - 1) {
				self.step();
			} else {
				self.done();
			}
		}
		
		if (wait <= 0) {
			go();
		} else {
			self.T = setTimeout(go,wait * 100);
		}
	}
	
	self.play = function() {
		self.playTs = ts();
		if (self.posI >= page.e.length - 1) {
			self.done();
			return;
		}
		self.step();
	}
	
	self.debug = function() {
		console.log(page);
	}
}
