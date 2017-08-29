var v = value.split(" ");

if (v.length > 1) {
	v[0] = Number(v[0]);

	switch (fields.type.value.value) {
		case "expense":
			if (v[0] > 0) v[0] = -v[0];
			break;
		
		case "sale":
			if (v[0] < 0) v[0] = -v[0];
			break;
	}
	value = v.join(" ");
}
