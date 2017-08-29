function b64v(urlSafe) {
	return "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"+(urlSafe?"-._":"+/=");
};

sl.base64ArrayBufferDecode = function(data, urlSafe) {	
	var b64 = b64v(urlSafe);
	var e = b64.charAt(64);
	
	var h1, h2, h3, h4, bits, ac = 0;

	if (!data) {return data;}
	data += '';
	
	var pad = 0, i = data.length - 1;
	while (data.charAt(i) == e) { i--; pad++; }
	
	var len = (data.length * 6 / 8) - pad;

	var bytes = new Uint8Array(len);
	
	i = 0;
	
	while (1) {
		h1 = b64.indexOf(data.charAt(i++));
		h2 = b64.indexOf(data.charAt(i++));
		h3 = b64.indexOf(data.charAt(i++));
		h4 = b64.indexOf(data.charAt(i++));
		bits = h1 << 18 | h2 << 12 | h3 << 6 | h4;
		
		if (ac < len) { bytes[ac++] = bits >> 16 & 0xff; } else break;
		if (ac < len) { bytes[ac++] = bits >> 8 & 0xff; } else break;
		if (ac < len) { bytes[ac++] = bits & 0xff; } else break;
	}
	return bytes.buffer;
};


sl.base64ArrayBufferEncode = function(arrayBuffer, urlSafe) {
	var b64 = b64v(urlSafe);
	
	var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
			ac = 0,
			enc = "",
			tmp_arr = []; 
	if (!arrayBuffer) {
			return arrayBuffer;
	}

	do { // pack three octets into four hexets
		o1 = arrayBuffer[i++];
		o2 = arrayBuffer[i++];
		o3 = arrayBuffer[i++];

		bits = o1 << 16 | o2 << 8 | o3;

		h1 = bits >> 18 & 0x3f;        h2 = bits >> 12 & 0x3f;
		h3 = bits >> 6 & 0x3f;
		h4 = bits & 0x3f;

		// use hexets to index into b64, and append result to encoded string
		tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
	} while (i < arrayBuffer.length);

	enc = tmp_arr.join('');
	var r = arrayBuffer.length % 3;
    
	return (r ? enc.slice(0, r - 3) : enc) + (urlSafe ? '___' : '===').slice(r || 3);
};


sl.base64Decode = function(data, urlSafe) {
	var b64 = b64v(urlSafe);
	var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
			ac = 0,        dec = "",
			tmp_arr = [];

	if (!data) {return data;}

	data += '';

	do { // unpack four hexets into three octets using index points in b64
	h1 = b64.indexOf(data.charAt(i++));
			h2 = b64.indexOf(data.charAt(i++));
			h3 = b64.indexOf(data.charAt(i++));
			h4 = b64.indexOf(data.charAt(i++));
			bits = h1 << 18 | h2 << 12 | h3 << 6 | h4;

			o1 = bits >> 16 & 0xff;
			o2 = bits >> 8 & 0xff;
			o3 = bits & 0xff; 
			if (h3 == 64) {
					tmp_arr[ac++] = String.fromCharCode(o1);
			} else if (h4 == 64) {
					tmp_arr[ac++] = String.fromCharCode(o1, o2);
			} else {
					tmp_arr[ac++] = String.fromCharCode(o1, o2, o3);
			}
	} while (i < data.length);
	dec = tmp_arr.join('');
	return dec;
};

sl.base64Encode = function(data,urlSafe) {
	var b64 = b64v(urlSafe);
	
	var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
			ac = 0,
			enc = "",
			tmp_arr = []; 
	if (!data) {
			return data;
	}
	//data = this.utf8_encode(data + '');

	do { // pack three octets into four hexets
		o1 = data.charCodeAt(i++);
		o2 = data.charCodeAt(i++);
		o3 = data.charCodeAt(i++);

		bits = o1 << 16 | o2 << 8 | o3;

		h1 = bits >> 18 & 0x3f;        h2 = bits >> 12 & 0x3f;
		h3 = bits >> 6 & 0x3f;
		h4 = bits & 0x3f;

		// use hexets to index into b64, and append result to encoded string
		tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
	} while (i < data.length);

	enc = tmp_arr.join('');
	var r = data.length % 3;
    
	return (r ? enc.slice(0, r - 3) : enc) + (urlSafe ? '___' : '===').slice(r || 3);
};
