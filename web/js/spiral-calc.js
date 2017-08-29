(function(){
	var maxPasses = 200,
		maxCalcTime = 10, // In seconds
		precision = 0.1, // ±%
		fractionOfInch = 64, // Fraction precision: 1 / fractionOfInch
		margin = 0.5, // In inches
		lineWidth = 0.25, //In inches
		fontSizePt = 12, // In points
		fontFamily = "monospace",
		dpi = 200,
		i, j, n, calci,
		aStep = 1/128*Math.PI, dStep, startTS,
		stack = 0, R = 0, A = 0, L = 0, adj, pass,
		canvasSize = 0, canvas = document.getElementById('spiral'), ctx,
		inputElsIds = [
			"innerDiameter","outerDiameter","outerGuideDiameter",
			"length","turns","distBetween"
		], inputListeners = ["change", "keyup", "paste"], p = {},
		textCursor = {"x":0, "y":0, "xStart":0}, fontSizePx = (fontSizePt / 72) * dpi;
		
	function TS() {
		return Date.now() / 1000;
	}
		
	function csave() {
		ctx.save();
		stack ++;
	}
	
	function crestore() {
		if (stack <= 0) return;
		ctx.restore();
		stack --;
	}
	
	function calc(dStep) {
		var dist, xd, yd, x, y, hlw = lineWidth * dpi / 2;
		R = p.innerDiameter.val * dpi;
		p.turns.val = A = L = 0;
		
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		
		ctx.beginPath();
		ctx.lineWidth = (1 / 36) * dpi;
		ctx.strokeStyle = '#000000';
		ctx.arc(canvas.width / 2, canvas.height / 2, (((p.outerGuideDiameter.val * dpi) - 1) / 2), 0, 2 * Math.PI, false);
		ctx.stroke();
		
		ctx.save();
		
		ctx.translate(canvas.width / 2, canvas.height / 2);
		
		ctx.beginPath();
		ctx.lineWidth = lineWidth * dpi;
		    
		ctx.moveTo(Math.cos(A) * (R - hlw),Math.sin(A) * (R - hlw));
		
		while (L < p.length.val) {
			xd = Math.cos(A) * R - Math.cos(A + aStep) * R;
			yd = Math.sin(A) * R - Math.sin(A + aStep) * R;
			dist = Math.sqrt(xd * xd + yd * yd);
			
			R += dStep;
			A += aStep;
			L += dist / dpi;
			
			ctx.lineTo(Math.cos(A) * (R - hlw),Math.sin(A) * (R - hlw));			
		}


		p.turns.val = A / (Math.PI * 2);
		p.distBetween.val = ((R - p.innerDiameter.val * dpi) / p.turns.val) / dpi;

		ctx.stroke();
		
		ctx.restore();
				
		return (R * 2) / dpi;
	}
	
	function done(txt) {
		clearInterval(calci);
		p.turns.el.innerHTML = p.turns.val;
		p.distBetween.el.innerHTML = asFraction(p.distBetween.val);

		setTextCursor(0.25, 0.25);
		
		showStat("innerDiameter", "Inner Diameter", "inches");
		showStat("outerDiameter", "Outer Diameter", "inches");
		showStat("outerGuideDiameter", "Outer Guide Diameter", "inches");
		showStat("length", "Spiral Length", "inches");
		showStat("turns", "Turns", "float");
		showStat("distBetween", "Distance Between", "inches");
	}
	
	function showStat(n, label, type) {
		var v = p[n].val, txt = label+": ";
		if (type === undefined) type = "text";
		switch (type) {
			case "inches":
				txt += asFraction(v)+'\"';
				break;
			
			case "float":
				txt += v.toLocaleString('en-US', {maximumFractionDigits: 4});
				break;
			
			case "text": default:
				txt += ""+v;
				break;
		}
		outText(txt);
	}
	
	function outText(txt) {
		ctx.font = fontSizePx+"px "+fontFamily;
	    ctx.fillText(txt,textCursor.x,textCursor.y);
	    textCursor.y += fontSizePx;
	    textCursor.x = textCursor.xStart;
	}
	
	function setTextCursor(x, y) {
		textCursor.x = textCursor.xStart = toPx(x);
		textCursor.y = toPx(y);
	}
	
	function toPx(inch) {
		return inch * dpi;
	}
	
	function asFraction(v) {
		var integ = Math.floor(v),
			num = Math.round((v - integ) * fractionOfInch),
			den = fractionOfInch;
		
		if (num == 0) return integ;
		if (num == fractionOfInch) return 1;
		
		while (((num / 2) % 1) == 0 && den > 1) {
			console.log(integ+" "+num+"/"+den);
			num /= 2;
			den /= 2;
		}
		return (integ == 0 ? "" : integ)+" "+num+"/"+den;
		
	}
	window.af = asFraction;
	
	function recalc() {
		if (
			p.outerDiameter.val <= Math.max(0, p.innerDiameter.val) ||
			p.outerGuideDiameter.val <= Math.max(0, p.outerDiameter.val) ||
			p.length.val <= 0 || p.innerDiameter.val <= 0
		) return;
		
		
		startTS = TS();
		
		canvasSize = Math.round((p.outerDiameter.val + Math.max(margin, (p.outerGuideDiameter.val - p.outerDiameter.val) / 2) * 2) * dpi);
		
		canvas.width = canvas.height = canvasSize;
		canvas.style.width = "8.5in";
		//canvas.style.width = (canvas.width / dpi)+"in";
		//canvas.style.height = (canvas.height / dpi)+"in";
		
		ctx = canvas.getContext("2d"); 
		
		
		dStep = 0.1;
		adj = dStep / 2;
		pass = 0;
				
		if (calci) clearInterval(calci);
		calci = setInterval(function() {
			var diam = calc(dStep),
				offBy = Math.abs(p.outerDiameter.val - diam),
				offByPercent = (offBy / p.outerDiameter.val) * 100;
				
			pass++;
			console.log(
				"Pass:"+pass,
				"Diameter:"+diam,
				"Off By:"+offBy.toLocaleString('en-US', {maximumFractionDigits: 4})+
				" ("+offByPercent.toLocaleString('en-US', {maximumFractionDigits: 4})+"%)"
			);
			
			
			dStep *= p.outerDiameter.val / diam;
			if (pass >= maxPasses) done("Maximum passes ("+maxPasses+") reached");
			if (offByPercent < precision) done("Requested precision (±"+precision.toLocaleString('en-US', {maximumFractionDigits: 4})+"%) reached");
			if (TS() - startTS > maxCalcTime) done("Maximum calculation time ("+maxCalcTime+" seconds) reached");
		},30);
	}
	
	function setValFromElement(el, v) {
		var v = Number(el.value);
			v = isNaN(v) ? 0 : v;
			
		if (p[el.id].val == v) return false;
		p[el.id].val = v;
		return true;
	}
	
	function inputEvent(e) {
		if (p[e.target.id].T) clearTimeout(p[e.target.id].T);
		p[e.target.id].T = setTimeout(function(){
			if (!setValFromElement(e.target, e.target.value)) return;
			
			recalc();
		},100);
	}
	
	for (i = 0; i < inputElsIds.length; i++) {
		n = inputElsIds[i];
		p[n] = {"el":document.getElementById(n),"val":0,"T":null};
		for (j = 0; j < inputListeners.length; j++) {
			p[n].el.addEventListener(inputListeners[j], inputEvent);
			setValFromElement(p[n].el, p[n].el.val)
		}
	}
	
	recalc();
	
})();
