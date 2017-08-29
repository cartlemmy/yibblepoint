sl.chatInstance = function(o) {
	var self = this;
	sl.initSlClass(this,"chat-instance");
	
	self.init = function() {
		self.tab = self.tabs.addTab(self.name,{"closeable":true});
		self.tab.chat = self;
		
		self.tab.addEventListener("remove",self.remove);
		self.tab.addEventListener("appeared",self.show);
				
		self.chatBoxEl = sl.dg("",self.tab.section,"div",{"className":"sl-chat-box"});
		self.chatInputEl = sl.dg("",self.tab.section,"input",{"className":"sl-chat-input"});

		sl.addEventListener(self.chatInputEl,"keypress",function(e){
			self.tabs.checked(self.tab);
			if (e.keyCode == 13) {
				if (self.submitMessage(this.value)) this.value = "";
			}
		});
	
		if (self.initialMessage) self.appendMessage(null,self.initialMessage);
		self.show();
	};
		
	self.remove = function() {
		self.dispatchEvent("remove");
	};
	
	self.disconnect = function() {
		self.appendMessage(null,sl.format("en-us|%% has disconnected from the chat.",self.name));
	};
	
	self.show = function(connectedMessage) {
		self.tabs.setSelected(self.tab);
		if (connectedMessage) self.appendMessage(null,sl.format("en-us|Chatting with %%.",self.name));
		self.chatInputEl.focus();
	};

	self.submitMessage = function(text) {
		self.core.net.sendEvent(self.user,"chat-message",{"message":text,"ts":sl.unixTS()});
		self.appendMessage("You",text);
		return true;
	};
	
	self.receiveMessage = function(text) {
		self.appendMessage(self.name,text);
		self.tabs.updated(self.tab);
	};
	
	self.appendMessage = function(from,text) {
		var cont = sl.dg("",self.chatBoxEl,"div",{});
		if (from) {
			sl.dg("",cont,"label",{
				"innerHTML":from+":"
			});
		} else cont.className = "system";
		sl.dg("",cont,"div",{
			"innerHTML":text.escapeHtml().split("\n").join("<br />")
		});
		sl.dg("",self.chatBoxEl,"div",{"style":{"clear":"both"}});
	};
	
	self.setValues({

	});	
	
	if (o) self.setValues(o);
	
	self.init();
};
