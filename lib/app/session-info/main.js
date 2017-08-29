
var info;

self.createView({"contentPadding":"8px"});

self.view.setContentFromHTMLFile();

self.view.element("loginName").innerHTML = sl.config.loggedIn.escapeHtml();

sl.addEventListener(self.view.element("loginName"),"click",function(){
	self.core.open("activity");
});

sl.addEventListener(self.view.element("logout"),"click",function(){
	self.core.logout();
});

self.request("info",[],function(response){
	info = response;
	self.listen("mobile-pass-added",function(type,response){
		self.view.element("mobilePass").slSpecial.append(response);
	});
	
	self.view.element("software").innerHTML = "YibblePoint "+sl.config.version;
	
	initValidation(response);
	
	validationUpdate(response.validation);

	self.view.element("mobilePass").slSpecial.addEventListener("click-*",function(type,o){
		switch (type) {
			case "click-delete":
				self.view.elementPrompt(o.target,"en-us|Are you sure you want to delete this device?",{"yes":"en-us|Yes","no":"en-us|No"},function(choice){
					if (choice == "yes") {
						self.request("deleteMobilePass",[o.item._KEY],function(response){
							self.view.element("mobilePass").slSpecial.remove(o.item._KEY);
						});
					}
				});
				break;
		}
	});
	
	self.view.element("mobilePass").slSpecial.appendMultiple(response.mobilePass);
	self.view.element("qr").src = response.QRAddress;
	
});

//Credits
function updateCredits(credits) {
	self.view.element("creditAmount").innerHTML = sl.format("en-us|%% Credits",credits);
}

if (sl.config.package.userCredits) {
	self.view.element("credits").style.display = "inline";
	updateCredits(sl.config.credits);
}

//Validation
function validationUpdate(validation) {
	info.validation = validation;
	for (var i in validationType) {
		if (info.useValidation.indexOf(i) != -1) {
			validationCB[i].checked = validation.indexOf(i) != -1;
			validationCB[i].title = ("en-us|Your [type] is validated.").replace("[type]",validationType[i]);
		}
	}
};

var validationType = {
	"email":"en-us|E-mail",
	"phone":"en-us|Phone",
	"photo":"en-us|Photo",
	"id":"en-us|ID",
	"billing":"en-us|Billing"
};

var validationCB = {};
function initValidation(info) {
	var el = self.view.element("validation");
	for (var i in validationType) {
		if (info.useValidation.indexOf(i) != -1) {
			var uid = self.makeUID(i);
			validationCB[i] = sl.dg("",el,"input",{
				"type":"checkbox",
				"id":uid
			});
			
			(function(i){
				sl.addEventListener(validationCB[i],"click",function(e){
					var msgEl = this;
					sl.cancelBubble(e);
					e.preventDefault();
					
					if (info.validation.indexOf(i) == -1) {				
						switch (i) {
							case "email":
								self.view.elementPrompt(msgEl,"en-us|An E-mail was sent to "+info.email+"en-us|, please follow the link in that E-mail to confirm your E-mail address. Would you like us to resend this E-mail?",{"yes":"en-us|Yes","no":"en-us|No"},function(choice){
									if (choice == "yes") {
										self.request("sendValidationEmail",[],function(response){
											if (response) {
												self.view.elementMessage(msgEl,"en-us|E-mail sent.",2);
											}
										});
									}
								});
								break;
								
							default:
								self.core.open(i+"-validation-setup");
								break;
						}
					} else {
						self.view.elementMessage(msgEl,msgEl.title,2);
					}
					
				},false);
			})(i);
			
			sl.dg("",el,"label",{
				"htmlFor":uid,
				"innerHTML":validationType[i]
			});
			
			sl.dg("",el,"br");
		}
	}
};


sl.addEventListener(self.view.element("resetPassword"),"click",function() {
	if (self.view.element("resetPassword").slValid) {
		self.request("passwordReset",[self.view.element("password").value],function(response){
			self.view.element("password").value = "";
			self.view.element("confPassword").value = "";

			self.view.element("resetPassword").slValidator.resetAll();
			
			self.view.elementMessage(self.view.element("resetPassword"),
				response ?
					"en-us|Your password has been reset." :
					"en-us|Unkown Error"
			,4);
		});
	}
},false);

self.duration = new sl.value({"type":"duration","minUnit":1});

var conValues = [
	new sl.value({"type":"duration"}),
	new sl.value({"type":"duration"}),
	new sl.value({"type":"bytes"}),
	new sl.value({"type":"percent"}),
	new sl.value({"type":"percent"})
];

function updateDuration() {
	self.duration.setValue(sl.unixTS(true) - sl.config.loginTime);
	self.view.element("loginDuration").innerHTML = self.duration.toString();
	
	var con = [
		["en-us|Average Latency",self.core.net.getAverageLatency()],
		["en-us|Poll Frequency",self.core.net.pollFrequency],
		["en-us|Total Transferred",self.core.net.totalBytesTransfered],
		["en-us|Current Server Load",self.core.net.serverLoad],
		["en-us|Average Server Load",self.core.net.getAverageServerLoad()]
	];
	
	for (var i = 0; i < con.length; i++) {
		if (conValues[i]) {
			conValues[i].setValue(con[i][1]);
			con[i][1] = conValues[i].toString();
		}
	}
	
	self.view.element("connection").innerHTML = con.multiJoin("\n",": ");
}

self.updateTimer = setInterval(updateDuration,1000);
updateDuration();

function appConvert(app) {
	return {
		"ref":app.ref,
		"icon":app.icon?app.icon+"-24.png":"",
		"title":app.title,
		"info":app.views.length == 0 ? "en-us|background" : app.views.length+"en-us| view(s)",
		"noClose":app.noClose
	};
}

function appendApp(app) {
	self.view.element("apps").slSpecial.append(appConvert(app),app.uid);
}

function updateApp(app) {
	self.view.element("apps").slSpecial.update(app.uid,appConvert(app));
}

for (var i = 0; i < self.core.apps.length; i++) {
	if (self.core.apps[i].uid != self.uid) appendApp(self.core.apps[i]);
}

self.view.element("apps").slSpecial.addEventListener("click-*",function(type,o){
	switch (type) {
		case "click-close":
			if (o.item._KEY && !o.item.noClose) {
				self.view.elementPrompt(o.target,"en-us|Are you sure you want to close this app?",{"yes":"en-us|Yes","no":"en-us|No"},function(choice){
					if (choice == "yes") {
						self.core.closeAppByUID(o.item._KEY);
					}
				});
			} else {
				self.view.elementMessage(o.target,"en-us|This app cannot be closed.",2);
			}
			break;
	}
});

self.listen("validation-*",function(type,response){
	type = type.split("-",3);
	validationCB[type[2]].checked = type[1] == "set";
});

var appListener = self.core.addEventListener("app-*",function(type,app){
	switch (type) {
		case "app-open":
			appendApp(app);
			break;
			
		case "app-update":
			updateApp(app);
			break;
			
		case "app-close":
			self.view.element("apps").slSpecial.remove(app.uid);
			break;
	}
});
	
self.view.center();

self.addEventListener("destruct",function() {
	self.core.removeEventListener(appListener);
	clearInterval(self.updateTimer);
});
