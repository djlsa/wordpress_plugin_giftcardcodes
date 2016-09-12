function crc32(s/*, polynomial = 0x04C11DB7, initialValue = 0xFFFFFFFF, finalXORValue = 0xFFFFFFFF*/) {
	s = String(s);
	var polynomial = arguments.length < 2 ? 0x04C11DB7 : arguments[1],
			initialValue = arguments.length < 3 ? 0xFFFFFFFF : arguments[2],
			finalXORValue = arguments.length < 4 ? 0xFFFFFFFF : arguments[3],
			crc = initialValue,
			table = [], i, j, c;
 
	function reverse(x, n) {
		var b = 0;
		while (n) {
			b = b * 2 + x % 2;
			x /= 2;
			x -= x % 1;
			n--;
		}
		return b;
	}
 
	for (i = 255; i >= 0; i--) {
		c = reverse(i, 32);
 
		for (j = 0; j < 8; j++) {
			c = ((c * 2) ^ (((c >>> 31) % 2) * polynomial)) >>> 0;
		}
 
		table[i] = reverse(c, 32);
	}
 
	for (i = 0; i < s.length; i++) {
		c = s.charCodeAt(i);
		if (c > 255) {
			throw new RangeError();
		}
		j = (crc % 256) ^ c;
		crc = ((crc / 256) ^ table[j]) >>> 0;
	}
 
	return (crc ^ finalXORValue) >>> 0;
}

var i = 0;
var map = [];
var codes = "";
var loop = setInterval( function() {
	var fullcode;
	do {
		fullcode = "";
		for(var j = 0; j < 3; j++) {
			do {
				var v = "" + Date.now() + " " + Math.random();
				var code = crc32(v).toString(36).toUpperCase().substr(0, 6);
			} while(code.indexOf("0") >= 0 || code.indexOf("O") >= 0 || code.indexOf("I") >= 0);
			fullcode += code;
			if(j < 2)
				fullcode += "-";
		}
	} while(fullcode in map);
	map[fullcode] = v;
	codes += fullcode + "\r\n";
	if(++i == 500) {
		clearInterval(loop);
		console.log(codes);
		copy(codes);
	}
}, 1);