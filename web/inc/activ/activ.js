

function Activ(params) {
	var self = this, C = {
	  get: function (sKey) {
		if (!sKey) { return null; }
		return decodeURIComponent(document.cookie.replace(new RegExp("(?:(?:^|.*;)\\s*" + encodeURIComponent(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1")) || null;
	  },
	  set: function (sKey, sValue, vEnd, sPath, sDomain, bSecure) {
		if (!sKey || /^(?:expires|max\-age|path|domain|secure)$/i.test(sKey)) { return false; }
		var sExpires = "";
		if (vEnd) {
		  switch (vEnd.constructor) {
			case Number:
			  sExpires = vEnd === Infinity ? "; expires=Fri, 31 Dec 9999 23:59:59 GMT" : "; max-age=" + vEnd;
			  break;
			case String:
			  sExpires = "; expires=" + vEnd;
			  break;
			case Date:
			  sExpires = "; expires=" + vEnd.toUTCString();
			  break;
		  }
		}
		document.cookie = encodeURIComponent(sKey) + "=" + encodeURIComponent(sValue) + sExpires + (sDomain ? "; domain=" + sDomain : "") + (sPath ? "; path=" + sPath : "") + (bSecure ? "; secure" : "");
		return true;
	  },
	  remove: function (sKey, sPath, sDomain) {
		if (!this.has(sKey)) { return false; }
		document.cookie = encodeURIComponent(sKey) + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT" + (sDomain ? "; domain=" + sDomain : "") + (sPath ? "; path=" + sPath : "");
		return true;
	  },
	  has: function (sKey) {
		if (!sKey) { return false; }
		return (new RegExp("(?:^|;\\s*)" + encodeURIComponent(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=")).test(document.cookie);
	  },
	  keys: function () {
		var aKeys = document.cookie.replace(/((?:^|\s*;)[^\=]+)(?=;|$)|^\s*|\s*(?:\=[^;]*)?(?:\1|$)/g, "").split(/\s*(?:\=[^;]*)?;\s*/);
		for (var nLen = aKeys.length, nIdx = 0; nIdx < nLen; nIdx++) { aKeys[nIdx] = decodeURIComponent(aKeys[nIdx]); }
		return aKeys;
	  }
	};
	
	function getScrollTop() {
		return document.body.scrollTop || document.documentElement.scrollTop; 
	};

	function ts() {
		return Math.round((new Date()).getTime() / 100);
	}
	
	function fromUnixTs(ts) {
		return Math.round(ts * 10);
	}
	
	function toUnixTs(T) {
		return T / 10;
	}
	
	self.isTop = !window.parent._activ;
	var ns = [], i, n, o, collector = !self.isTop ? window.parent._activ : self, eT = {}, eL = {}, status = {
		"first":ts(),"last":ts(),"other":ts(),"requested":fromUnixTs(params.requested),"pageLoad":ts(),"lastMove":ts(),"lastScroll":ts(),"lastClick":ts(),"referrer":1
	}, vars = ['x','y','child','top','scroll','move','sel','out','over','down','up','click','requested','load','size','n','href','w','h','exit','fp'], eS = [],
	listeners = [];
	
	function domain(url) {
		url = url.split('//').pop().split('/').shift().split('.');
		while (url.length > 2) {
			url.shift();
		}
		return url.join('.');
	}
		
	function upStatus(n,T) {
		status[n] = !T ? ts() : T;
	};
	
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
	
	var listenerId = 1;
	self.addListener = function(type,p,cb) {
		if (!self.isTop) return collector.addListener(type,p,cb);
		listeners.push([type,p,cb,listenerId++]);
		return listenerId - 1;
	};
	
	self.removeListener = function(id) {
		for (i = 0; i < listeners.length; i++) {
			if (id == listeners[i][3]) {
				listeners.splice(i,1);
				break;
			}
		}
	};
	
	var sendT, sending = false;
	function sendCheck(now) {
		if (!window.localStorage) return;
		
		var i, d, n, a;
		d = localStorage.getItem('_active_queue');
		d = d ? JSON.parse(d) : [];
		
		for (i = 0; i < eS.length; i++) {
			d.push(eS[i]);
		}
		
		eS = [];
		
		localStorage.setItem('_active_queue',JSON.stringify(d));
		
		if (sendT) clearTimeout(sendT);
		
		function send() {
			if (sending) return;
			
			var sendData = localStorage.getItem('_active_queue');
			localStorage.setItem('_active_queue','[]');
			
			if (!sendData) return;
			
			sending = true;
			
			$.ajax({
				url:params.u,
				data:{'href':window.location.href,'d':sendData},
				type:'POST',
				async: now === true?false:true,
				success: function(data) {
					sending = false;
				},
				error: function(e){
					console.log(e);
				}
			});
		}
		
		if (d.length) {
			if (now) {
				send();
			} else {			
				sendT = setTimeout(send,30000);
			}
		}
		
		a = [];
		for (n in status) {
			a.push(status[n]);
		}
		C.set('_activ',JSON.stringify(a));
	}
	
	window.onbeforeunload = function(e) {
		self.event('exit');
		sendCheck(true);
	};
	
	self.event = function(type,T,P) {
		if (!self.isTop) {
			if (!P) P = {};
			P.child = true;
			collector.event(type,T,P);
			return;
		}
		
		if (T) T = fromUnixTs(T);
		
		if (eT[type]) clearTimeout(eT[type]);
		
		function go() {
			eS.push([FP(type), T, compA(P)]);
			eL[type] = ts();
			sendCheck();
		}
		T = !T ? ts() : T;
		
		if (!eL[type]) eL[type] = 0;
		if (ts() - eL[type] > 5 || (["requested","load","click"]).indexOf() != -1) {
			go();
		} else {
			eT[type] = setTimeout(go,250);
		}
		
		var move = false;
		
		switch (type) {
			case "requested":
				upStatus("requested",T);
				break;
				
			case "load":
				if (domain(document.referrer) != domain(window.location.href)) {
					upStatus('referrer',document.referrer?document.referrer:1);
				}
				upStatus("pageLoad");
				break;
			
			case "scroll":
				upStatus("lastScroll");
				break;
				
			case "click":
				upStatus("lastClick");
				break;
				
			case "move": case "out": case "over":
			case "down": case "up":
				upStatus("lastMove");
				move = true;
				break;
				
		}
		upStatus("last");
		if (!move) upStatus("other");
	};
	
	if (self.isTop) {		
		if (o = C.get('_activ')) {
			o = JSON.parse(o);
			i = 0;
			for (n in status) {
				status[n] = o[i];
				i++;
			}
		}

		self.event('requested',params.requested,{"href":window.location.href});
		self.event('load');	
		
		size();
		
		if (localStorage.getItem('_active_queue')) sendCheck(1);
		
		function check() {
			var i, l, j, T = ts(),
				 a = {
					"move":(status.lastMove ? toUnixTs(T - status.lastMove) : -1),
					"scroll":(status.lastScroll ? toUnixTs(T - status.lastScroll) : -1),
					"click":(status.lastClick ? toUnixTs(T - status.lastClick) : -1),
					"other":(status.other ? toUnixTs(T - status.other) : -1)
				};
			for (i = 0; i < listeners.length; i++) {
				var l = listeners[i];
				switch (l[0]) {
					case "idle":
						if (!l[1].dur) l[1].dur = 5;
						if (!l[1].factor) l[1].factor = ['move','scroll','click','other'];
						var f = true, checked = false;
						for (j = 0; j < l[1].factor.length; j++) {
	
							if (a[l[1].factor[j]] == -1) continue;
							checked = true;
							if (a[l[1].factor[j]] < l[1].dur) {
								f = false;
								break;
							}
						}
						if (checked && f) {
							l[2](a);
							listeners.splice(i,1);
							i--;
						}
						break;
				}
			}
		};
		
		check();
		setInterval(check,1000);	
	}
	
	function elToSel(el) {
		var s = [];
		
		do {
			if (el.id) {
				s.unshift('#'+el.id);
				break;
			} else if (el.nodeName == "BODY") {
				s.unshift(el.nodeName.toLowerCase());
				break;
			} else if (el.className) {
				s.unshift('.'+el.className.split(" ").join("."));
			} else {
				s.unshift(el.nodeName.toLowerCase());
			}
		} while (el = el.parentNode);
		
		$(s.join('>')).each(function(i,ele){
			if (el == ele) s.push(i);
		});
		
		return s.join('>');
	}
	
	function AddN(n) {
		var ni;
		if ((ni = ns.indexOf(n)) != -1) return ni;
		ni = ns.length;
		ns.push(n);
		eS.push([FP('n'),ni,n]);
		return ni;
	}
	
	function PFromEl(el,x,y) {
		var rv =  {
			"sel":AddN(elToSel(el)),
			"x":Math.round(x / $(el).width()*100),
			"y":Math.round(y / $(el).height()*100)
		};
		
		if (el.href) rv.href = el.href;
		return rv;
	}

	function mouse(e) {
		var off = $(e.target).offset();
		self.event(e.type.replace('mouse',''),false,PFromEl(e.target,e.pageX - off.left,e.pageY - off.top));
	};
	
	function size(e) {
		self.event('size',false,{"w":$( window ).width(),"h":$( window ).height()});
	};
	
	document.addEventListener("mousemove",mouse);
	document.addEventListener("mouseout",mouse);
	document.addEventListener("mouseover",mouse);
	document.addEventListener("mousedown",mouse);
	document.addEventListener("mouseup",mouse);
	
	document.addEventListener("click",mouse);
	
	window.addEventListener("resize",size);
	
	window.addEventListener("scroll",function(e){
		self.event('scroll',false,{"top":getScrollTop()});
	});
};
