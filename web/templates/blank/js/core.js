$.fn.update = function(cb) {
	var curE, t, self = this, lastValue = this.val();
	
	function update(e,go) {
		if (t) clearTimeout(t);
		if (e.type == "keyup" && !go) {
			curE = e;
			t = setTimeout(function(){update(curE,true)},100);
			return;
		}
		if (lastValue != self.val()) cb.call(self,e);
		lastValue = self.val();
	};
	
	this.change(update);
	this.on("keyup", update);
};

$.fn.showOrHide = function(show) {
	if (show) {
		this.show();
	} else {
		this.hide();
	}
};

$.fn.topHeight = function(show) {
	return (this.outerHeight(true) + this.outerHeight()) / 2;
};

function coreOb() {
	var self = this;
	var clientPWKey = config.C_PW_KEY;
	
	self.authenticated = false;
	
	self.setUserKey = function(key,cb) {
		//TODO: Check to make sure this is a valid user key
		
		var checkVal = $.md5((""+Math.random())+"-"+self.unixTS()).substr(0,16);
		storedValue("na_user_key",checkVal+"-"+$.rc4EncryptStr(checkVal+"-"+key,self.getPasswordHash()));
		
		self.updatePublicUserKey();
		
		if (cb) cb(true);
	};
	
	function storedValue(n,v) {
		if (('localStorage' in window) && window.localStorage !== null) {
			if (v === undefined) {
				var rv = localStorage[n] ? JSON.parse(localStorage[n]) : undefined;
				//if (rv === undefined) return $.cookie(n);
				return rv;
			} else {
				localStorage[n] = JSON.stringify(v);
				return;
			}
		}		
		return $.cookie(n,v);
	};
	
	function removeStoredValue(n) {
		if (('localStorage' in window) && window.localStorage !== null) {
			localStorage.removeItem(n);
			return;
		}
		return $.removeCookie(n);
	};
	
	self.getUserKey = function() {
		var key;
		if ((key = storedValue("na_user_key")) !== undefined) {
			key = key.split("-");
			if (key.length == 2) {
				try {
					key[1] = $.rc4DecryptStr(key[1],self.getPasswordHash()).split("-");
					var cv = key[1].shift();
					if (key[0] === cv) return key[1].join("-");
				} catch (e) {};
			} else return undefined;
			return false;
		}
		return undefined;
	};

	self.getUserData = function(n,cb) {
		self.request("get-user-data",{"n":n},function(o){
			cb(o);
		});
	};
	
	self.setUserData = function(n,v,cb,key) {
		self.request("set-user-data",{"n":n,"v":v,"_KEY":key ? self.getPasswordHash() : false},cb);
	};
	
	self.setPassword = function(password) {
		password = $.md5("PW"+clientPWKey+""+password);
		storedValue("na_password",$.rc4EncryptStr(password,clientPWKey),{"expires":1});
		self.updatePublicUserKey();
		return self.getUserKey() !== false;
	};
	
	self.refreshPassword = function() {
		storedValue("na_password",self.getPasswordHash(),{"expires":1});
	};
	
	self.getPasswordHash = function() {
		var pw;
		if ((pw = storedValue("na_password"))) {
			return $.rc4DecryptStr(pw,clientPWKey);
		}
		return undefined;
	};
	
	self.unixTS = function(dontRound) {
		var currentTime = new Date();
		if (dontRound) {
			return currentTime.getTime() / 1000;
		} else {
			return Math.floor(currentTime.getTime() / 1000);
		}
	};
	
	self.updatePublicUserKey = function() {
		var key;
		if (key = self.getUserKey()) {
			self.publicUserKey = $.md5(clientPWKey+"-"+key);
			tv.setUserKey(key);
		}
	};
		
	self.init = function() {
		if (config.PERMISSION_LEVEL == -1) {
			removeStoredValue('na_user_key');
			removeStoredValue('na_password');
		}
		
		self.publicUserKey = null;
		var key;
		if ((key = self.getUserKey()) === undefined) {
			var p = self.parseUrl();
			if (p.params && p.params.t) { //Set from token
				storedValue("na_user_key",p.params.t);
				key = self.getUserKey();
			} else {
				//user key not set
				removeStoredValue('na_password');
				if (self.parseUrl().page != "initial-setup") self.go("initial-setup",null,true);
				return;
			}
		}
		
		self.updatePublicUserKey();
		
		if (self.getPasswordHash() === undefined) {
			//password not set
			if (self.parseUrl().page != "initial-setup") self.go("authenticate",null,true);
			return;
		}
				
		//Authenticated!
		self.authenticated = true;
		
		if (!config.PERMISSION_LEVEL) {
			$("#_KEY").val(self.publicUserKey);
			$("#key-form").submit();
		}
		
		if (storedValue("na_page") != window.location.href.substr(window.location.href.length - storedValue("na_page").length)) {
			self.go(storedValue("na_page"));
		}
		
		window.addEventListener("load",function() {
			if (navigator.geolocation) {
				//navigator.geolocation.getCurrentPosition(self.locationUpdate, self.locationError, { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 });
				navigator.geolocation.watchPosition(self.locationUpdate, self.locationError, {enableHighAccuracy:true,maximumAge:30000});
			}
		});
	};
	
	self.locationUpdate = function(loc) {
		//alert(JSON.stringify(loc.coords));
		self.request("update-position",loc.coords,function(res){
			//alert(JSON.stringify(res));
		})
		
		//alert(JSON.stringify(loc.coords));
		//console.log(loc);
	};
	
	self.locationError = function(e) {
		//console.log(e);
	};
	
	self.authenticate = function() {
		var pw = $("#password").val();
		if (self.setPassword(pw)) {
			self.go("settings");
		} else {
			$("#password-invalid").show(400).delay(3000).fadeOut();			
			$("#password").val("");
		}
	};
	
	self.go = function(location,params,noCookie) {		
		if (!location) location = window.location.href;
		if (params) {
			var p = self.parseUrl(location);
			if (!p.params) p.params = {};
			for (var n in params) {
				p.params[n] = params[n];
			}

			location = p.protocol+"//"+p.host+"/"+p.path+p.page+"?"+(function(){
				var rv = [];
				for (var n in p.params) {
					rv.push(encodeURIComponent(n)+"="+encodeURIComponent(p.params[n])); 
				}
				return rv.join("&");
			})()+p.hash;
		}
						
		var locInfo = self.parseUrl(location);
		if (self.parseUrl().local == locInfo.local) return;
		
		if (locInfo.page == "settings" && storedValue("na_user_key")) {
			location = location+"?t="+storedValue("na_user_key");
		}
		if (!noCookie) storedValue("na_page", location);
		window.location.href = location;
	};
		
	self.parseUrl = function(url) {
		if (!url) url = window.location.href;
		
		var rv = {
			"protocol":null,
			"search":"",
			"path":"",
			"hash":""
		};
		
		url = url.split(/(\/\/|\/|\?|\#)/);
		if (url[1] == "//") {
			rv.protocol = url.shift();
			url.shift();
			rv.host = url.shift();
			url.shift();
		} else {
			var f = window.location.href.split(/(\/\/|\/|\?|\#)/);
			
			rv.protocol = f.shift();
			f.shift();
			rv.host = f.shift();
			f.shift();
						
			if (url[0] !== "") {
				var v, p = self.parseUrl(window.location.href).path.split(/(\/)/);
				p.pop();
				while (v = p.pop()) {
					url.unshift(v);
				}
			}
		}
		
		rv.path = "";
		
		while (url[1] == "/") {
			rv.path += url.shift() + url.shift();
		}
		
		rv.page = url.shift();
		
		if (url.length) {
			if (url[0] == "?") {
				rv.search = url.shift() + url.shift();
				rv.params = rv.search.substr(1).multiSplit("&","=");
			}
			if (url[0] == "#") {
				rv.hash = url.shift() + url.shift();
			}
		}
		
		rv.local = rv.path + rv.page + rv.search;
		
		return rv;
	};
	
	self.request = function(action,paramsIn,cb) {
		paramsIn._KEY = self.publicUserKey;
		var params = {};
		
		for (var n in paramsIn) {
			params[n] = typeof(paramsIn[n]) == "string" ? "s"+paramsIn[n] : "o"+JSON.stringify(paramsIn[n]);
		}
		
		$.ajax({
			dataType: "json",
			type: "POST",
			url: "?action="+escape(action),
			data: params,
			beforeSend: function(xhr) {
				//Upload progress
				if (xhr.upload) {
					xhr.upload.addEventListener("progress", function(evt){
						console.log(evt);
						if (evt.lengthComputable) {  
							var percentComplete = evt.loaded / evt.total;
							//Do something with upload progress
						}
					}, false)
				}
			}
		}).done(function(r) {
			//console.log("done",r);
			if (cb) cb(r);
		}).error(function(o1,o2,o3){
			console.log("Core request Error",o1,o2,o3);
		});
	};
		
	self.getCurrentScript = function() {
		var scripts = document.getElementsByTagName('script');
		return scripts[scripts.length - 1];
	};
};

function cancelBubble(e) {
	if (e.originEvent) e = e.originEvent;
	var evt = e ? e : window.event;
	if (evt.stopPropagation) evt.stopPropagation();
	if (evt.cancelBubble!=null) evt.cancelBubble = true;
};

function preventDefault(e) {
	if (e.originEvent) e = e.originEvent;

  if ( e.preventDefault ) {
		e.preventDefault();
	} else {
		e.returnValue = false;
	}
};

function dg(id,p,t,a,pre) {
	if (id !== "" && document.getElementById(id)) {
		return document.getElementById(id);
	} else {
		if (t == "text") {
			var d = document.createTextNode(a);
		} else {
			var d = document.createElement(t);
		}
		
		if (id !== "") { d.id = id; }
		if (pre && pre.before && !p) p = pre.before.parentNode;
		if (pre && pre.after && !p) p = pre.after.parentNode;
		
		if (p) {
			if (pre && pre.before) {
				p.insertBefore(d, pre.before);
			} else if (pre && pre.after) {
				if (pre.after.nextSibling) {
					p.insertBefore(d, pre.after.nextSibling);
				} else {
					p.appendChild(d);
				}
			} else if (pre) {
				p.insertBefore(d, p.firstChild);
			} else {
				p.appendChild(d);
			}
		}
		if (t != "text") recursiveSet(d,a);
		return d;
	}
	return false;
};

function recursiveSet(d,a) {
	for (var i in a) {
		if (typeof(a[i]) == "object") {
			recursiveSet(d[i],a[i]);
		} else {
			d[i] = a[i];
		}
	}
};

if (!window.btoa) {
	window.btoa = function(str) {
		return Base64.encode(str);
	}
}

if (!window.atob) {
	window.atob = function(str) {
		return Base64.decode(str);
	}
}

Array.prototype.multiJoin = function() {
	var arg = [], i;
	for (i in arguments) {
		arg.push(arguments[i]);
	}
	var rv = [], sep = arg.length ? arg[0] : ",";
	if (arg.length) arg.shift();
	for (i = 0; i < this.length; i++) {
		if (this[i] instanceof Array) {
			rv.push(this[i].multiJoin.apply(this[i],arg));
		} else {
			rv.push(this[i]);
		}
	}
	return rv.join(sep);
};

String.prototype.multiSplit = function() {
	var arg = [], i;
	for (i in arguments) {
		arg.push(arguments[i]);
	}
	
	var sep = arg.shift();
	var s = this.split(sep);
	
	if (arg.length == 1) {
		sep = arg.shift();
		var rv = {};
		for (i = 0; i < s.length; i++) {
			var e = s[i].split(sep,2);
			rv[unescape(e[0])] = e.length == 2 ? unescape(e[1]) : true;
		}
		return rv;
	}
	
	for (i = 0; i < s.length; i++) {
		s[i] = s[i].multiSplit.apply(this,arg);
	}

	return s;
};

function date(format, timestamp) {
    var that = this,
        jsdate, f, formatChr = /\\?([a-z])/gi,
        formatChrCb,
        // Keep this here (works, but for code commented-out
        // below for file size reasons)
        //, tal= [],
        _pad = function (n, c) {
            if ((n = n + '').length < c) {
                return new Array((++c) - n.length).join('0') + n;
            }
            return n;
        },
        txt_words = ["Sun", "Mon", "Tues", "Wednes", "Thurs", "Fri", "Satur", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    formatChrCb = function (t, s) {
        return f[t] ? f[t]() : s;
    };
    f = {
        // Day
        d: function () { // Day of month w/leading 0; 01..31
            return _pad(f.j(), 2);
        },
        D: function () { // Shorthand day name; Mon...Sun
            return f.l().slice(0, 3);
        },
        j: function () { // Day of month; 1..31
            return jsdate.getDate();
        },
        l: function () { // Full day name; Monday...Sunday
            return txt_words[f.w()] + 'day';
        },
        N: function () { // ISO-8601 day of week; 1[Mon]..7[Sun]
            return f.w() || 7;
        },
        S: function () { // Ordinal suffix for day of month; st, nd, rd, th
            var j = f.j();
            return j > 4 || j < 21 ? 'th' : {1: 'st', 2: 'nd', 3: 'rd'}[j % 10] || 'th';
        },
        w: function () { // Day of week; 0[Sun]..6[Sat]
            return jsdate.getDay();
        },
        z: function () { // Day of year; 0..365
            var a = new Date(f.Y(), f.n() - 1, f.j()),
                b = new Date(f.Y(), 0, 1);
            return Math.round((a - b) / 864e5) + 1;
        },

        // Week
        W: function () { // ISO-8601 week number
            var a = new Date(f.Y(), f.n() - 1, f.j() - f.N() + 3),
                b = new Date(a.getFullYear(), 0, 4);
            return _pad(1 + Math.round((a - b) / 864e5 / 7), 2);
        },

        // Month
        F: function () { // Full month name; January...December
            return txt_words[6 + f.n()];
        },
        m: function () { // Month w/leading 0; 01...12
            return _pad(f.n(), 2);
        },
        M: function () { // Shorthand month name; Jan...Dec
            return f.F().slice(0, 3);
        },
        n: function () { // Month; 1...12
            return jsdate.getMonth() + 1;
        },
        t: function () { // Days in month; 28...31
            return (new Date(f.Y(), f.n(), 0)).getDate();
        },

        // Year
        L: function () { // Is leap year?; 0 or 1
            return new Date(f.Y(), 1, 29).getMonth() === 1 | 0;
        },
        o: function () { // ISO-8601 year
            var n = f.n(),
                W = f.W(),
                Y = f.Y();
            return Y + (n === 12 && W < 9 ? -1 : n === 1 && W > 9);
        },
        Y: function () { // Full year; e.g. 1980...2010
            return jsdate.getFullYear();
        },
        y: function () { // Last two digits of year; 00...99
            return (f.Y() + "").slice(-2);
        },

        // Time
        a: function () { // am or pm
            return jsdate.getHours() > 11 ? "pm" : "am";
        },
        A: function () { // AM or PM
            return f.a().toUpperCase();
        },
        B: function () { // Swatch Internet time; 000..999
            var H = jsdate.getUTCHours() * 36e2,
                // Hours
                i = jsdate.getUTCMinutes() * 60,
                // Minutes
                s = jsdate.getUTCSeconds(); // Seconds
            return _pad(Math.floor((H + i + s + 36e2) / 86.4) % 1e3, 3);
        },
        g: function () { // 12-Hours; 1..12
            return f.G() % 12 || 12;
        },
        G: function () { // 24-Hours; 0..23
            return jsdate.getHours();
        },
        h: function () { // 12-Hours w/leading 0; 01..12
            return _pad(f.g(), 2);
        },
        H: function () { // 24-Hours w/leading 0; 00..23
            return _pad(f.G(), 2);
        },
        i: function () { // Minutes w/leading 0; 00..59
            return _pad(jsdate.getMinutes(), 2);
        },
        s: function () { // Seconds w/leading 0; 00..59
            return _pad(jsdate.getSeconds(), 2);
        },
        u: function () { // Microseconds; 000000-999000
            return _pad(jsdate.getMilliseconds() * 1000, 6);
        },

        // Timezone
        e: function () { // Timezone identifier; e.g. Atlantic/Azores, ...
            // The following works, but requires inclusion of the very large
            // timezone_abbreviations_list() function.
/*              return this.date_default_timezone_get();
*/
            throw 'Not supported (see source code of date() for timezone on how to add support)';
        },
        I: function () { // DST observed?; 0 or 1
            // Compares Jan 1 minus Jan 1 UTC to Jul 1 minus Jul 1 UTC.
            // If they are not equal, then DST is observed.
            var a = new Date(f.Y(), 0),
                // Jan 1
                c = Date.UTC(f.Y(), 0),
                // Jan 1 UTC
                b = new Date(f.Y(), 6),
                // Jul 1
                d = Date.UTC(f.Y(), 6); // Jul 1 UTC
            return 0 + ((a - c) !== (b - d));
        },
        O: function () { // Difference to GMT in hour format; e.g. +0200
            var a = jsdate.getTimezoneOffset();
            return (a > 0 ? "-" : "+") + _pad(Math.abs(a / 60 * 100), 4);
        },
        P: function () { // Difference to GMT w/colon; e.g. +02:00
            var O = f.O();
            return (O.substr(0, 3) + ":" + O.substr(3, 2));
        },
        T: function () {
            return 'UTC';
        },
        Z: function () { // Timezone offset in seconds (-43200...50400)
            return -jsdate.getTimezoneOffset() * 60;
        },

        // Full Date/Time
        c: function () { // ISO-8601 date.
            return 'Y-m-d\\Th:i:sP'.replace(formatChr, formatChrCb);
        },
        r: function () { // RFC 2822
            return 'D, d M Y H:i:s O'.replace(formatChr, formatChrCb);
        },
        U: function () { // Seconds since UNIX epoch
            return jsdate.getTime() / 1000 | 0;
        }
    };
    this.date = function (format, timestamp) {
        that = this;
        jsdate = ((typeof timestamp === 'undefined') ? new Date() : // Not provided
        (timestamp instanceof Date) ? new Date(timestamp) : // JS Date()
        new Date(timestamp * 1000) // UNIX timestamp (auto-convert to int)
        );
        return format.replace(formatChr, formatChrCb);
    };
    return this.date(format, timestamp);
};

String.prototype.toCamelCase = function(firstUpper,allowFirstCharNum) {
	var v = this.split(/[^\w\d]+/);
	var rv = [];
	for (var i = 0; i < v.length; i ++) {
		if (!firstUpper && i == 0) {
			rv.push(v[i].toLowerCase());
		} else {
			rv.push(v[i].substr(0,1).toUpperCase()+v[i].substr(1).toLowerCase());
		}
	}
	rv = rv.join("");
	if (!allowFirstCharNum && rv.substr(0,1).replace(/[\d]/,'') == '') {
		rv = "_"+rv;
	}
	return rv;
};

function setBleedingEdgeStyle(el,n,v) {
	var pre = [false,"webkit","Moz","ms","O"];
	for (var i = 0; i < pre.length; i++) {
		var prop = pre[i] ? pre[i]+n.toCamelCase(true) : n;
		if (el.style[prop] !== undefined) {
			el.style[prop] = v;
			return;
		}
	}
};

var core = new coreOb();

core.init();
