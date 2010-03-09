function ajaxUpload(url, form, successCallback) {
 // bind form using ajaxForm
    $('#'+form).ajaxForm({
        // target identifies the element(s) to update with the server response
        target: '#uploadOutput',
		iframe: true,

        // success identifies the function to invoke when the server response
        // has been received; here we apply a fade-in effect to the new content
        success: function() {
	    if (successCallback) {
		  successCallback();
	    }

            $('#uploadOutput').fadeIn('slow');
        }
    });
}