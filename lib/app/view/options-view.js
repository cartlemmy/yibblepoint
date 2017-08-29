
self.setContentFromHTMLFile();

self.formatContent({"singleName":app.setup.singleName});


function appeared() {
	console.log(self);
};

self.addEventListener("appeared",function(){
	appeared();
});


appeared();


