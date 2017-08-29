sl.notification = function(o) {
	var self = this;
	sl.initSlClass(this,"notification");

	self.init = function() {
		self.el = sl.dg("",self.core.appBarNotificationsEl,"div",{
			"style":{
				"backgroundImage":"url('"+self.icon+"')"
			}
		});		
		
		self.core.setCommonIcon(self.el,o.icon);
		
		self.el.addEventListener("click",self.click,false);
		
		if (self.persistent) {
			self.slTimer = setInterval(function(){
				self.el.style.opacity = self.el.style.opacity == 0 ? 1 : 0;
				if (self.el.style.opacity == 0) sl.sound("notify");
			},500);
		}

		if (self.message) self.showMessage(self.message);
	};
	
	self.showMessage = function(message) {
		self.mb = new sl.messageBox({"element":self.el,"message":message.split("\n").join("<br>"),"core":self.core,"timer":4});
		self.mb.addEventListener("click",self.click,false);			
	};
	
	self.click = function() {
		if (self.globalAnswer) self.core.net.broadcastEvent("answer-"+self.type,{"id":self.id,"message":self.answerMessage});
		if (self.onclick) self.onclick();
	};
	
	self.destruct = function() {
		if (self.mb) self.mb.destruct();
		if (self.slTimer) clearInterval(self.slTimer);
		if (self.el.parentNode) self.el.parentNode.removeChild(self.el);
	};	
		
	self.setValues({
		"answerMessage":false,
		"globalAnswer":false
	});
	
	if (o) self.setValues(o);
	
	self.init();
}

