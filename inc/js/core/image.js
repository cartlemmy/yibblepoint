sl.initQRCode = function(cb) {
	sl.require("../inc/thirdparty/jsqrcode-master/src/*.js",cb);
};	

sl.image = function(o) {
	var self = this;
	sl.initSlClass(this,"image");
	
	self.fromImage = function(img,cb,scaleDown) {
		if (typeof(img) == "string") {
			//Load image from url
			try {
				var image = new Image();
				image.onload = function() {
					self.fromImage(image,cb,scaleDown);
				};

				image.src = img;
			} catch (e) {}
			return;
		}
		
		if (img.width || img.videoWidth) {
			var width = img.width || img.videoWidth, height = img.height || img.videoHeight;

			self.canvas = document.createElement('canvas');
			if (scaleDown) {
				scaleDown = Math.round(scaleDown);
				self.setSize({"width":Math.floor(width/scaleDown),"height":Math.floor(height/scaleDown)});
				
				var srcImg = new sl.image();
				srcImg.fromImage(img);
				
				for (var oy = 0; oy < height; oy += scaleDown) {
					var dy = (oy / scaleDown) | 0;
					for (var ox = 0; ox < width; ox += scaleDown) {
						var dx = (ox / scaleDown) | 0, rgbaTot = [0,0,0,0], cnt = 0;
						for (var y = oy, yStop = oy + scaleDown; y < yStop; y++) {
							for (var x = ox, xStop = ox + scaleDown; x < xStop; x++) {
								var rgba = srcImg.getPixel(x,y);
								rgbaTot[0] += rgba[0];
								rgbaTot[1] += rgba[1];
								rgbaTot[2] += rgba[2];
								rgbaTot[3] += rgba[3];
								cnt++;
							}
						}
						self.setPixel(dx,dy,rgbaTot[0]/cnt,rgbaTot[1]/cnt,rgbaTot[2]/cnt,rgbaTot[3]/cnt);
					}
				}
			} else {
				self.setSize({"width":width,"height":height});
				self.canvas.getContext('2d').drawImage(img,0,0);
			}
			if (cb) cb();
		}		
	};
	
	self.getPixel = function(x,y) {
		return self.ctx.getImageData(x, y, 1, 1).data;		
	};
	
	self.setPixel = function(x,y,r,g,b,a) {
		var imgd = self.ctx.getImageData(x, y, 1, 1);
		imgd.data[0] = r;
		imgd.data[1] = g;
		imgd.data[2] = b;
		imgd.data[3] = a;
		self.ctx.putImageData(imgd, x, y);
	};
	
	self.setSize = function(size,noNewCanvas) {
		if (size) {
			self.size = {};
			self.size.width = size.width;
			self.size.height = size.height;
			self.size.pixels = size.width * size.height;
			
			if (!self.canvas) self.canvas = document.createElement('canvas');
			
			if (!noNewCanvas) {
				self.canvas.setAttribute('width',size.width);
				self.canvas.setAttribute('height',size.height);
			
				self.ctx = self.canvas.getContext('2d');			
			}
		}
	};
	
	self.toCanvas = function(canvas) {
		canvas.getContext('2d').drawImage(self.canvas,0,0);
	};
	
	self.getPixels = function() {
		self.imgd = self.ctx.getImageData(0, 0, self.size.width, self.size.height);
		return self.imgd.data;
	};
	
	self.savePixelChanges = function() {
		self.ctx.putImageData(self.imgd, 0, 0);
	};
	
	self.scale = function(xScale,yScale) {
		self.resize(Math.round(self.size.width * xScale),height = Math.round(self.size.height * yScale));
	};
	
	self.resize = function(width,height) {
		var scaled = new sl.image();
		scaled.setSize({"width":width,"height":height});
		scaled.ctx.drawImage(
			self.canvas,
			0,0,self.size.width,self.size.height,
			0,0,width,height
		);
		self.fromImage(scaled.canvas);
		self.setSize({"width":width,"height":height},true);
	};
	
	//filters
	self.threshold = function(v) {
		v *= 768;
		var pix = self.getPixels();
		
		for (var i = 0, len = self.size.pixels * 4; i < len; i++) {
			if (pix[i++] + pix[i++] + pix[i++] >= v) {
				i -= 3;
				pix[i++] = pix[i++] = pix[i++] = 255;
			} else {
				i -= 3;
				pix[i++] = pix[i++] = pix[i++] = 0;
			}
		}
		
		self.savePixelChanges();
	};
	
	self.normalize = function(paddingLow,paddingHigh) {
		if (paddingLow === undefined) paddingLow = 0.1;
		if (paddingHigh === undefined) paddingHigh = paddingLow;

		var pix = self.getPixels();
		
		// RGB Histogram
		var tot = [[],[],[]], minRGB = [0,0,0], maxRGB = [0,0,0], multRGB = [0,0,0];
		for (var i = 0; i < 256; i++) {
			tot[0].push(0); // Red
			tot[1].push(0); // Green
			tot[2].push(0); // Blue
		}
		
		for (var i = 0, len = self.size.pixels * 4; i < len; i++) {
			tot[0][pix[i++]]++;
			tot[1][pix[i++]]++;
			tot[2][pix[i++]]++;
		}
		
		for (var i = 0; i < 256; i++) {
			maxRGB[0] = Math.max(maxRGB[0],tot[0][i]);
			maxRGB[1] = Math.max(maxRGB[1],tot[1][i]);
			maxRGB[2] = Math.max(maxRGB[2],tot[2][i]);
		}
		
		for (var i = 0; i < 256; i++) {
			tot[0][i] /= maxRGB[0];
			tot[1][i] /= maxRGB[1];
			tot[2][i] /= maxRGB[2];
		}
		
		for (i = 0; i < 3; i++) {
			//Find min
			var j = 0;
			while (tot[i][j] <= paddingLow && j < 127) { j++; }
			minRGB[i] = j;
			
			//Find max
			var j = 255;
			while (tot[i][j] <= paddingHigh && j > 128) { j--; }
			multRGB[i] = 255 / (j - minRGB[i]);
		}
		
		for (var i = 0, len = self.size.pixels * 4; i < len; i++) {
			pix[i] = ((pix[i++] - minRGB[0]) * multRGB[0])|0;
			pix[i] = ((pix[i++] - minRGB[1]) * multRGB[1])|0;
			pix[i] = ((pix[i++] - minRGB[2]) * multRGB[2])|0;
		}
				
		self.savePixelChanges();
	};
	
	self.quickBlur = function(r) {
		var canvas = document.createElement('canvas');
		canvas.setAttribute('width',self.size.width);
		canvas.setAttribute('height',self.size.height);
		var ctx = canvas.getContext('2d');

		var oldAplha = self.ctx.globalAlpha;
		self.ctx.globalAlpha = 0.5;
		

		var inc = 1;
		for (var x = 1; x <= (r>>1); x += inc) {
			ctx.drawImage(self.canvas,0,0);
			self.ctx.drawImage(canvas,x,0);		
			self.ctx.drawImage(canvas,-x,0);
			inc++;
		}
		var inc = 1;
		for (var y = 1; y <= (r>>1); y += inc) {
			ctx.drawImage(self.canvas,0,0);
			self.ctx.drawImage(canvas,0,y);
			self.ctx.drawImage(canvas,0,-y);
			inc++;
		}
		self.ctx.globalAlpha = oldAplha;
		
	};
	
	self.blurNormalize = function(blurV) {
		if (blurV === undefined) blurV = 16;
		
		var blurImage = new sl.image();
		blurImage.setSize({"width":self.size.width,"height":self.size.height});
		blurImage.fromImage(self.canvas);
		blurImage.quickBlur(blurV);
		
		var blurPix = blurImage.getPixels();
		var pix = self.getPixels();
		
		for (var i = 0, len = self.size.pixels * 4; i < len; i++) {
			pix[i] = pix[i] - blurPix[i++] + 127;
			pix[i] = pix[i] - blurPix[i++] + 127;
			pix[i] = pix[i] - blurPix[i++] + 127;
		}
		
		self.savePixelChanges();
	};
	
	self.getBarCode = function() {
		if (Math.abs(1 - 640 / self.size.width) > 0.5) self.scale(640 / self.size.width, 640 / self.size.width);
		
		var maxLen = Math.sqrt(self.size.width * self.size.width + self.size.height * self.size.height) / 50;
		
		//self.quickBlur(1);
	  self.blurNormalize(64);
		//self.threshold(0.5);
		
		function scan(x,y,xv,yv) {
			if (x < 0) { y += 0 - x; x = 0; }
			if (y < 0) { x += 0 - y; y = 0; }
			if (x >= self.size.width) { y += x - (self.size.width - 1); x = self.size.width - 1; }
			if (y >= self.size.height) { x += y - (self.size.height - 1); y = self.size.height - 1; }
			
			var curC = -1, phaseLen = 0, phaseC = [], phaseMap = [];
			while (x < self.size.width && y < self.size.height && x >= 0 && y >= 0) {
				var c = (self.getPixel(x,y))[1] > 127;
				
				if (curC != c) {
					phaseC.push(curC);
					phaseMap.push(phaseLen);
					phaseLen = 0;
					curC = c;
				}
				x += xv; y += yv; phaseLen ++;
			}
			phaseC.push(curC);
			phaseMap.push(phaseLen);
			
			phaseC.push(!curC);
			phaseMap.push(100);
			
			for (var i = 0; i < phaseMap.length; i++) {
				if (phaseC[i] === false && phaseMap[i] > 1) {
					var pass = true, tot = phaseMap[i] + phaseMap[i + 1], cnt = 2;
					var whiteOff = (phaseMap[i] - phaseMap[i + 1]) / 2;

					if (Math.abs(phaseMap[i] - phaseMap[i + 2]) / (phaseMap[i] + phaseMap[i + 2]) < 0.5 /*Math.abs(phaseMap[i] - phaseMap[i + 2]) <= 2*/ && Math.abs(whiteOff) <= 5) {						
						var str = "101";
						for (var j = i + 3; j < phaseMap.length; j += 2) {
							var avgLen = tot / cnt;
							var dLen = (phaseMap[j] + phaseMap[j + 1]);
							var len = Math.round(dLen / avgLen);
							if (len) {
								if (len < 2) {
									dLen = 2 * avgLen;
									len = 2;
								}
								if (len == 6) len = 5;
	
								if (len > 5 || j >= phaseMap.length - 2) {
									if (str.length >= 95) return str;
								}
																
								var whiteLen = Math.max(1,Math.min(len-1,Math.round((phaseMap[j] + whiteOff) / avgLen)));

								for (k = 0; k < whiteLen; k++) {
									str += "0";
								}
								
								for (k = 0; k < len - whiteLen; k++) {
									str += "1";
								}
								
								tot += dLen;
								cnt += len;
								avgLen = tot / cnt;
							} else break;
						}
					}
				}
			}
			return false;
		};
		
		function decode(enc,x,y,xv,yv) {
			var type = "UPC-A";
			if (x < 0) { y += 0 - x; x = 0; }
			if (y < 0) { x += 0 - y; y = 0; }
			if (x > self.size.width) { y += x - (self.size.width - 1); x = self.size.width - 1; }
			if (y > self.size.height) { x += y - (self.size.height - 1); y = self.size.height - 1; }
			
			while (x < self.size.width && y < self.size.height && x >= 0 && y >= 0) {
				var old = self.getPixel(x,y);
				self.setPixel(x,y,old[0]^255,old[1],old[2],255); 
				x += xv, y += yv;
			}
						
			if (enc.substr(0,3) != "101") return false; // no starting guard
			if (enc.substr(enc.length - 3,3) != "101") return false; // no ending guard
			enc = enc.substr(3,enc.length - 6);
			
			var mid = Math.floor(enc.length / 2) - 2;
						
			if (enc.substr(mid,5) != "01010") return false; // no middle guard
			enc = enc.substr(0,mid)+enc.substr(mid+5);
			
			var d = [["0001101","0011001","0010011","0111101","0100011","0110001","0101111","0111011","0110111","0001011"],[],[]];
			var ean = ["LLLLLL","LLGLGG","LLGGLG","LLGGGL","LGLLGG","LGGLLG","LGGGLL","LGLGLG","LGLGGL","LGGLGL"];
			
			for (var i = 0; i < 10; i++) {
				var s1 = "", s2 = "";
				for (var j = 0; j < 7; j++) {
					s1 += d[0][i].charAt(j) == "0" ? "1" : "0";
				}
				for (var j = 0; j < 7; j++) {
					s2 += s1.charAt(6 - j);
				}
				d[1].push(s1);
				d[2].push(s2);
			}
			
			if (d[0].indexOf(enc.substr(0,7)) == -1) { //upside down, flip it
				var encUD = enc;
				var enc = "";
				for (var i = encUD.length - 1; i >= 0; i--) {
					enc += encUD.charAt(i);
				}
			}
			
			var pos = 0, rv = "", v, eanS = "";
			var dbg = [];
			while (pos < enc.length) {
				switch (type) {
					case "EAN-13":
						var v = -1;
						if (pos >= mid) {
							if ((v = d[1].indexOf(enc.substr(pos,7))) == -1) return 0;
						} else {
							for (var i = 0; i < 3; i++) {
								if ((v = d[i].indexOf(enc.substr(pos,7))) != -1) break;
							}
							if (v == -1) return 0;
							if (i != 1) eanS += i == 0 ? "L" : "G";
						}
						rv += v;
						break;
						
					default:
						if (v = d[2].indexOf(enc.substr(pos,7)) != -1) {
							rv = "";
							type = "EAN-13";
							pos = 0;
							continue;
						}
						if ((v = d[pos>=mid?1:0].indexOf(enc.substr(pos,7))) == -1) return 0;
						rv += v;
						break;
				}
				
				pos += 7;
			}
			
			switch (type) {
				case "EAN-13":
					if (ean.indexOf(eanS) == -1) return 0;
					rv = ean.indexOf(eanS) + rv;
					break;
			}
			
			
			var check = 0;
			for (var i = 0; i < rv.length - 1; i += 2) {
				check += (i < rv.length - 2 ? Number(rv.charAt(i+1)) * 3 : 0) + Number(rv.charAt(i));
			}
			check = check % 10;
			if (check != 0) check = 10 - check;
			if (String(check) != rv.charAt(rv.length-1)) return 0; // Checksum failed
			
			self.barCodeType = type;
			
			return rv;
		};
		
		var enc, tryMore = 0;
		for (var y = 0; y < self.size.height; y += tryMore ? 1 : 32) {
			if ((enc = scan(0,y,1,0)) && (enc = decode(enc,0,y,1,0))) return enc;
			if (enc === 0) tryMore = 4;
			if (tryMore > 0) tryMore --;
		}
		
		tryMore = 0;
		for (var x = 0; x < self.size.width; x += tryMore ? 1 : 32) {
			if ((enc = scan(x,0,0,1)) && (enc = decode(enc,x,0,0,1))) return enc;
			if (enc === 0) tryMore = 4;
			if (tryMore > 0) tryMore --;
		}
	
		tryMore = 0;
		for (var x = - self.size.height; x < self.size.width; x += tryMore ? 1 : 32) {
			if ((enc = scan(x,0,1,1)) && (enc = decode(enc,x,0,1,1))) return enc;
			if (enc === 0) tryMore = 4;
			if (tryMore > 0) tryMore --;
		}
		
		tryMore = 0;
		for (var x = 0; x < self.size.width + self.size.height; x += tryMore ? 1 : 32) {
			if ((enc = scan(x,0,-1,1)) && (enc = decode(enc,x,0,-1,1))) return enc; 
			if (enc === 0) tryMore = 4;
			if (tryMore > 0) tryMore --;
		}
		return self.setError("No barcode found");
	};	
	
	self.getQRCode = function() {
		qrcode.width = self.size.width;
		qrcode.height = self.size.height;
		
		qrcode.imagedata = self.ctx.getImageData(0, 0, qrcode.width, qrcode.height);
				
		try {
			return qrcode.process(self.ctx);
		} catch (e) {
			return self.setError(e);
		}
	};
	
	self.setError = function(e) {
		self.error = e;
		return false;
	};
	
	self.setValues({
		"size":{
			"width":0,
			"height":0
		}
	});
	
	self.setValues(o);
};
