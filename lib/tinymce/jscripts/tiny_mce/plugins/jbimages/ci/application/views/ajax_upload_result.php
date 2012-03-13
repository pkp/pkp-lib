<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>JustBoil's Result Page</title>
<script language="javascript" type="text/javascript">
	window.parent.window.jbImagesDialog.uploadFinish({
		filename:'<?php echo html_escape($file_name); ?>',
		result: '<?php echo html_escape($result); ?>',
		resultCode: '<?php echo html_escape($resultcode); ?>'
	});
</script>
</head>

<body>

Result: <?php echo html_escape($result); ?>

</body>
</html>
