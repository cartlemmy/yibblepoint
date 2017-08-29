sl.require(["js/core/image.js"],function(){
	sl.initQRCode(function() {
		self.createView({"contentPadding":"8px"});

		self.view.setContentFromHTMLFile();
		self.view.center();
			
		var lastV = "";
		function setResult(type,v) {
			if (v != lastV) {
				lastV = v;
				self.request("info",[type,v],function(r){
					if (r.error) self.setScanError(r.error);
					var el = self.view.element("result");
					el.innerHTML = v;
					el.href = r.url;					
					self.dispatchEvent("scan",r);
				});
			}
		};
		
		self.setScanError = function(error) {
			self.view.element("error").innerHTML = error;
		};
		
		var video = self.view.element("video");
		
		var wait = 500;
		var img = new sl.image();
		
		function scanCode(src,cb) {
			if (!cb) self.view.element("result").innerHTML = "SCANNING...";
			
			function scanBarCode(cb) {
				var res = img.getBarCode();
				
				if (res) setResult(img.barCodeType,res);
				if (cb) cb();
			};

			function scanQRCode(cb) {
				var res = img.getQRCode();
				//self.view.element("error").innerHTML = res === false ? img.error : "";
				if (res) setResult("QR",res);
				if (cb) cb();
			};
			
			img.fromImage(src,function(){
				var type = self.view.element("type").selectedIndex + 1, done = 0;
				var ts = (new Date()).getTime();
				
				function scanDone() {
					done --;
					if (done == 0) {
						wait = Math.min(1500,Math.max(500,((new Date()).getTime() - ts)));
						if (cb) cb();
					}
				};
				
				for (var i = 1; i < 3; i++) { if (type & i) done++; }
				for (var i = 1; i < 3; i++) {
					if (type & i) {
						switch (i) {
							case 1: scanBarCode(scanDone); break;
							case 2: scanQRCode(scanDone); break;
						}
					}
				}			
			});
		};
	
		var vidTimer = null;
		function scanCam() {
			scanCode(video,function(){
				vidTimer = setTimeout(scanCam, wait);
			});
		};
		
		var um, mediaStream = null;
		if (um = sl.getNonStadardsItemName(navigator,"getUserMedia")) {
			navigator[um]({video: true}, function(localMediaStream) {
				mediaStream = localMediaStream;
				video.src = window.URL.createObjectURL(localMediaStream);

				var t = setInterval(function() {
					if (video.videoWidth) {
						scanCam();
						clearInterval(t);
					}
				},100);
			}, function(e) {
				//fail
			});
		}
			
		self.view.element("imgSel").addEventListener("change",function(e) {
			scanCode(URL.createObjectURL(e.target.files[0]));
		});
				
		self.addEventListener("destruct",function(){
			if (vidTimer) clearTimeout(vidTimer);
			if (mediaStream) mediaStream.stop();
			video.src = "";
		});
	});
});
