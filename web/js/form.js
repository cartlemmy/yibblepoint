
function formErrors(fields) {
	var n, field, err, m = [], hasErrors;
	for (n in fields) {
		field = fields[n];
		if (fields[n].errors && fields[n].errors.length) {
			hasErrors = true;
			m.push(field.label+(fields[n].errors.length>1?":\n\t"+fields[n].errors.join("\n\t"):" "+fields[n].errors[0]));
		} else hasErrors = false;
		
		if (window.parent.document.getElementById(field.id)) window.parent.document.getElementById(field.id).parentNode.className = "form-group"+(hasErrors ? " has-error" : " has-success");
	}
	alert(m.join("\n"));
}
