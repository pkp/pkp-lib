<head>
	<title><?php echo lang('jb_uploaded_images') ?></title>
	<link href="../../../css/dialog.css" rel="stylesheet" type="text/css" />
</head>

<div id="imageListing">
<?php if($files) {
		foreach ($files as $file) { ?>
			<img src="<?php echo html_escape($file['img_path']) ?>" width="25" height="25" alt="<?php echo html_escape($file['name']) ?>" />
			<a href="<?php echo html_escape($file['img_path']) ?>"><?php echo html_escape($file['name']) ?></a>&nbsp;
			(<?php echo html_escape($file['size']) ?> kB)&nbsp;&nbsp;&nbsp;
			<span class="delete"><a href="../deleteImage/<?php echo urlencode($file['name']) ?>"><?php echo lang('jb_delete') ?></a></span>
			<br />
<?php 		}
	} else { ?>
	<h3><?php echo lang('jb_no_files') ?></h3>
	<a href="../../../dialog.htm"><?php echo lang('jb_go_back') ?></a>
<?php } ?>
</div>
