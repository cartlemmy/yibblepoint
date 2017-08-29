sl.bitArray = function(length) {
	var self = this;
	
	var bitCount = [];
	for (var i = 0; i < 256; i++) {
		bitCount.push(0);
		for (var j = 0; j < 8; j++) {
			if (i & Math.pow(2,j)) bitCount[i] ++;
		}
	}
	
	self.length = 0;
	self.setCnt = 0;
	
	self.data = null;
	
	self.isBitSet = function(i) {
		if (i >= self.length) return null;
		return !!(Math.pow(2,i & 7) & self.data[i >> 3]);
	};
	
	self.setBit = function(i,v) {
		if (i >= self.length) return;
		self.setCnt += (self.isBitSet(i) ? -1 : 0) + (v ? 1 : 0);
		var n = self.data[i >> 3] & (Math.pow(2,i & 7) ^ 255);
		if (v) n = n | Math.pow(2,i & 7);
		self.data[i >> 3] = n;
	};
	
	self.setAll = function(v) {
		v = v ? 255 : 0;
		self.setCnt = v ? self.length : 0;
		for (var i = 0; i < self.data.length; i++) {
			self.data[i] = v;
		}
	};
	
	self.setLength = function(length) {
		if (length > 0) {
			var newData = new Uint8Array(Math.ceil(length / 8));
			if (self.data) {
				self.setCnt = 0;
				var v;
				for (var i = 0, len = Math.min(self.data.length,newData.length); i < len; i++) {
					v = self.data[i];
					self.setCnt += bitCount[v];
					newData[i] = v;
				}
			}
			self.data = newData;
		} else self.data = [];
		self.length = length;
	};
	
	self.getAsBase64 = function() {
		return sl.base64ArrayBufferEncode(self.data);
	};
	
	self.getAsArrayOfSet = function() {
		var rv = [];
		for (var i = 0; i < self.data.length; i++) {
			if (self.data[i] > 0) {
				for (var j = 0; j < 8; j++) {
					if (self.data[i] & Math.pow(2,j)) {
						var n = (i << 3) + j;
						if (n < self.length) rv.push(n);
					}
				}
			}
		}
		return rv;
	};
	
	self.getAsMostEffecient = function() {
		return self.setCnt > self.length / 2 ? self.getAsBase64() : self.getAsArrayOfSet();
	};
	
	self.setLength(length);
};
