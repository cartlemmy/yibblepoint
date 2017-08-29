sl.efx = {
	"fade":function(el,cb) {
		if (sl.config.noEfx) { cb(); return; }
		
		var o = 1;
		var t = setInterval(function(){
			o -= 0.1;
			if (o > 0) {
				el.style.opacity = o;
			} else {
				clearInterval(t);
				cb();
			}
		},50);
	},
	"appear":function(start,el,canvas,cb,reverse) {
		if (sl.config.noEfx) {
			el.style.visibility = "";
			el.style.opacity = reverse ? 0 : 1;
			cb();
			return;
		}
		
		var o = 0;
		el.style.opacity = reverse ? 1 : 0;
		el.style.visibility = "";
		
		var w = el.offsetWidth * 3, h = el.offsetHeight * 3;
		if (!start) start = {
			"x":el.offsetLeft - (w - el.offsetWidth) / 2,
			"y":el.offsetTop - (h - el.offsetHeight) / 2,
			"w":w,
			"h":h
		};
		
		var t = sl.efx.addUpdater(function(T,ctx){
			o += T * 2;
			if (o < 1) {
				var ao = (reverse ? 1 - o : o);
				el.style.opacity = Math.max(0,ao * 2 - 1);				
				if (sl.supports("canvas")) {
					ctx.save();
					ctx.globalAlpha = o / 10;
					var om = ao * 1.2;
					for (var i = Math.max(0,om - T * 2.5); i < Math.min(1,om); i += 0.025) {
						var ni = 1 - i;

						ctx.drawImage(
							canvas,
							ni*start.x+i*el.offsetLeft,
							ni*start.y+i*el.offsetTop,
							ni*start.w+i*el.offsetWidth,
							ni*start.h+i*el.offsetHeight
						);
					}				
					ctx.restore();
				}
			} else {
				sl.efx.removeUpdater(t);
				el.style.opacity = reverse ? 0 : 1;
				cb();
			}
		});
	},
	"addUpdater":function(cb) {
		var u = {"cb":cb};
		sl.efx.updaters.push(u);
		return u;
	},
	"removeUpdater":function(u) {
		var i = sl.efx.updaters.indexOf(u);
		if (i == -1) return;
		sl.efx.updaters.splice(i,1);
	},
	"update":function(){
		var ts = sl.unixTS(true);
		
		var T = ts - sl.efx.lastUpdate;
		
		if (sl.efx.updaters.length) {
			if (sl.supports("canvas")) {
				sl.efx.ctx.clearRect(0,0,sl.efx.canvas.width,sl.efx.canvas.height);
				sl.efx.ctx.save();
				sl.efx.ctx.scale(sl.efx.canvasScale.width,sl.efx.canvasScale.height);
				
				for (var i = 0; i < sl.efx.updaters.length; i++) {
					sl.efx.updaters[i].cb(T,sl.efx.ctx);
				}
				
				sl.efx.ctx.restore();
			}
			//setTimeout(sl.efx.update,Math.max(50,(sl.unixTS(true) - ts) * 1000));
		} else {
			//setTimeout(sl.efx.update,100);
		}
		
		sl.efx.lastUpdate = ts;
	},
	"updaters":[],
	"lastUpdate":0
};

setInterval(sl.efx.update,40);

sl.efx.update();
