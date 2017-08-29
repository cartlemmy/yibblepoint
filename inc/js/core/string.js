String.prototype.camelCaseToReadable = function(delim) {
	if (delim == undefined) delim = " ";
	var v = this.split(/([A-Z]?[a-z\d\_]*)/);
	var rv = [];
	for (var i = 1; i < v.length; i += 2) {
		rv.push(v[i].substr(0,1).toUpperCase()+v[i].substr(1));
	}
	return rv.join(delim);
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

String.prototype.safeString = function() {
	var escapable = /[\\\"\u0000-\u001f\u007f-\uffff]/g;
	var meta = { // table of character substitutions
		'\b': '\\b',
		'\t': '\\t',
		'\n': '\\n',
		'\f': '\\f',
		'\r': '\\r',
		'"': '\\"',
		'\\': '\\\\'
	};

	escapable.lastIndex = 0;
	return escapable.test(this) ? '"' + this.replace(escapable, function (a) {
		var c = meta[a];
		return typeof c === 'string' ? c : '\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice(-4);
	}) + '"' : '"' + this + '"';
};

String.prototype.escapeHtml = function() {
	return this.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
};

String.prototype.unescapeHtml = function() {
	return String(this)
		.replace(/&quot;/g, '"')
		.replace(/&#39;/g, "'")
		.replace(/&lt;/g, '<')
		.replace(/&gt;/g, '>')
		.replace(/&amp;/g, '&');
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
	var arg = [], i, keyIsValue = false;
	for (i in arguments) {
		arg.push(arguments[i]);
	}
	
	if (arg[0] === true) {
		keyIsValue = true;
		arg.shift();
	}
	
	var sep = arg.shift();
	var s = this.split(sep);
	
	if (arg.length == 1) {
		sep = arg.shift();
		var rv = {};
		for (i = 0; i < s.length; i++) {
			var e = s[i].split(sep,2);
			rv[unescape(e[0])] = e.length == 2 ? unescape(e[1]) : (keyIsValue ? unescape(e[0]) : true);
		}
		return rv;
	}
	
	for (i = 0; i < s.length; i++) {
		s[i] = s[i].multiSplit.apply(this,arg);
	}

	return s;
};


String.prototype.charNormalize = function(){ 
	var a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜ¥ÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ'; 
	var b = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYYbSaaaaaaaceeeeiiiidnoooooouuuyybyRr'; 
 
	var rv = "", pos = 0;
	for (var i = 0, len = this.length; i < len; i++) {
		if ((pos = a.indexOf(this.charAt(i))) != -1) {
			rv += b.charAt(pos);
		} else {
			rv += this.charAt(i);
		}
	}
	return rv;
};

String.prototype.leetReplace = function(){ 
	var a = '1234567890!#$([+'; 
	var b = 'lzeasgtbgoihscct'; 
 
	var rv = "", pos = 0;
	for (var i = 0, len = this.length; i < len; i++) {
		if ((pos = a.indexOf(this.charAt(i))) != -1) {
			rv += b.charAt(pos);
		} else {
			rv += this.charAt(i);
		}
	}
	return rv;
};

String.prototype.repeat = function(n){
	var rv = "";
	for (var i = 0; i < n; i++) {
		rv += this.toString();
	}
	return rv; 
};
	
String.prototype.searchify = function(delim,replace) {
	var s = String(this);
	if (replace) {
		s = s.toLowerCase();
		for (var i = 0, len = sl.data.abbreviations[0].length; i < len; i++) {
			if (s.indexOf(sl.data.abbreviations[0][i]) != -1) {
				s = s.split(sl.data.abbreviations[0][i]).join(sl.data.abbreviations[1][i]);
			}
		}
	}
	return s.charNormalize().replace(/[^A-Za-z\d]+/gi,delim==undefined?"":delim).toLowerCase();
};

String.prototype.safeName = function(){
	return this.charNormalize().replace(/[^A-Za-z\d]+/gi,"-").toLowerCase();
};

String.prototype.trim = function() {
	return this.replace(/^\s+|\s+$/g,"");
};

String.prototype.lTrim = function() {
	return this.replace(/^\s+/,"");
};

String.prototype.rTrim = function() {
	return this.replace(/\s+$/,"");
};

String.prototype.diminishParentheses = function() {
	var o = this.split("(");
	for (var i = 1; i < o.length; i+=2) {
		var o2 = o[i].split(")",2);
		if (o2.length == 2) {
			o[i] = "<span class='dimin'>("+o2[0]+")</span>"+o2[1];
		}
	}
	return o.join("");
};

String.prototype.ucFirst = function() {
	return this.charAt(0).toUpperCase() + this.substr(1);
};


String.prototype.getBetween = function(start,end,caseInsensative) {
	var s = caseInsensative ? this.toLowerCase() : this;
	if (caseInsensative) {
		start = start.toLowerCase();
		end = end.toLowerCase();
	}
	
	var p1, p2;
	if ((p1 = s.indexOf(start)) != -1) {
		p1 += start.length;
		if ((p2 = s.indexOf(end,p1)) != -1) {
			return this.substr(p1,p2-p1);
		}
	}
	return false;
};

sl.truncate = function(str,len) {
	return str.truncate(len);
};

String.prototype.truncate = function(len) {
	var s = this.split(/\s/), rv = [], l = 0;
	for (var i = 0; i < s.length; i++) {
		l += s[i].length;
		
		if (l > len && l > 0) {
			rv[rv.length - 1] += "...";
			break;
		}
		
		rv.push(s[i]);
		
		l++;
	}
	return rv.join(" ");
};


String.prototype.wikify = function(toHTML) {
	var s = this.split(/\=\=\=\=([^\n\t]+)\=\=\=\=\n{0,1}/);
	for (var i = 1; i < s.length; i += 2) {
		s[i] = "[subsubhead]"+s[i]+"[/subsubhead]";
	}
	s = s.join("").split(/\=\=\=([^\n\t]+)\=\=\=\n{0,1}/);
	
	for (var i = 1; i < s.length; i += 2) {
		s[i] = "[subhead]"+s[i]+"[/subhead]";
	}
	s = s.join("").split(/\=\=([^\n\t]+)\=\=\n{0,1}/);
	
	for (var i = 1; i < s.length; i += 2) {
		s[i] = "[head]"+s[i]+"[/head]";
	}
	s = s.join("").split(/\'\'\'\'\'([^\n\t\']+)\'\'\'\'\'/);
	
	for (var i = 1; i < s.length; i += 2) {
		s[i] = "[b][i]"+s[i]+"[/i][/b]";
	}
	
	s = s.join("").split(/\'\'\'([^\n\t\']+)\'\'\'/);
	
	for (var i = 1; i < s.length; i += 2) {
		s[i] = "[b]"+s[i]+"[/b]";
	}
	
	s = s.join("").split(/\'\'([^\n\t\']+)\'\'/);
	
	for (var i = 1; i < s.length; i += 2) {
		s[i] = "[i]"+s[i]+"[/i]";
	}
	s = s.join("").split(/\n\:/);
	for (var i = 1; i < s.length; i ++) {
		s[i] = (toHTML?"\n&nbsp;&nbsp;&nbsp;&nbsp;":"\n[tab]")+s[i];
	}
	s = s.join("");	
	
	s = s.split("\n----\n").join("\n[hr]");
	
	if (toHTML) {
		var f = ["subsubhead",	"subhead",	"head",	"b","i","hr"];
		var t = ["h3",			"h2",		"h1",	"b","i","hr"];
		for (var i = 0; i < f.length; i++) {
			s = s.split("["+f[i]+"]").join("<"+t[i]+">");
			s = s.split("[/"+f[i]+"]").join("</"+t[i]+">");
		}
	}
	return s.replace(/\n/gi,"<br />\n");
};

String.prototype.dewikify = function() {
	var s = this.toString();
	var convert = [
		[/[\n]+/gi,""],
		[/\<h1\>([^\<]+)\<\/h1\>/gi, function(match, p1){return "=="+p1+"==\n";}],
		[/\<h2\>([^\<]+)\<\/h2\>/gi, function(match, p1){return "==="+p1+"===\n";}],
		[/\<h2\>([^\<]+)\<\/h2\>/gi, function(match, p1){return "===="+p1+"====\n";}],
		[/\<hr(\>|\s\/\>)/gi, function(){return "\n----\n";}],
		[/\<br(\>|\s\/\>)/gi, function(){return "\n";}]
	];
	for (var i = 0; i < convert.length; i++) {
		s = s.replace(convert[i][0],convert[i][1]);
	}
	return s;
};
