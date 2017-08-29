sl.valueDef.date = {
	"patterns":{
		"[th]":"en-us|(st|rd|th)?",
		"mm":"\\d{1,2}",
		"dd":"\\d{1,2}",
		"yyyy":"\\d{4}",
		"month":"en-us|(january|february|march|april|may|june|july|august|september|october|november|december)",
		"mshort":"en-us|(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)",
		"mwy":"en-us|(sunday|monday|tuesday|wednesday|thurday|friday|saturday|month|week|year)",
		"HH":"\\d{1,2}",
		"MM":"\\d{2}",
		"SS":"\\d{2}",
		"ampm":"en-us|(am|pm)",
		"today":"en-us|today",
		"now":"en-us|now",
		"ystrdy":"en-us|yesterday",
		"tomorrow":"en-us|tomorrow",
		"next":"en-us|next",
		"las":"en-us|last",
		"previous":"en-us|previous",
	},
	"format":[{
		"today":[0],
		"now":[0],
		"ystrdy":[-1],
		"tomorrow":[1],
		"next mwy":[1,"relative"],
		"las mwy":[-1,"relative"],
		"previous mwy":[-1,"relative"],
		"mm-dd-yyyy":["m","d","y"],
		"dd-mm-yyyy":["d","m","y"],
		"yyyy-mm-dd":["y","m","d"],
		"mm-dd":["m","d"],
		"month mm[th] yyyy":["month","d","y"],
		"mm[th] of month yyyy":["month","d","y"],
		"mshort mm[th] yyyy":["month","d","y"],
		"mm[th] of mshort yyyy":["month","d","y"]
	},{
		"HH:MM:SSampm":["hour","minute","second"],
		"HH:MMampm":["hour","minute"],
		"HHampm":["hour","ampm"],
		"HH:MM:SS":["hour","minute","second"],
		"HH:MM":["hour","minute"]			
	}],
	"toString":function() {
		this.value = Number(this.value);
		if (isNaN(this.value)) this.value = 0;
		if (!this.value) return this.nullLabel ? this.nullLabel : "en-us|N/A";
		if (sl.config.international[this.format]) {
			return sl.date(sl.config.international[this.format],Math.floor(this.value));
		} else {
			return sl.date(this.format?this.format:sl.config.international["date-time"],Math.floor(this.value));
		}
	},
	"clean":function(v) {
		return typeof(v) == "string" ? v.replace(/[\,]+/gi,"").replace(/[^\w\d\: ]+/gi,"-").replace(/\s+/gi," ") : "";
	},
	"fromString":function(text) {
		
		function ampm(hour,a){ 
			if (a == "am") {
				if (hour == 12) hour = 0;
			} else if (a == "pm") {
				if (hour != 12) hour += 12;
			}
			return hour;
		}
								
		var match, matchText = this.def.clean(text);
		
		var passName = ["en-us|Date","en-us|Time"];
		var possible = [[],[]];
		
		for (var dt = 0; dt < 2; dt++) {
			for (var n in this.def.format[dt]) {
				var format = n;
				
				for (var i in this.def.patterns) {
					format = format.replace(i,this.def.patterns[i]);
				}
				
				format = format.split(":").join("\\:");
				
				if (match = (new RegExp(format,"gi")).exec(matchText)) {
					possible[dt].push([match[0],this.def.format[dt][n]]);
					if (dt == 1) break;
				}
			}
		}
				
		if (possible[0].length > 1) {
			for (var i = 0; i < possible[0].length; i++) {
				if (possible[0][i][1].join("") == sl.config.international.dateOrder) {
					possible[0] = [possible[0][i]];
					break;
				}					
			}
			
			if (possible[0].length > 1) return false;
		}
		
		if (possible[0].length == 0 && possible[1].length == 0) {
			//No date found
			return false;
		} else {
			var months = this.def.patterns.month.substr(1,this.def.patterns.month.length-2).split("|");

			var rid = [new RegExp(this.def.patterns["[th]"],"gi")], timeFound = false;
			
			/*
			//TODO: 
			var newFormat = text.split("").join("\\");;
			console.log(newFormat);
			*/
			
			var ts = new Date(), hour = -1;
			var relativeV = 0, relativeMult = "";
			
			//Is there a relative value?
			for (var dt = 0; dt < 2; dt++) {
				var res = possible[dt][0];
				
				if (res && res.length) {
					for (var i = 0; i < rid.length; i++) {
						res[0] = res[0].replace(rid[i],"");
					}
					
					var parts = res[0].split(/[^\w\d]+/gi);
					for (var i = 0; i < parts.length; i++) {
						var part = parts[i].toLowerCase();

						switch (res[1][i]) {
							case "relative":
								relativeMult = part;
								break;
							
							default:
								if (typeof(res[1][i]) == "number") {
									if (relativeMult == "") relativeMult = "day";
									relativeV = res[1][i];
								}
								break;
						}
					}
				}
			}
			
			if (relativeMult != "") {
				ts = new Date();
			} else {
				ts.setDate(1);
			}
				

			switch (relativeMult) {
				case "day":
					ts.setDate(ts.getDate() + relativeV);
					break;
				
				case "week":
					ts.setDate(ts.getDate() + relativeV * 7);
					break;
					
				case "month":
					ts.setMonth(ts.getMonth() + relativeV);
					break;
				
				case "year":
					ts.setFullYear(ts.getFullYear() + relativeV);
					break;
				
				default:
					var dow = this.def.patterns.mwy.substr(1,this.def.patterns.mwy.length-2).split("|").indexOf(relativeMult);
					var day = ts.getDate();
					var testTs;
					if (dow != -1) {
						var cnt = 0;
						do {
							day += relativeV;
							testTs = new Date(ts);
							testTs.setDate(day);
							cnt++;
						} while (testTs.getDay() != dow && cnt < 15);
						var ts = testTs;
					}
					break;					
					
			}
			
			for (var dt = 0; dt < 2; dt++) {
				var res = possible[dt][0];
				
				if (res && res.length) {
					for (var i = 0; i < rid.length; i++) {
						res[0] = res[0].replace(rid[i],"");
					}
					
					var parts = res[0].split(/[^\w\d]+/gi);
					
					for (var i = 0; i < parts.length; i++) {
						var part = parts[i].toLowerCase();
						switch (res[1][i]) {
							case "y":
								ts.setFullYear(Number(part)); break;
						
							case "month":
								ts.setMonth(months.indexOf(part)); break;
								
							case "m":
								ts.setMonth(Number(part) - 1);
								break;
						
							case "d":
								ts.setDate(Number(part)); break;
								
							case "hour":
								timeFound = true;
								part = part.split(/(am|pm)/);
								hour = ampm(Number(part[0],part[1]));
								
								ts.setMinutes(0);
								ts.setSeconds(0);
								break;
							
							case "minute":
								part = part.split(/(am|pm)/);
								hour = ampm(hour,part[1]);
								ts.setMinutes(Number(part[0]));
								break;
							
							case "second":
								part = part.split(/(am|pm)/);
								hour = ampm(hour,part[1]);
								ts.setSeconds(Number(part[0]));
								break;
								
						}
					}
				}
			}
			
			if (hour != -1) ts.setHours(hour);
			
			if (!timeFound) {
				ts.setHours(this.ending?23:0);
				ts.setMinutes(this.ending?59:0);
				ts.setSeconds(this.ending?59:0);
				if (this.ending) ts.setMilliseconds(999);
			}
			
			return ts.getTime() / 1000;
		}
	}
};
