{
	"not-empty":function(v) {
		if (v === null || String(v).trim() == "") {
			return this.fail("en-us|Cannot be empty");
		}
		return true;
	},
	"agree-terms":function(v) {
		if (!v)	return this.fail("en-us|You must agree to the terms by checking the checkbox.");
		return true;
	},
	"email":function(v) {
		var self = this;
		function fail(d,f,s) {
			return self.fail(sl.format("en-us|Invalid E-mail address (%%).",d),f,s);
		};
		
		if (v === null || String(v).trim() == "") return true;
		
		var commonDomains = [
			"gmail.com","hotmail.com","aol.com","comcast.net","yahoo.com",
			"cox.net","msn.com","earthlink.net"
		];

		v = v.split(";").join(",").trim().toLowerCase();
		
		if (v.indexOf(",") != -1) {
			v = v.split(",").shift().trim();
			return fail("en-us|More than one address listed","en-us|Use one address.",v);
		}
		
		if (v.length > 255) {
			return fail("en-us|E-mail too long","en-us|Shorten to 255 characters or less.");
		}
		
		if (v.indexOf(" ") != -1) {
			return fail("en-us|Spaces in E-mail address","en-us|Remove spaces.",v.split(" ").join(""));
		}
		
		var fixed, atCount = v.split("@").length - 1;
		if (atCount == 0) {
			fixed = false;

			for (var i in commonDomains) {
				var domain = commonDomains[i];
				if (v.substr(v.length - domain.length) == domain) {
					fixed = v.substr(0,v.length - domain.length) + "@" + domain;
					break;
				}
			}
			return fail("en-us|One @ symbol required","en-us|Add the @ symbol",fixed?fixed:undefined);
		} else if (atCount > 1) {
			v = v.split("@");
			v = v.shift()+"@"+v.join("");
			return fail("en-us|Only one @ symbol allowed","en-us|Remove extra @ symbols",v);
		}
		
		var ld = v.split("@");
		var local = ld[0], domain = ld[1];
		
		if (domain.trim() == "") {
			return fail("en-us|No domain specified","en-us|Add domain after the @ symbol");
		}
		
		if (local.length > 64) {
			return fail("en-us|Local part too long");
		}
		
		if (local.charAt(0) == ".") {
			while (local.charAt(0) == ".") {
				local = local.substr(0);
				v = local+"@"+domain;
			}
			return fail("en-us|Cannot start with dot (.)","en-us|Remove the dot",v);
		}
		
		if (local.charAt(local.length-1) == ".") {
			while (local.charAt(local.length-1) == ".") {
				local = local.substr(0,local.length-1);
				v = local+"@"+domain;
			}
			return fail("en-us|Dot (.) cannot come before @ symbol","en-us|Remove the dot",v);
		}
				
		var allowed = "";
		var reg = /[^\.\w\d\!\#\$\%\&\'\*\+\-\/\=\?\^\_\`\{\|\}\~]+/g;
		if (local.replace(reg,"").length != local.length) {
			local = local.replace(reg,"");
			return fail("en-us|Invalid character(s)","en-us|Remove the invalid character(s)",local+"@"+domain);
		}
		
		var reg = /[^\w\d\.\-]+/g;
		if (domain.replace(reg,"").length != domain.length) {
			domain = domain.replace(reg,"");
			return fail("en-us|Invalid character(s)","en-us|Remove the invalid character(s)",local+"@"+domain);
		}
		
		if (v.indexOf("..") != -1) {
			return fail("en-us|Two or more dots (..) in a row not allowed","en-us|Remove the extra dot(s)",v.replace(/[\.]+/,"."));
		}
		return true;
	},
	"unique-email":{"immediate":true,"check":function(v) {
		if (v.trim() == "") return true;

		(function(){
			var dcn = self.delayedCheck();
			self.core.net.send("email-check",{
				"email":v,
				"userID":self.userID?self.userID:0
			},{},function(o){
				if (!self.delayedCheckCancelled(dcn)) {
					if (o.success) {
						self.pass();
					} else {
						self.fail("en-us|Email address already in use.");
					}
				}
			});
		})();
		
		return false;
	}},
	"user":{"immediate":true,"check":function(v) {
		if (v.trim() == "") return true;
		var reg = /[^\w\d\_]+/g;
		if (v.replace(reg,"").length != v.length) {
			return this.fail("en-us|Only letters, numbers and underscores allowed","en-us|Remove the invalid character(s)",v.replace(reg,""));
		}

		this.core.net.send("user-check",{
			"user":v,
			"userID":self.userID?self.userID:0
		},{},function(o){
			if (o.success) {
				self.pass();
			} else {
				self.fail("en-us|User Name already taken.");
			}
		});

		return false;
	}},
	"password":{"immediate":true,"check":function(v) {			
		if (!v || v.trim() == "") return true;
		var vOrig = v;
		var reasons = 0;
		if (v.length < 5) reasons |= 1;
		var w = ("123456789,password1,qwerty1,jesus1,abc123,letmein,testing,lovely,hello1,monkey1,dragon,trustno1,11111111,iloveyou1,shadow,christ,sunshine,master,computer,princess1,tigger,football1,angels,123123,whatever,freedom,killer,asdfgh,soccer1,superman,michael,cheese,internet,joshua,fuckyou1,blessed,baseball1,starwars,0,purple,jordan23,faith,summer1,ashley1,buster,heaven,pepper,7777777,hunter,andrew,thomas,charlie,danielle,jennifer,single,hannah,qazwsx,happy,matrix,aaaaaa,654321,amanda,nothing,ginger,mother,snoopy,jessica,welcome,pokemon,mustang,helpme,justin,jasmine,orange1,apple,michelle,peace,secret,grace,william,iloveyou2,nicole1,666666,muffin,gateway,asshole1,hahaha,poop,blessing,blahblah,myspace1,matthew,canada,silver,robert,forever,rachel,rainbow,guitar,peanut,batman,cookie,bailey,mickey,biteme,eminem,dakota,samantha,compaq,diamond,taylor,forum,john316,richard,blink182,peaches,cool,flower1,scooter,banana,james,asdfasdf,victory,london,123qwe,123321,startrek,george,winner,maggie,trinity,online,123abc,chicken,junior,passw0rd,austin,sparky,admin,merlin,google,friends,hope,shalom,nintendo,looking,harley,smokey,joseph,lucky,digital,thunder,spirit,bandit,enter,anthony,corvette,hockey,power,benjamin,iloveyou!,1q2w3e4r,viper,genesis,knight,creative,foobar,adidas,rotimi,slayer,wisdom,praise,zxcvbnm,samuel,mike,dallas,green,testtest,maverick,onelove,david,mylove,church,god,destiny,none,microsoft,222222,bubbles,cocacola,ilovegod,loving,nathan,emmanuel,scooby,fuckoff,sammy,maxwell,jason,baby,red123,blabla,chelsea,55555,angel1,hardcore,dexter,saved,112233,hallo,jasper,kitten,cassie,stella,prayer,hotdog,password2,nigger1,fuckyou!,cheer1,fuckyou2,dancer1,bitch1,soccer2,123456a,eagles1,volcom1,chris1,summer06,love123,nigga1,fucker1,phpbb,pastor").split(",");
		var entropy = 1;		
		
		function commonCheck(v,leet) {
			for (var i = 0; i < w.length; i++) {
				for (var j = w[i].length; j >= 3; j--) {
					var t = w[i].substr(0,j);
					if (v.indexOf(t) != -1) {
						entropy *= w.length;
						v = v.replace(t,"");
						if (v == "") {
							reasons |= leet ? 64 : 4;
						} else {
							reasons |= leet ? 32 : 2;
						}
						break;
					}
				}
			}
			return v;
		}
		
		
		v = commonCheck(v);
		
		var entPerChar = 0, hasChars = 0;
		if (v.replace(/[A-Z]+/,"") != v) { hasChars |= 8; entPerChar += 26; }
		if (v.replace(/[a-z]+/,"") != v) { hasChars |= 16; entPerChar += 26; }
		if (v.replace(/\d+/,"") != v) { hasChars |= 2; entPerChar += 10; }
		if (v.replace(/[^\w\d]+/,"") != v) { hasChars |= 4; entPerChar += 20; }
		
		//LE3T check
		v = commonCheck(v.leetReplace(),1);
		
		if (hasChars & 24) hasChars |= 1;
		hasChars &= 7;
		if (hasChars < 7) reasons |= 8;
		entropy *= Math.pow(entPerChar,v.length);
		
		//Penalize using number at the end
		var m;
		if (m = vOrig.match(/\d+$/)) {
			reasons |= 16;
			if(entPerChar > 10) entropy /= Math.pow(entPerChar - 10, m.length);
		}
		
		//convert to bits of entropy
		entropy = Math.log(entropy)/Math.log(2);
		
		var s = ["en-us|is very weak","en-us|is weak","en-us|is weak","en-us|is fair","en-us|could be better","en-us|is good"];
		var c = ['C00','C60','C90','CC0','9C0','0C0'];
		
		function reason() {
			var r = [
				'en-us|Too short',
				'en-us|Contains a commonly used password',
				'en-us|Is a commonly used password',
				'en-us|A password of this length should contain '+(function(){
					var c = ["en-us|mixed case","en-us|numbers","en-us|symbols"];
					var rv = []
					for (var i = 0; i < 3; i++) {
						if (!(hasChars & Math.pow(2,i))) rv.push(c[i]);
					}
					if (rv.length > 1) rv[rv.length - 1] = "en-us|and/or "+rv[rv.length - 1];
					return rv.join(", ");
				})(),
				"en-us|Ends in a number",
				'en-us|Contains a commonly used LE3T password',
				'en-us|Is a commonly used LE3T password',
			], rv = [];
			
			for (var i = 0; i < r.length; i ++) {
				if (reasons & Math.pow(2,i)) rv.push("• "+r[i]+".");
			}
			if (!rv.length) rv.push("en-us|• You're close, make a bit longer.");
			return rv.join("\n");
		};
		
		var i = Math.min(5,Math.floor(entropy/10));
		this.message("en-us|Password strength "+s[i]+"."+(entropy < 50 ? " <a href=\"javascript:;\" onclick=\"alert(unescape('"+escape(reason())+"'));sl.cancelBubble(event);\" tabindex=\"-1\">WHY?</a>" : "")+"<br /><div class='meter' style='width:220px;'><div style='height:4px;background-color:#"+c[i]+";width:"+Math.floor(Math.min(5,entropy/10)*20)+"%'></div></div>",null,null,entropy >= 50 ? 0 : 1);				
		
	}},
	"password-confirm":{"immediate":true,"check":function(v) {
		if (v != self.view.element('type=password').value) return this.fail("en-us|Passwords don't match.");
		return true;
	}},
	"regexp":{"check":function(v) {
		var pattern = self.field.getAttribute("data-regexp-format");
		if (pattern) {
			if (!v.match(new RegExp(pattern,"i"))) return this.fail(self.field.getAttribute("data-regexp-error"));
		}		
		return true;
	}}		
}
