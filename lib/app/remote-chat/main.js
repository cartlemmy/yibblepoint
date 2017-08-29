self.request("getInfo",[],function(info){	
	
	function setHideType(el) {
		if (el.style.visibility == "hidden") {
			el.slHideType = "visibility";
		} else {
			el.slHideType = "display";
		}
	}
	
	function showEl(el,show) {
		if (el.slHideType == "visibility") {
			el.style.visibility = show ? "visible" : "hidden";
		} else {
			el.style.display = show ? "" : "none";
		}
	}

	if (info) {
		self.log(sl.jsonEncode(info));
		if (info.isAdmin) {
			var chatButtonEl
			if (chatButtonEl = sl.dg("slChatButton")) {
				setHideType(chatButtonEl);
				showEl(chatButtonEl,true);
				chatButtonEl.addEventListener("click",function() {
					alert("en-us|You cannot request support because you are logged in as a support admin.");
				});
			}
			return;
		}
		var defInfo, infoChanged = false;
		
		self.userStatusListener = self.addServerListener("user-status-change",function(status){
			var available = false;
			for (var i in status) {
				if (status[i].chatAvailable) {
					available = true;
					break;
				}
			}
			setAvailability(available);
		});

		self.chatAnsweredListener = self.core.net.addEventListener("chat-answered",function(type,res){
			if (res) {
				requesting = false
				self.core.net.forcePollFrequency(2);
				connectedTo = res;
				self.request("answered",[res.user],function(res){
					//console.log(res);
				});
				appendMessage(null,sl.format("en-us|You are now connected to %%.",res.name));
				if (connectTimer) clearTimeout(connectTimer);
			}
		});
		
		self.chatMessageListener = self.core.net.addEventListener("chat-message",function(type,res){
			if (res) {
				appendMessage(connectedTo.name,res.message);
			}
		});
			
		var chatButtonEl = sl.dg("slChatButton");
		setHideType(chatButtonEl);
		
		function showChat() {
			showEl(chatContEl,true);
			if (info.email) {
				if (infoChanged) {
					connect(info);
				} else {
					connect();
				}
			} else {
				getInput("en-us|Before we can chat we need a bit of information.\n\nPlease enter your E-mail address in the box below, then press enter.",function(email){
					if (!email) return hideChat();
					info.email = email;
					getInput("en-us|Please enter your name in the box below, then press enter to connect.",function(name){
						if (!name) return hideChat();
						info.name = name;
						connect(info);
					});
				});
			}
		};
		
		function hideChat() {
			showEl(chatContEl,false);
			disconnect();
		};
		
		sl.addEventListener(chatButtonEl,"click",showChat);
		
		var chatBoxEl = sl.dg("slChatBox");
		chatBoxEl.style.overflow = "auto";

		var chatContEl = sl.dg("slChatCont") ? sl.dg("slChatCont") : chatBoxEl.parentNode;
		setHideType(chatContEl);

		var chatCloseEl = sl.dg("slChatClose");
		
		if (chatCloseEl) {
			sl.addEventListener(chatCloseEl,"click",function(){
				hideChat();
			},false);
		}
			
		var chatInputEl = sl.dg("slChatInput");
		
		function setAvailability(available) {
			chatButtonEl.style.cursor = "pointer";
			showEl(chatButtonEl,available);
		}
			
		setAvailability(info.available);
		
		if (defInfo = sl.dg("slChatUser")) {
			defInfo = defInfo.value.multiSplit(";",":");
			if (info.email != defInfo.email) {
				info = defInfo;
				infoChanged = true;
			}
		}

		var connectedTo = false, requesting = false;
		var currentInput = null;
		var userInfo = {"email":info.email?info.email:"","name":info.name?info.name:""};
		
		sl.addEventListener(chatInputEl,"keypress",function(e){
			if (e.keyCode == 13) {
				if (submitMessage(chatInputEl.value)) chatInputEl.value = "";
			}
		});

		function submitMessage(text) {
			if (currentInput) {
				var oldInput = currentInput;
				currentInput(text);
				if (oldInput == currentInput) currentInput = null;
				return true;
			} else {
				if (connectedTo) {
					self.core.net.sendEvent(connectedTo.user,"chat-message",{"message":text});
					appendMessage("You",text);
					return true;
				}
				appendMessage(null,"en-us|You can send your message as soon as you are connected."+(sl.config.package.phone?" "+sl.format("en-us|If your issue is urgent please call %%.",sl.config.package.phone):""));
			}
			return false;
		}
			
		function appendMessage(from,text) {
			var cont = sl.dg("",chatBoxEl,"div",{});
			if (from) {
				sl.dg("",cont,"label",{
					"innerHTML":from+":"
				});
			} else cont.className = "system";
			sl.dg("",cont,"div",{
				"innerHTML":text.escapeHtml()
			});
			sl.dg("",chatBoxEl,"div",{"style":{"clear":"both"}});
		};
		
		function getInput(text,cb) {
			if (sl.browser == "IE" && sl.version < 9) {
				appendMessage(null,text);
				currentInput = cb;
			} else {
				var res = prompt(text,"");
				cb(res);
			}
		};

		var connectTimer = null;
		self.tryNum = 0;
		
		function connect(setInfo) {
			requesting = true;
			if (self.tryNum == 2 || self.tryNum == 0) {
				appendMessage(null,self.tryNum == 0 ?
					sl.format("en-us|Welcome %%, please wait while we connect you to a representative.",info.name) :
					"en-us|We are still trying to connect you. "+(sl.config.package.phone?sl.format("en-us|If your issue is urgent please call %%.",sl.config.package.phone):"")
				);
			}
			if (self.tryNum == 0) {
				self.log("chatRequest");
				self.request("chatRequest",[setInfo],function(res){
					//self.log(res);
				});
			}
			self.tryNum ++;
			connectTimer = setTimeout(connect,10000);
		};

		function disconnect() {
			if (connectTimer) clearTimeout(connectTimer);
			self.tryNum = 0;
			self.core.net.forcePollFrequency(false);
			if (connectedTo || requesting) {
				self.request("disconnect",[],function(res){
					connectedTo = null;
					requesting = false;
					sl.removeChildNodes(chatBoxEl);
				});
			}
		};
		
		self.addEventListener("destruct",function() {
			disconnect();
			if (self.userStatusListener) self.removeServerListener(self.userStatusListener);
			if (self.chatAnsweredListener) self.removeServerListener(self.chatAnsweredListener);
			if (self.chatMessageListener) self.removeServerListener(self.chatMessageListener);
		});
	} else {
		self.log("Failed to retrieve info.");
	}
});
