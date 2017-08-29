self.createView({"contentPadding":"8px","options":["no-close-button","no-minimize-button","no-size-button","no-resize"]});

self.view.setContentFromHTMLFile();

var mobilePassRefreshTimer = null;

if (sl.config.package.disableMobilePass) {
	var el = self.view.element("qrCont");
	el.parentNode.removeChild(el);
} else {

	function mobilePassRefresh() {
		self.request("mobilePass",[],function(response){
			if (response) {
				self.view.element("qr").src = response;
				mobilePassRefreshTimer = setTimeout(mobilePassRefresh,14 * 60 * 1000);
			} else {
				self.view.element("qr").style.display = 'none';
			}
		});
	};
	mobilePassRefresh();

	sl.addEventListener(self.view.element("qr"),"click",function(){
		self.view.elementMessage("qr","en-us|Scan this QR code to login with your mobile device.",5);
	},false);
}

self.view.center();

function loggedIn(response) {
	sl.config.loggedIn = response.formattedUser;
	sl.config.user = response.user;
	sl.config.name = response.name;
	sl.config.credits = response.credits;
	sl.config.loginTime = response.loginTime;
	self.core.net.forcePollFrequency(false);
	self.core.login();
};

self.listen("mobile-pass-login",function(type,response){
	loggedIn(response);
	self.view.element("message").innerHTML = "en-us|Logged in via mobile pass.";
	self.destruct();
	self.core.initInterface();
});

function logIn(){
	if (!self.view.element("user").value) self.view.elementMessage("user","en-us|Cannot be blank.");
	if (!self.view.element("password").value) self.view.elementMessage("password","en-us|Cannot be blank.");
	
	if (self.view.element("user").value && self.view.element("password").value) {
		self.view.element("message").innerHTML = "en-us|Attempting Log In...";
		self.core.net.send("login",{"user":self.view.element("user").value,"password":self.view.element("password").value},{"queueTime":0},function(response){
			if (response) {
				self.view.element("logout-other").style.display = "none";
				if (response.success) {
					loggedIn(response);
					self.view.element("message").innerHTML = "en-us|Success.";
					self.destruct();
					self.core.initInterface();
				} else {
					switch (response.error) {
						case "logged-in":
							self.view.element("message").innerHTML = "en-us|You are already logged in.";
							self.destruct();
							self.core.initInterface();
							break;
							
						case "no-password":
							self.view.elementMessage("password","en-us|Cannot be blank.");
							break;
							
						case "incorrect":
							self.view.element("message").innerHTML = "en-us|Incorrect Log In Name or Password.";
							break;
						
						case "logged-in-elsewhere":
							self.view.element("logout-other").style.display = "";
								self.view.element("message").innerHTML = sl.format("en-us|Someone is logged in as '%%' on another machine, device, or browser. Click 'Force Log In' to log in by logging out the other session.",self.view.element("user").value);
							break;
							
						default:
							self.view.element("message").innerHTML = response.error;
							break;
					}  
				}
			} else {
				self.view.element("message").innerHTML = "en-us|Log in failed.";
			}
		});
	} 
};

self.view.addEventListener("keypress-enter",logIn);
sl.addEventListener(self.view.element("login"),"click",logIn,false);

self.addEventListener("destruct",function() {
	if (mobilePassRefreshTimer) clearTimeout(mobilePassRefreshTimer);
});


sl.addEventListener(self.view.element("logout-other"),"click",function(){
	self.view.element("message").innerHTML = sl.format("en-us|Logging out other session...");

	self.core.net.send("force-logout",{"user":self.view.element("user").value,"password":self.view.element("password").value},{"queueTime":0},function(response){
		function check() {
			self.core.net.send("login-status",{"user":self.view.element("user").value},{"queueTime":0},function(response){
				if (response && response.loggedIn) {
					setTimeout(check,500);
				} else {
					logIn();
				}
			});
		};
		setTimeout(check,500);
	});
},false);

