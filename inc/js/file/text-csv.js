sl.fileManagers["text-csv"] = function() {
	var self = this;
	
	self.setValues({
		"separator":",",
		"firstRowLabels":true,
		"labels":[],
		"userLabels":[],
	});
	
	function getPart(pos,len,cb) {
		var reader = new FileReader();
		var blob = self.file.slice(pos, pos + len);
		reader.onloadend = function(e) {
			if (e.target.readyState == FileReader.DONE) {
				curPartPos = pos;
				curPart = e.target.result;
				cb(e.target.result);
			}
		};
		reader.readAsBinaryString(blob);
	};
	
	function getPartText(pos,len,cb) {
		var reader = new FileReader();
		var blob = self.file.slice(pos, pos + len);
		reader.onloadend = function(e) {
			if (e.target.readyState == FileReader.DONE) {
				curPartPos = pos;
				curPart = e.target.result;
				cb(e.target.result);
			}
		};
		reader.readAsText(blob);
	};
	
	function quoteEnd(text,pos) {
		if (text.charAt(pos) == "\"") pos++;
		var next;
		do {
			next = text.indexOf("\"",pos);
			if (next == -1) return pos;
			if (text.charAt(next + 1) == "\"") {
				pos = next + 2;
				continue;
			}
			return next + 1;
		} while (1);
	};
	
	function getNextTok(origText,offset,limit) {
		var check = ["\"","\n"];
		
		var text, limited = false;
		if (limit && origText.length > offset + limit) {
			limited = true;
			text = origText.substr(0,offset + limit);
		} else {
			text = origText;
		}
		
		var bestPos = text.length, pos = 0;
		
		if ((pos = text.indexOf("\"",offset)) != -1) bestPos = pos;

		if ((pos = text.indexOf("\n",offset)) != -1 && pos < bestPos) {
			if (text.charAt(bestPos - 1) == "\r") return bestPos - 1;
			return pos;
		}
		
		if (limited && bestPos == text.length) {
			return getNextTok(origText,offset,0);
		}
		return bestPos;
	};
	
	self.init = function() {
		self.rowPos = new sl.bigArray("Uint32Array");
		
		var maxLineSize = 0;
		var pos = 0, num = 0, num2 = 0;
		var lastTs = sl.unixTS(true);
		
		function recordLineSize(size) {
			maxLineSize = Math.max(size,maxLineSize);
		}
		
		function getMaxLineSize() {
			return maxLineSize ? maxLineSize : 3200;
		}
		
		function process() {
			var maxLineSize = getMaxLineSize();
			getPart(pos,maxLineSize*20,function(part){
				var offset = 0, start = 0, realPos = 0, next = 0, rowsSinceLast = 0, tok, tokChar;
				while ((realPos = pos + offset) < self.file.size) {
					
					if (realPos > self.file.size - 1024 && part.substr(realPos).trim() == "") break;
					
					self.rowPos.push(realPos);
					
					next = part.indexOf("\n",offset);
					
					if (next == -1) {
						console.log("NOPE!");
						//TODO: Try again
						break;
					}
					
					offset = next + 1;
					rowsSinceLast ++;
					
					recordLineSize(offset - start);								
					start = offset;				
					
					var ts = sl.unixTS(true);
					if (ts > lastTs + 0.2) {
						
						//console.log((rowsSinceLast / (ts - lastTs))+" rows / second","\tmaxLineSize",maxLineSize,offset);
						lastTs = ts;
						rowsSinceLast = 0;
						self.dispatchEvent("progress",[pos,self.file.size,self.rowPos.count]);
					}
					
					num++;
												
					if (offset > maxLineSize*10) {
						num2++;
						pos += offset;
						if ((num2 % 500) == 0) {
							setTimeout(process,5);
						} else {
							process();
						}
						return;
					}
				}
								
				if (self.firstRowLabels) {
					self.firstRow = 1;
					linesToArray(0,1,function(v){
						self.labels = v[0];
						self.dispatchEvent("load");
					});
				} else {
					self.firstRow = 0;
					self.dispatchEvent("load");
				}
		
			});
		};
		process();
	};
	
	function getRowPos(i) {
		return i < self.rowPos.count ? self.rowPos.data[i] : self.file.size;
	};
		
	function lineToArray(line) {
		var pos = 0, nextQuote = 0, nextSep = 0, rv = [];
		line = line.replace(/[\s\r\n]+$/,"");
		
		while (pos < line.length) {
			nextQuote = line.indexOf("\"",pos);
			nextSep = line.indexOf(self.separator,pos);
			if (nextQuote != -1 && (nextQuote < nextSep || nextSep == -1)) {
				pos = nextQuote;
				var qe = quoteEnd(line,pos);
				if (qe != -1) {
					rv.push(line.substr(pos+1,qe-pos-2).split("\"\"").join("\""));
					pos = qe + 1;
				} else {
					pos ++;
				}
			} else if (nextSep != -1) {
				rv.push(line.substr(pos,nextSep-pos));
				pos = nextSep + 1;
			} else {
				if (pos < line.length) rv.push(line.substr(pos));
				break;
			}
		}
		return rv;
	}
	
	function linesToArray(i,len,cb) {
		var sp = 0;
		getPartText(sp = getRowPos(i),getRowPos(i+len)-sp,function(text) {
			text = text.split("\n");
			var rv = [];
			for (var i = 0; i < text.length; i++) {
				if (text[i].trim() != "") rv.push(lineToArray(text[i]));
			}
			cb(rv);
		});
	};
			
			
	//self.reader.onload = function(e) {
		//self.setData(e.target.result);
	//};
		
		
	function associateRow(row) {
		var rv = {};
		for (var i = 0; i < row.length; i++) {
			if (row[i].trim() == "") continue;
			if (self.userLabels.length >= i && typeof(self.userLabels[i]) == "string") {
				rv[self.userLabels[i]] = row[i].trim();
			} else if (self.labels.length >= i && typeof(self.labels[i]) == "string") {
				rv[self.labels[i]] = row[i].trim();
			} else {
				rv["Column "+(i+1)] = row[i].trim();
			}
		}
		return rv;
	};
	
	self.getRow = function(row,cb) {
		linesToArray(row + self.firstRow,1,function(rows){
			cb(associateRow(rows[0]));
		});
	};
	
	self.getRows = function(row,len,cb) {
		linesToArray(row + self.firstRow,len,function(rows){
			for (var i = 0; i < rows.length; i++) {
				rows[i] = associateRow(rows[i]);
			}
			cb(rows);
		});
	};
	
	self.count = function() {
		return self.rowPos.count - self.firstRow;
	};
	
	self.init();
	
};

