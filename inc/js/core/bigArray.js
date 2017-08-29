sl.bigArray = function(type) {
	var self = this;
	
	self.count = 0;
	
	self.data = null;
	
	self.push = function(v) {
		if (typeof(v) != "number") v = 0;
		self.set(self.count++,v);
	};
	
	self.dataLength = function() {
		return self.data ? self.data.length : 0;
	};
	
	self.increaseDataLength = function() {
		var len = self.dataLength();
		if (len == 0) {
			len = 32;
		} else {
			len *= 2;
		}
		var oldData = self.data;
		
		switch (type) {
			case "Int8Array":
				self.data = new Int8Array(len); break;
			
			case "Uint8Array":
				self.data = new Uint8Array(len); break;
			
			case "Int16Array":
				self.data = new Int16Array(len); break;
			
			case "Uint16Array":
				self.data = new Uint16Array(len); break;
			
			case "Int32Array":
				self.data = new Int32Array(len); break;

			case "Uint32Array":
				self.data = new Uint32Array(len); break;
			
			case "Float32Array":
				self.data = new Float32Array(len); break;
				
			case "Float64Array":
				self.data = new Float64Array(len); break;
		}
		
		if (oldData) self.data.set(oldData);
		
	};
	
	self.set = function(i,v) {
		if (i >= self.dataLength()) self.increaseDataLength();
		self.data[i] = v;
		self.count = Math.max(i+1,self.count);
	};
	
};
