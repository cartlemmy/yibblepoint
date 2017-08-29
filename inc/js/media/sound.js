(function(){
	sl.sound = {
		"sounds":{},
		"play":function(sound, endedCB) {
			if (sound.substr(0,6) == "speak:" && sl.sound.speak) {
				sl.sound.speak(sound.substr(6), endedCB);
				return;
			}
			
			var elist;
			if (!self.sounds[sound]) {
				var s = document.createElement('source'), a = new Audio;
				if (a.canPlayType('audio/mpeg;')) {
					s.type = 'audio/mpeg';
					s.src = path+sound+'.mp3';
				} else {
					s.type = 'audio/ogg';
					s.src = path+sound+'.ogg';
				}
				
				a.appendChild(s);
				
				self.sounds[sound] = a;
			} else a = self.sounds[sound];
			a.play();
			if (endedCB) {
				function cb(){
					endedCB();
					a.removeEventListener('ended',cb);
				}
				a.addEventListener('ended',cb);
			}
		}
	};
	
	var self = sl.sound, path, it = setInterval(function(){
		if (sl.config.webRoot) {
			sl.require("http://code.responsivevoice.org/responsivevoice.js",function(){
				sl.sound.speak = function(text,cb) {
					var params = {};
					if (cb) params.onend = cb;
					responsiveVoice.speak(text, "US English Female", params);
				}
			});
	
			path = sl.config.webRoot+"lib/sounds/";
			clearInterval(it);
		}
	},200);
})();
