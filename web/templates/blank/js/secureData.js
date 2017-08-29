function secureData() {
	var self = this;
		
	self.userKey = "";
	self.apiError = false;
	
	self.init = function() {
		
	};
	
	function keyCheckCallback(res,o,cb) {
		var rv = null; // Not a necessarily a bad key, but some sort of connection issue
			
		if (typeof(o) == "object" && o.status === 401) rv = false;
		if (typeof(res) == "object") rv = res.result === "success";
				
		if (cb) cb(rv,res,o);
	};
		
	self.setUserKey = function(key, cb) {
		key = self.cleanKey(key);
		if (typeof(key) != "string") return;
		self.userKey = key.substr(0,config.KEY_LENGTH);
		if (cb) {
			self.request("GET",null,function(res,o){
				keyCheckCallback(res,o,cb);
			});
		}
	};
	
	self.checkUserKey = function(key, cb) {
		key = self.cleanKey(key);
		self.request("GET",{"checkKey":key.substr(0,config.KEY_LENGTH)},function(res,o){
			keyCheckCallback(res,o,cb);
		});
	};
	
	self.cleanKey = function(key) {
		if (typeof(key) != "string") return String(key);
		key = key.replace(/[^A-Za-z0-9]/gi,'');
		
		var simChars = "8B0O1I5S";
		for (var i = 0; i < simChars.length; i += 2) {
			key = key.split(simChars.charAt(i)).join(simChars.charAt(i + 1))
		}
		
		if (key.length) key = key.toUpperCase().match(/.{1,4}/g).join("-");
		
		return key;
	};
	
	self.setDocumentHeader = function(n,v) {
		if (!self.documentHeader) self.documentHeader = {};	
		self.documentHeader[n] = v;
	};
	
	self.setDocumentPermission = function(perms) {
		self.setDocumentHeader("perms",perms);
	};
	
	self.post = function(data,cb,raw) {
		self.request("POST",{
			"document":raw?data:JSON.stringify(data)
		},cb,raw);
	};
	
	self.put = function(id,data,cb,raw) {
		self.request("PUT",{
			"document":raw?data:JSON.stringify(data),
			"dID":id
		},cb,raw);
	};
	
	self.get = function(id,cb,raw) {
		self.request("GET",{
			"dID":id
		},function(res){	
			if (res && res.document && !raw) res.document = JSON.parse(res.document);
			if (res) cb(res.document);
		});
	};
	
	self.remove = function(id,cb) {
		self.request("DELETE",{
			"dID":id
		},function(res){	
			if (cb) cb(res);
		});
	};
	
	self.request = function(type, params, cb, raw) {
		if (!params) params = {};
		params.type = type;
		if (self.documentHeader) params.head = self.documentHeader;
		//params.auth = (params.checkKey ? params.checkKey : self.userKey)+":";
		
		core.request("secure-data",params,function(res){
			if (cb) cb(res);
		});
		
	};
};
