sl.nibbleIndexer = function(o) {
	var self = this;
	sl.initSlClass(this,"nibble-indexer");
	
	var startTS = 0;
	
	var dec2Hex = [];
	for (var i = 0; i < 256; i++) {
		dec2Hex.push(((i>>4)&15).toString(16)+(i&15).toString(16));
	}
		
	self.setData = function(data) {
		if (data) {
			self.data = new Uint8Array(data);
		} else {
			self.data = data;
		}
	};
	
	self.search = function(text) {
		self.startTimer();
		self.pos = 0;
		
		self.hash = self.toNibbleHash(text);
			
		var ids = [];
		while ((id = self.result()) != -1) {
			ids.push(id);
		}
		self.pos = 0;
		self.endTimer();
		return ids;
	};
	
	self.getLine = function() {
		var startPos = self.pos;
		while (self.pos < self.data.length) {
			self.pos ++;			
			if (self.data[self.pos] == 0) break;
		}
		self.pos++;
		return self.data.subarray(startPos,self.pos - 1);
	};
	
	self.bin2hex = function(bin) {
		var rv = "";
		for (var i = 0; i < bin.length; i++) {
			rv += dec2Hex[bin[i]];
		}
		return rv;
	};
	
	self.result = function() {
		while (self.pos < self.data.length) {
			var h = self.bin2hex(self.getLine());
			var id = self.decodeInt(self.getLine());
		
			if (h.indexOf(self.hash) != -1) return id;
		}
		
		return -1;
	};
	
	self.decodeInt = function(enc) {
		var rv = 0, i = enc.length - 1;
		while (i >= 0) {
			rv *= 255;
			rv += enc[i--] - 1;
		}
		return rv;
	};
	
	self.startTimer = function() {
		startTS = sl.unixTS(true);
	};
	
	self.endTimer = function() {
		self.lastQueryDuration = sl.unixTS(true) - startTS;
	};
	
	self.toNibbleHash = function(text) {
		text = text.searchify('',true);

		var from = "abcdefghijklmnopqrstuvwxyz ",
			to = "2456e8fbc45d9112a7e33fd123f";
		var rv = "";
		
		for (var i = 0; i < text.length; i++) {
			rv += to.charAt(from.indexOf(text.charAt(i)));
		}
		
		return rv;
	};
	
	/*
	$hex = strtr(
		strtolower($text),
	);
	if (!$noPad && strlen($hex)&1==1) $hex .= "f";

	return $asHex ? $hex : str_replace("\0",chr(1),pack("H*" ,$hex));
	*/
	
	self.setValues({
		"data":null,
		"pos":0,
		"lastQueryDuration":0
	});
	
	self.setValues(o);
};
