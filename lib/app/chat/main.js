self.require("chat.js",function(){

var conversations = {}, internal = {};

var statusDesc = {
	"unanswered":"en-us|Unanswered",
	"answered":"en-us|Answered",
	"closed":"en-us|Chat Closed",
};
				
function updateConversation(user,info) {
	if (!conversations[user]) {
		var tr = sl.dg("",self.view.element("conversations"),"tr");
		conversations[user] = [
			sl.dg("",tr,"td"),
			sl.dg("",tr,"td"),
			sl.dg("",tr,"td"),
			"",
			""
		];
		tr.style.cursor = "pointer";
		tr.addEventListener("click",function(){
			if (conversations[user][3] == "unanswered") {
				self.core.net.sendEvent(user,"chat-answered",{"user":sl.config.user,"name":sl.config.name});
				self.core.net.broadcastEvent("answer-remote-chat-request",{"id":"remote-chat-request-"+user,"message":"en-us|Answered"});
			}
			initChat(user);
		},false);
	}
	if (info.name) conversations[user][0].innerHTML = info.name;
	if (info.answeredBy) conversations[user][1].innerHTML = info.answeredBy ? info.answeredBy : "---";
	if (info.status) {
		conversations[user][2].innerHTML = statusDesc[info.status];
		conversations[user][3] = info.status;
	}
	if (info.name) conversations[user][4] = info.name;
	self.view.updateNoItemMessage("support");
};


function addInternalUserRow(user,info) {
	internal[user].remove = false;
	var tr = sl.dg("",self.view.element("internal-users"),"tr");
	tr.style.cursor = "pointer";
	tr.addEventListener("click",function(){
		initChat(this.user);
	},false);
	tr.user = user;
	internal[user].td = sl.dg("",tr,"td",{"innerHTML":(internal[user].name?internal[user].name:user)});
	internal[user].tr = tr;
	if (!internal[user].name) {
		self.request("getUserInfo",[user],function(info) {
			if (info) {
				internal[user].td.innerHTML = internal[user].name = info.name;
			}
		});
	}
};

self.request("getUserStatus",[],function(info) {
	if (info) {
		var user;
		
		for (var user in info) {
			if (info[user].active && user != sl.config.user) {
				internal[user] = info[user];
				addInternalUserRow(user,info);
			}	
		}
		self.view.updateNoItemMessage("internal");
	}
});

function removeConversation(user) {
	if (conversations[user]) {
		var tr = conversations[user][0].parentNode;
		tr.parentNode.removeChild(tr);
		conversations[user] = null;
		self.view.updateNoItemMessage("internal");
		self.view.updateNoItemMessage("support");
	}
};

self.appendChat = function(user,message) {
	initChat(user,function(){
		sl.sound("chat");
		if (typeof(message) == "string") message = [message];
		var m;
		while (m = message.shift()) {
			self.chatInstances[user].receiveMessage(m);				
		}
	});
};

self.messageListener = self.core.net.addEventListener("chat-message",function(type,res){
	self.appendChat(res.from,res.message);
	updateHistoryCnt();
});

	
self.userStatusListener = self.addServerListener("user-status-change",function(status){
	var available = false;
	for (var i in status) {
		if (i != sl.config.user) {
			if (status[i].active != (internal[i] !== undefined)) {
				if (status[i].active) {
					internal[i] = status[i];
					addInternalUserRow(i,status[i]);
				} else {
					internal[i].tr.parentNode.removeChild(internal[i].tr);
					internal[i] = undefined;
				}
			}
		}
	}
	self.view.updateNoItemMessage("internal");
});

self.disconnectListener = self.core.net.addEventListener("remote-chat-*",function(type,res){
	switch (type) {
		case "remote-chat-disconnect":
			if (self.chatInstances[res.user]) {
				sl.sound("disconnect");
				self.chatInstances[res.user].disconnect();
			}
			removeConversation(res.user);
			break;
		
		default:
			updateConversation(res.user,res);
			break;
	}
});

self.addEventListener("destruct",function() { 
	for (var user in self.chatInstances) {
		self.request("closed",[user],function(info) {});
	}
	if (self.messageListener) self.removeServerListener(self.messageListener);
	if (self.disconnectListener) self.removeServerListener(self.disconnectListener);
});

self.createView({"contentPadding":"8px","tools":["search"]});

self.view.setContentFromHTMLFile();

self.view.center();

self.chatInstances = {};

self.request("getRemoteConversations",[],function(res){
	for (var user in res) {
		if (res[user]) updateConversation(user,res[user]);
	}
});


function initChat(user,cb) {
	if (self.chatInstances[user]) {
		//self.chatInstances[user].show(true);
		if (cb) cb();
	} else {
		self.request("getUserInfo",[user],function(info) {
			if (info) {
				self.chatInstances[user] = new sl.chatInstance({
					"user":user,
					"name":info.name,
					"core":self.core,
					"tabs":self.view.element("tabs").slSpecial,
					"initialMessage":
						info.server ?
							sl.format("en-us|You are chatting with %% (%%).\nIP: %%\nE-mail: %%",info.name,info.user,info.server.REMOTE_ADDR,info.email) :
							false
				});
				self.chatInstances[user].addEventListener("remove",function(){
					self.request("closed",[user],function(info) {});
					self.chatInstances[user] = null;
				});
				if (cb) cb();
			}
		});
	}
}

self.showChat = initChat;

self.open = function(user) {
	initChat(user);
};

function closeChat(user) {
	self.chatInstances[user].destruct();
	self.chatInstances[user] = null;
}



var scroller = self.view.element("history").slSpecial;
var totalHistory = 0;

function updateHistoryCnt() {
	self.request("historyCnt",[],function(cnt){
		totalHistory = cnt;
		scroller.setItemCount(cnt);
		scroller.scrollToItem(cnt);
	});
};
updateHistoryCnt();

scroller.requestSections = function() {
	self.request("sections",[],function(r){
		scroller.setSections(r);
	});
};
	
scroller.requestItem = function(itemIndex) {
	var scrollerItem = this;
	scrollerItem.loadingMessage(["ts","from","to","message"]);
	
	self.request("history",[itemIndex],function(res){				
		scrollerItem.element("ts").slValue.setValue(res.ts);
		scrollerItem.element("from").innerHTML = res.from;
		scrollerItem.element("to").innerHTML = res.to;
		scrollerItem.element("message").innerHTML = res.message;
		scrollerItem.setAsLoaded();
	});
};

if (self.args[0]) initChat(self.args[0]);

});
