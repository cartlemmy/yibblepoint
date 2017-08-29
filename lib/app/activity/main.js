
var activityDef = {
	"logged-in":{"name":"en-us|Logged In"},
	"logged-out":{"name":"en-us|Logged Out"},
	"password-changed":{"name":"en-us|Password Changed","details":function(a){
		return a.extra ? "IP: "+a.extra._SERVER.REMOTE_ADDR : "";
	}},
	"password-restore":{"name":"en-us|Password Restored"},
	"validation-.*":{
		"name":function(a){
			var t = a.type.split("-");
			return (t[1] == "set" ? "en-us|Validated" : "en-us|Validation Revoked");
		},"details":function(a){
			var vt = {
				"email":"en-us|E-mail",
				"phone":"en-us|Phone",
				"photo":"en-us|Photo",
				"id":"en-us|ID",
				"billing":"en-us|Billing"
			};
			var t = a.type.split("-");
			return vt[t[2]];
	}},
	"failed-login":{"name":"en-us|Failed Log In","details":function(a){
		return "PW: "+a.extra.crit.password+" IP: "+a.extra._SERVER.REMOTE_ADDR;
	}},
	"logged-in-elsewhere":{"name":"en-us|Log In Attempted...","details":function(a){
		return "en-us|...while logged in elsewhere (IP: "+a.extra._SERVER.REMOTE_ADDR+")";
	}}
};

self.createView({"contentPadding":"0px","noScroll":true});

self.view.setContentFromHTMLFile();

var scroller = self.view.element("activities").slSpecial;
var totalActivity = 0;

self.request("activityCnt",[],function(cnt){
	totalActivity = cnt;
	scroller.setItemCount(cnt);
	scroller.scrollToItem(cnt);
});

self.listen("user-activity",function(type,response){
	totalActivity ++;
	scroller.setItemCount(totalActivity);
	console.log(response);
});

scroller.requestSections = function() {
	self.request("sections",[],function(r){
		scroller.setSections(r);
	});
};
	
scroller.requestItem = function(itemIndex) {
	var scrollerItem = this;
	scrollerItem.loadingMessage(["ts","type","details"]);
	self.request("activity",[itemIndex],function(activity){
		
		var def = activityDef[activity.type];
		
		if (!def) {
			for (var i in activityDef) {
				if (activity.type.search(new RegExp("^"+i+"$")) != -1) {
					def = activityDef[i];
					break;
				}
			}
		}
		
		scrollerItem.element("ts").slValue.setValue(activity.ts);
		scrollerItem.element("type").innerHTML = def ? (typeof(def.name) == "function" ? def.name(activity) : def.name) : activity.type;
		scrollerItem.element("details").innerHTML = (def && def.details ? def.details(activity) : "");
		scrollerItem.setAsLoaded();
	});
};
	
self.view.center();

self.addEventListener("destruct",function() {
	
});
