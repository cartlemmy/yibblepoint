
sl.loadedSounds = {};

sl.sound = function(name) {
	if (sl.loadedSounds[name]) {
		if (sl.loadedSounds[name].ready) {
			sl.loadedSounds[name].setAttribute("src",sl.loadedSounds[name].getAttribute("src"));
			sl.loadedSounds[name].play();
		}
	} else {
		if (window.Audio) {
			var sound = new Audio();
			var types = {
				"ogg":"audio/ogg","mp3":"audio/mpeg","m4a":"audio/mp4"
			};
			for (var i in types) {
				if (sound.canPlayType(types[i])) {
					sound.setAttribute("src","sounds/"+name+"."+i);
					break;
				}
			}
			sound.load();		
			sound.addEventListener("canplaythrough", function() {
				sound.ready = true;
				sound.play(); 
			}, true);
			sl.loadedSounds[name] = sound;
		} else { //Flash fallback
		}
	}
};
