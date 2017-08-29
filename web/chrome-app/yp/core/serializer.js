sl.serializer = function() {
	this.debug = false;
	var self = this;
	this.precision = false; // False = 32 bit float, true = 64 bit float
	
	var types = [
		"false","true","null","{","}","[","]",",",":",
		"Int8","Int16","Int32","Uint32","Float32","Float64",
		"Uint8Array","Int8Array","Uint16Array","Int16Array",
		"Uint32Array","Int32Array","Float32Array","Float64Array",
		"end","refToParent"
	], map = {};
	
	for (var i = 0; i < types.length; i++) {
		map[types[i]] = i;
	}
	
	function typeOf(v) {
		var s = typeof(v);
		if (s === 'object') {
			if (v) {
				if (v.buffer && v.buffer instanceof ArrayBuffer) {
					var t = ['Int8','Uint8','Int16','Uint16','Int32','Uint32','Float32','Float64'];
					var r;
					for (var i in t) {
						eval('r = v instanceof '+t[i]+'Array;');
						if (r) return t[i]+'Array';
					}
				}
				if (v instanceof Array) return 'array';
				if (v.tagName) return 'element';
				return 'object'
			}
			return 'null';
		}
		return s;
	};
	
	function variableBuffer() {
		this.size = 0;
		this.tmpBuffer = [];
		
		this.append = function(v) {
			if (v.buffer && v.buffer instanceof ArrayBuffer) {
				this.size += v.length * v.BYTES_PER_ELEMENT;
				this.tmpBuffer.push(v);
			} else if (typeof(v) == "object") {
				for (var i = 0; i < v.length; i++) {
					this.append(v[i]);
				}
			} else {
				this.size += typeof(v) == "string" ? v.length : 1;
				this.tmpBuffer.push(v);
			}
		};
		
		this.bytes = function() {
			var buffer = new Uint8Array(this.size);
			var pos = 0;
			for (var i = 0; i < this.tmpBuffer.length; i++) {
				var t = this.tmpBuffer[i];
				if (t.buffer && t.buffer instanceof ArrayBuffer) {
					var b = new Uint8Array(t.buffer);
					buffer.set(b,pos);
					pos += t.length * t.BYTES_PER_ELEMENT;
				} else if (typeof(t) == "string") {
					for (var j = 0; j < t.length; j++) {
						buffer[pos] = t.charCodeAt(j);
						pos ++;
					}
				} else {
					buffer[pos] = t;
					pos ++;
				}
			}
			return buffer;
		};
	};
	
	function encodeNum(t,v) {
		var buffer = new ArrayBuffer(8);
		var b = new Uint8Array(buffer);
		switch (t) {
			case 0: //Int16
				var view = new Int16Array(buffer);
				view[0] = v;
				return [b[0],b[1]];
				
			case 1: //Int32
				var view = new Int32Array(buffer);
				view[0] = v;
				return [b[0],b[1],b[2],b[3]];
				
			case 2: //Uint32
				var view = new Uint32Array(buffer);
				view[0] = v;
				return [b[0],b[1],b[2],b[3]];
			
			case 3: //Float32
				var view = new Float32Array(buffer);
				view[0] = v;
				return [b[0],b[1],b[2],b[3]];
				
			case 4: //Float64
				var view = new Float64Array(buffer);
				view[0] = v;
				return [b[0],b[1],b[2],b[3],b[4],b[5],b[6],b[7]];
		}
	};
	
	this.toHex = function(buffer) {
		if (!(buffer instanceof Uint8Array)) buffer = new Uint8Array(buffer);
		var rv = "";
		for (var i = 0; i < buffer.length; i++) {
			rv += ((buffer[i] >> 4) & 15).toString(16) + (buffer[i] & 15).toString(16);
		}
		return rv;
	};
	
	this.encode = function(o, buffer, branch) {
		var top = false;
		if (!branch) branch = [];
		if (!buffer) {
			top = true;
			buffer = new variableBuffer();
		}
		
		var level, type = typeOf(o);
		
		if (type == "object" && (level = branch.indexOf(o)) != -1) {
			buffer.append([map["refToParent"],level]);
			return;
		}
		branch.push(o);

		if (type == "object" && o.serialize && typeof(o.serialize) == "function") var o = o.serialize();
		
		switch (type) {
			case "boolean":
				buffer.append(o?map["true"]:map["false"]);
				break;
				
			case "number":
				if ((o % 1) === 0) { //Integer
					if (o >= -32 && o <= 31) {
						buffer.append(192|((o+64)&63));
						break;
					} else if (o >= -128 && o <= 127) {
						buffer.append([map["Int8"],(o+256)&255]);
						break;
					} else if (o >= -32768 && o <= 32767) {
						buffer.append(map["Int16"]);
						buffer.append(encodeNum(0,o));
						break;
					} else if (o >= -2147483648 && o <= 2147483647) {
						buffer.append(map["Int32"]);
						buffer.append(encodeNum(1,o));
						break;
					} else  if (o >= 0 && o <= 0xFFFFFFFF) {
						buffer.append(map["Uint32"]);
						buffer.append(encodeNum(2,o));
						break;
					} else {
						buffer.append(map["Float64"]);
						buffer.append(encodeNum(4,o));
						break;
					}
				} 
				//Float
				buffer.append(map[self.precision?"Float64":"Float32"]);
				buffer.append(encodeNum(self.precision?4:3,o));
				break;
			
			case "Int8Array": case "Uint8Array":
			case "Int16Array": case "Uint16Array":
			case "Int32Array": case "Uint32Array":
			case "Float32Array": case "Float64Array":
				buffer.append(map[type]);
				buffer.append(encodeNum(2,o.length));
				buffer.append(o);
				break;
				
			case "string":
				buffer.append(127);
				buffer.append(escape(o));
				buffer.append(127);
				break;
			
			case "array":
				buffer.append(map["["]);
				for (var i = 0; i < o.length; i ++) {
					self.encode(o[i],buffer,branch);
				}
				buffer.append(map["]"]);
				break;
			
			case "element":
				if (o.nodeName != "CANVAS" && o.nodeName != "IMG") return buffer.append(map["null"]);
				
			case "object":		
				var enc;
				if (enc = sl.extendedObjects.encode(o)) {
					buffer.append(126);
					buffer.append(escape(enc));
					buffer.append(126);
					break;
				}
				
				buffer.append(map["{"]);
				var num = 0;
				for (var i in o) {
					self.encode(i,buffer);
					self.encode(o[i],buffer,branch);
					num++;
				}
				buffer.append(map["}"]);
				break;
						
			case "null": default:
				buffer.append(map["null"]);
				break;
		}
		if (top) return buffer.bytes();
	};
	
	var decodePos = 0;
	this.decode = function(buffer,notTop) {
		if (!(buffer instanceof Uint8Array)) buffer = new Uint8Array(buffer);
		if (!notTop) decodePos = 0;
		if (buffer[decodePos] >= 192) { //small number
			var n = buffer[decodePos]&63;
			decodePos++;
			return n >= 32 ? n - 64 : n;  
		} else {
			var decType = buffer[decodePos];
			switch (decType) {
				case map["refToParent"]:
					var n = buffer[decodePos+1];
					decodePos+=2;
					return true;
					
				case map["true"]:
					decodePos++;
					return true;
				
				case map["false"]:
					decodePos++;
					return false;
				
				case map["Int8"]:
					var n = buffer[decodePos+1];
					decodePos+=2;
					return n >= 128 ? n - 256 : n; 
					
				case map["Int16"]:
					var bt = new ArrayBuffer(2);
					var b = new Uint8Array(bt);
					var out = new Int16Array(bt);
					decodePos++;
					b.set(buffer.subarray(decodePos,decodePos+=2));
					return out[0];
					
				case map["Int32"]:
					var bt = new ArrayBuffer(4);
					var b = new Uint8Array(bt);
					var out = new Int32Array(bt);
					decodePos++;
					b.set(buffer.subarray(decodePos,decodePos+=4));
					return out[0];
					
				case map["Uint32"]:
					var bt = new ArrayBuffer(4);
					var b = new Uint8Array(bt);
					var out = new Uint32Array(bt);
					decodePos++;
					b.set(buffer.subarray(decodePos,decodePos+=4));
					return out[0];	
					
				case map["Float32"]:
					var bt = new ArrayBuffer(4);
					var b = new Uint8Array(bt);
					var out = new Float32Array(bt);
					decodePos++;
					b.set(buffer.subarray(decodePos,decodePos+=4));
					return out[0];	
					
				case map["Float64"]:
					var bt = new ArrayBuffer(8);
					var b = new Uint8Array(bt);
					var out = new Float64Array(bt);
					decodePos++;
					b.set(buffer.subarray(decodePos,decodePos+=8));
					return out[0];	
			
				case map["Int8Array"]: case map["Uint8Array"]:
				case map["Int16Array"]: case map["Uint16Array"]:
				case map["Int32Array"]: case map["Uint32Array"]:
				case map["Float32Array"]: case map["Float64Array"]:
					var bt = new ArrayBuffer(4);
					var b = new Uint8Array(bt);
					var size = new Uint32Array(bt);
					decodePos++;
					b.set(buffer.subarray(decodePos,decodePos+=4));
					var convBuff, ab;
					
					var bpe = Number(types[decType].replace(/[^\d]+/g,""))>>3;
					
					/* The below should work, but there is a bug in chrome https://bugs.webkit.org/show_bug.cgi?id=57042
					eval("convBuff = new Uint8Array(buffer.buffer,decodePos,"+(size[0] * bpe)+")");
					*/
				
					//Workaround:
					eval("convBuff = new Uint8Array("+(size[0] * bpe)+")");
					for (var i = 0, len = size[0] * bpe, b = decodePos; i < len; i++) {
						convBuff[i] = buffer[b];
						b++;
					}
					//End workaround
					
					eval("ab = new "+types[decType]+"(convBuff.buffer)");
					decodePos += size[0] * bpe;
				
					return ab;
				
				case 126:
					var v = "";
					decodePos++;
					do {
						var c = buffer[decodePos++];
						if (c == 126) break;
						v += String.fromCharCode(c);
					} while (decodePos < buffer.length);
					return sl.extendedObjects.decode(unescape(v));
					
				case 127:
					var v = "";
					decodePos++;
					do {
						var c = buffer[decodePos++];
						if (c == 127) break;
						v += String.fromCharCode(c);
					} while (decodePos < buffer.length);
					v = unescape(v);
					var dec;
					if (v.substr(0,5) == "data:" && (dec = sl.extendedObjects.decode(v))) {
						return dec;
					}
					return v;
					
				case map["["]:
					var o = [];
					decodePos++;
					while (buffer[decodePos] != map["]"] && decodePos < buffer.length) {
						o.push(self.decode(buffer,1));
					}
					decodePos++;
					return o;
										
				case map["{"]:
					var o = {};
					decodePos++;
					while (buffer[decodePos] != map["}"] && decodePos < buffer.length) {
						var n = self.decode(buffer,1);
						o[n] = self.decode(buffer,1);
						if (self.debug) console.log(n,o[n]);
					}
					decodePos++;
					return o;
					
				case map["null"]: default:
					decodePos++;
					return null;
				
			}
		}
	};
	
};
