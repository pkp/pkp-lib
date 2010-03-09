function ajaxUpload(url, form, successCallback) {
 // bind form using ajaxForm
    $('#'+form).ajaxForm({
        // target identifies the element(s) to update with the server response
        target: '#uploadOutput',
		iframe: true,
		dataType: 'json',
		beforeSubmit: function() {
//			$('#loading').throbber({
//				bgcolor: "#CED7E1",
//				speed: 1
//			});
//			$('#loading').throbber('enable');
			$('#loadingText').fadeIn('slow');
    	},
        // success identifies the function to invoke when the server response
        // has been received; here we show a success message and enable the next tab
        success: function(returnString) {
		if (successCallback) {
			successCallback();
		}

    		if (returnString.status == true) {
//    			$('#loading').throbber("disable");
	    		//$('#loading').hide();
	    		
	    		// FIXME: NEED TO USE A JS FUNCTINO TO LOAD CONTENT INTO MODAL
	    		//$('li#tab1 > a').attr('href', returnString.metadataUrl);
	    		$('#fileUploadTabs').tabs('url', 1, returnString.metadataUrl);
	    		
	    		$('#fileUploadTabs').tabs('enable', 1);
    		}
    		$('#loadingText').text(returnString.content);  // Set to error or success message
        }
    });
}