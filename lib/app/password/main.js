
self.request("tokenCheck",[],function(response){
	
	
	self.createView({"contentPadding":"8px"});

	if (0 && response === true) {
		self.view.setContentFromHTMLFile();

		sl.addEventListener(self.view.element("restorePassword"),"click",function() {
			self.request("restorePassword",[],function(response){
				self.view.elementMessage(self.view.element("restorePassword"),
					response ?
						"en-us|Your password has been restored." :
						"en-us|Unkown Error"
				,4);
				});
		});

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
	} else {
		self.view.setContentAsErrorMessage(response);
	}
	
	self.view.center();
	
	self.addEventListener("destruct",function() {
		
	});
});
