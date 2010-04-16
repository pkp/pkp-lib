<?php
	// urlForm.inc.php
	// Matt Crider, Aug. 7 2008
	// Displays form for user to enter image url
	
?>
<!-- do not delete this line - it's need for proper working of the resizeDialogToContent() function -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Insert Image</title>	
	<meta http-equiv="Pragma" content="no-cache">
	<style type="text/css"> @import url("../css/style.css"); </style>
	<script type="text/javascript" src="http://www.memescheme.net/pkp/ojs2-stable/lib/tinymce/jscripts/tiny_mce/tiny_mce_popup.js"></script>
	<script type="text/javascript" src="dialog.js"></script>
</head>
<body onload="tinyMCEPopup.executeOnLoad('init();')">

	<div class="headerDiv">
		<h4>Insert Image URL</h4>
		<div class="thickSeparator"> </div>
	</div>
	<div class="rowDiv">
      <p style="margin-left: 20px; margin-top:15px; width: 400px;"> The image upload utility is only enabled for registered users.  Instead, you may enter a URL to an image you wish to include. </p>
    </div>
	<form name="source" action="#">
		<input type="hidden" name="wraped" id="wraped"/>
		
		<input name="htmlSource" id="htmlSource" type="text" size="50" maxlength="80" style="margin-left:20px;">
		<br /><br />
		<div class="mceActionPanel">
			<input type="submit" name="insert" class="btn" value="Insert" onclick="URLDialog.insert();" id="insert" style="margin-left: 20px;" />
			<input type="button" name="cancel" class="btn" value="Cancel" onclick="tinyMCEPopup.close();" id="cancel" style="margin-left: 20px;"/>
		</div>
	</form>
</body>
</html>






