self.setTitle("en-us|Completing Setup");
self.setContentFromHTMLFile();

function msg(txt) {
	self.view.element("info").innerHTML += txt+"\n";
	self.view.element("scroller").scrollTop = self.view.element("scroller").scrollHeight;
}

var step = 0;
function nextStep(cb) {
	app.request("complete",[step++],function(res){
		msg(res.out);
		//console.log(res);
		if (!res.error) cb();
	});
};

self.view.element("continue").addEventListener("click",function(){
	window.location.reload();
});

self.addEventListener("appeared",function(){
	sl.chainer(
		[self,nextStep],
		[self,nextStep],
		[self,nextStep],
		[self,function(cb){
			msg("<b>Logging in as Super User</b>");
			app.request("getConfig",["superUser"],function(data){
				console.log(data);
				self.core.net.send("login",{"user":data.user,"password":data.password},{"queueTime":0},function(response){
					console.log(response);
					if (response) {
						if (response.success) {
							msg("logged in\n");
							cb();
						} else if (response.error) {
							msg(response.error+"\n");
						}
					}
				});
			});
		}],
		[self,function(){
			msg("Setup Complete.");
			self.view.element("showContinue").style.display = "";
		}]
	);
});
