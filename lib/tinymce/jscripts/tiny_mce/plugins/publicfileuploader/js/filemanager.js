function toggleUrlField(elem) {
	var urlField = elem.parentNode.nextSibling;
	var displayMode = urlField.style.display;
	
	if (displayMode == 'none') {
		urlField.style.display = 'block';
	} else {
		urlField.style.display = 'none';
	}
}