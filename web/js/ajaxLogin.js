function ajaxLogin(user,password,callback) {
	function rv(v) {
		if (v === true) sl.config.loggedIn = true;
		if (callback) callback(v);
	};
	
	sl.coreOb.net.send("login",{"user":user,"password":password},{"queueTime":0},function(response){
		if (response) {
			if (response.success) {
				rv(true); return;
			} else {
				switch (response.error) {
					case "logged-in":
						rv(true); return;
						
					case "no-password":
						rv("en-us|Password Cannot be blank."); return;
						
					case "incorrect":
						rv("en-us|Incorrect Log In Name or Password."); return;
					
					case "logged-in-elsewhere":
						rv(sl.format("en-us|Someone is logged in as '%%' on another machine, device, or browser.",user)); return;
						
					default:
						rv(response.error); return;
				}  
			}
		}
		rv("en-us|Log in failed."); return;
	});
}
