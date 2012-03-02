<head>
	<title><?=lang('jb_uploaded_images') ?></title>
	<link href="../../../css/dialog.css" rel="stylesheet" type="text/css" />
</head>

<div id="imageListing">
<? if($files) {
		foreach ($files as $file) { ?>
			<img src="<? echo html_escape($file['img_path']) ?>" width="25" height="25" alt="<? echo html_escape($file['name']) ?>" />
			<a href="<? echo html_escape($file['img_path']) ?>"><? echo html_escape($file['name']) ?></a>&nbsp;
			(<? echo html_escape($file['size']) ?> kB)&nbsp;&nbsp;&nbsp;
			<span class="delete"><a href="../deleteImage/<? echo html_escape($file['name']) ?>"><?=lang('jb_delete') ?></a></span>
			<br />
<? 		}
	} else { ?>
	<h3><? echo lang('jb_no_files') ?></h3>
	<a href="../../../dialog.htm"><?=lang('jb_go_back') ?></a>
<? } ?>
</div>