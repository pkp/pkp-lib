var URLDialog = {
	init : function() {
		var f = document.forms[0];

		// Get the selected contents as text and place it in the input
		//f.htmlSource.value = tinyMCEPopup.editor.selection.getContent({format : 'text'});
	},

	insert : function() {
		// Insert the contents from the input into the document
		tinyMCEPopup.editor.execCommand('mceInsertContent', false, '<img src=\"' + document.forms[0].htmlSource.value + '\"/>');
		tinyMCEPopup.close();
	}
};

tinyMCEPopup.onInit.add(URLDialog.init, URLDialog);