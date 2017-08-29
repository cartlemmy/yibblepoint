function trueVault() {
	var self = this;
	
	self.apiUrl = config.API_URL;
	self.vault = config.VAULT_ID;
	
	self.userKey = "";
	self.apiError = false;
	
	self.init = function() {
		
	};
	
	function keyCheckCallback(res,o,cb) {
		var rv = null; // Not a necessarily a bad key, but some sort of connection issue
			
		if (typeof(o) == "object" && o.status === 401) rv = false;
		if (typeof(res) == "object" && res.result === "success") rv = true;
				
		if (cb) cb(rv,res,o);
	};
			
	self.setUserKey = function(key, cb) {
		if (typeof(key) != "string") return;
		self.userKey = key.substr(0,36);
		if (cb) {
			self.request("GET",null,function(res,o){
				keyCheckCallback(res,o,cb);
			});
		}
	};
	
	self.checkUserKey = function(key, cb) {
		self.request("GET",{"checkKey":key.substr(0,36)},function(res,o){
			keyCheckCallback(res,o,cb);
		});
	};
	
	self.setDocumentPermission = function(perms) {
		//TODO
	};
	
	self.post = function(data,cb,raw) {
		self.request("POST",{
			"document":raw?data:JSON.stringify(data),
			"url":self.apiUrl+"vaults/"+self.vault+"/documents"
		},cb,raw);
	};
	
	self.put = function(id,data,cb,raw) {
		self.request("PUT",{
			"document":raw?data:JSON.stringify(data),
			"url":self.apiUrl+"vaults/"+self.vault+"/documents/"+id
		},cb,raw);
	};
	
	self.get = function(id,cb,raw) {
		self.request("GET",{
			"url":self.apiUrl+"vaults/"+self.vault+"/documents/"+id
		},function(res){	
			if (res && !raw) res = JSON.parse(atob(res.responseText));
			if (res) cb(res);
		});
	};
	
	self.remove = function(id,cb) {
		self.request("DELETE",{
			"url":self.apiUrl+"vaults/"+self.vault+"/documents/"+id
		},function(res){	
			if (cb) cb(res);
		});
	};
	
	self.request = function(type, params, cb, raw) {
		if (!params) params = {};
		var o = {
			dataType: "json",
			type: type,
			url: params.url ? params.url : self.apiUrl,
			"timeout":5*60*1000
		};
		
		if (params.document) o.data = "document="+(raw ? params.document : btoa(params.document));
		
		o.beforeSend = function (req) {
			req.withCredentials = true;
      req.setRequestHeader("Authorization", "Basic "+btoa((params.checkKey ? params.checkKey : self.userKey)+":"));
    };
    
		if (0 && (o.data && o.data.length > 1024 * 512)/* || o.type == "POST" || o.type == "PUT"*/) {
			o.auth = (params.checkKey ? params.checkKey : self.userKey)+":";
			//Send via server proxy
			core.request("true-vault",o,function(res){
				console.log(res);
				if (cb) cb(res);
			});
		} else {
			$.ajax(o).done(function(res) {
				//console.log(r);
				if (cb) cb(res);
			}).error(function(o1,o2,o3){
				switch (o1.status) {
					case 200: //Not sure why this happens
						cb(o1);
						return;
						
					case 401:
						alert("You are not authorized to perform this action, please contact your administrator.");
						break;
				}
				console.log("TrueVault Error: ");
				console.log("\t",o.url);
				console.log("\tlength: "+o.data.length);
				console.log("\t",o1,o2,o3);
				if (cb) cb(false,o1,o2,o3);
			});
		}
	};
};
