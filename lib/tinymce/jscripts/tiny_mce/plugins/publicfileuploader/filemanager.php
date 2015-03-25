<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{#publicfileuploader_dlg.manage}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="text/javascript" src="../../tiny_mce_popup.js"></script>
	<script type="text/javascript" src="js/dialog.js"></script>
	<script type="text/javascript" src="js/filemanager.js"></script>
	<link href="css/style.css" rel="stylesheet" type="text/css" />
</head>
<body>
	<div class='container_upload'>
		<p>{#publicfileuploader_dlg.your_directory}:</p>
		
<?php
require_once('classes/PublicFileUploaderPlugin.inc.php');
return new PublicFileUploaderPlugin(false, true);
?> 