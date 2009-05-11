<?php
	// ================================================
	// PHP image browser - iBrowser 
	// Heavily modified by Matt Crider for the PKP
	// ================================================
	// iBrowser dialog
	// ================================================
	// Developed: net4visions.com
	// Copyright: net4visions.com
	// License: GPL - see license.txt
	// (c)2005 All rights reserved.
	// File: ibrowser.php
	// ================================================
	// Revision: 1.3                   Date: 08/29/2006
	// ================================================
	//-------------------------------------------------------------------------
	ini_set('display_errors', E_ALL);
	// unset $cfg['ilibs_incl'] - dynamic image library
	if (isset($cfg['ilibs_inc'])) {
		$cfg['ilibs_inc'] = '';
	}
	//-------------------------------------------------------------------------
	// include configuration settings
	include dirname(__FILE__) . '/config/config.inc.php';
	include dirname(__FILE__) . '/langs/lang.class.php';	
	//-------------------------------------------------------------------------
	// language settings	
	$l = (isset($_REQUEST['lang']) ? new PLUG_Lang($_REQUEST['lang']) : new PLUG_Lang($cfg['lang']));
	$l->setBlock('ibrowser');	
	//-------------------------------------------------------------------------
	// if set, include file specified in $cfg['ilibs_incl']; hardcoded libraries will be ignored!	
	if (!empty($cfg['ilibs_inc'])) {
		include $cfg['ilibs_inc'];
	}	
	//-------------------------------------------------------------------------		
	// set current image library	
	$clib = (isset($_REQUEST['clib']) ? $_REQUEST['clib'] : '');
	//-------------------------------------------------------------------------	
	$value_found = false;
	// callback function for preventing listing of non-library directory
	function is_array_value($value, $key, $tlib) {
		global $value_found;
		if (is_array($value)) {
			array_walk($value, 'is_array_value', $tlib);
		}
		if ($value == $tlib) {
			$value_found = true;
		}
	}	
	//-------------------------------------------------------------------------	
	if(isset($user)) {
		array_walk($cfg['ilibs'], 'is_array_value', $clib);	
		if (!$value_found || empty($clib)) {
			$clib = $cfg['ilibs'][0]['value'];
		}		
	}
	//-------------------------------------------------------------------------
	// create library dropdown
	$lib_options = liboptions($cfg['ilibs'], '', $clib,'');
?>
<!-- do not delete this line - it's need for proper working of the resizeDialogToContent() function -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<head>
<title><?php echo $l->m('im_002'); ?></title>
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $l->getCharset(); ?>">
<style type="text/css">
<!--
	@import url("css/style.css");
-->
</style>
<script language="javascript" type="text/javascript" src="scripts/resizeDialog.js"></script>
<script language="JavaScript" type="text/javascript" src="scripts/validateForm.js"></script>
<script language="JavaScript" type="text/JavaScript">
<!--
// ============================================================
// = global instance V 1.0, date: 04/07/2005                  =
// ============================================================
	function iBrowser() {
		// browser check
		this.isMSIE  = (navigator.appName == 'Microsoft Internet Explorer');
		this.isGecko = navigator.userAgent.indexOf('Gecko') != -1;	
		this.isWebKit = navigator.userAgent.indexOf('Safari') != -1;
	};
	var iBrowser = new iBrowser();

// ============================================================
// = iBrowser init V 1.0, date: 12/03/2004                    =
// ============================================================
	function init() {			
		resizeDialogToContent();		
		window.focus();		
	}

// ============================================================
// = set title - V 1.0, date: 06/03/2005                      =
// ============================================================
	function setTitle(elm) {
		var retstr;
		switch(elm) {
			case 'imDiv':
				retstr = '<?php echo $l->m('im_004'); ?>';
				break;		
			case 'inDiv':
				retstr = '<?php echo $l->m('im_008'); ?>';
				break;
			case 'atDiv':
				retstr = '<?php echo $l->m('im_010'); ?>';
				break;
			case 'poDiv':
				retstr = '<?php echo $l->m('im_014'); ?>';
				break;
			default:
				retstr = '<?php echo $l->m('im_016'); ?>'; 		
		}
		return retstr;	
	}
// ============================================================
// = set image properties V 1.0, date: 04/25/2005             =
// ============================================================		
	function setImageArgs() {
		var formObj = document.forms[0];		
		var args = window.dialogArguments;					
		
		if (args.tsrc) { 											// dynamic thumbnail
			formObj.pr_src.value = args.tsrc;
			changeClass(0,'alertImg','showit');						// show warning		
		}
		if (args.rset) {
			formObj.pr_src.value = args.rsrc;
		}
		
		formObj.pr_width.value 	= args.width  ? args.width  : '';	// WIDTH value		
		formObj.pr_height.value	= args.height ? args.height : '';	// HEIGHT value
		formObj.pr_alt.value 	= args.alt;							// ALT text		
		formObj.pr_title.value 	= args.title;						// DESCR text
		formObj.pr_border.value = args.border ? args.border : '';	// BORDER value	
		formObj.pr_vspace.value = args.vspace ? args.vspace : '';	// VSPACE value				
		formObj.pr_hspace.value = args.hspace ? args.hspace : '';	// HSPACE value
		
		if (args.caption == 1) { // if image caption
			formObj.pr_chkCaption.checked = true;
			for (var i = 0; i < formObj.pr_captionClass.options.length; i++) {	// CLASS value
				if (formObj.pr_captionClass.options[i].value == args.captionClass) {
					formObj.pr_captionClass.options.selectedIndex = i;				
				}
			}
		}
		
		for (var i = 0; i < formObj.pr_align.options.length; i++) {	// ALIGN value 
			if (formObj.pr_align.options[i].value == args.align) {
				formObj.pr_align.options.selectedIndex = i;				
			}
		}
		
		for (var i = 0; i < formObj.pr_class.options.length; i++) {	// CLASS value
			if (formObj.pr_class.options[i].value == args.className) {
				formObj.pr_class.options.selectedIndex = i;				
			}
		}

		formObj.param.value = ''; // resetting param value		
	}
// ============================================================
// = insertImage, date: 08/03/2005                            =
// ============================================================
	function insertImage() {
		var formObj = document.forms[0];
		var args = {};
		// check if valid image is selected		
		if (!args.action) { // if not popup	mode, check whether there is a valid image selected		
			if (formObj.pr_src.value == '') { // no valid picture has been selected				
				var msg = escapeHTML('<?php echo $l->m('er_001') . ': ' . $l->m('er_002'); ?>');
				alert(msg);
				return;
			}
			
			args.src = (formObj.pr_src.value) ? (formObj.pr_src.value) : '';									
			if ('<?php echo $cfg['furl']; ?>' == true) { // create full url incl. e.g. http://localhost....
				args.src = '<?php echo $cfg['base_url']; ?>' + args.src;				
			}
						
			args.width  = (formObj.pr_width.value)  ? (formObj.pr_width.value)  : '';
			args.height = (formObj.pr_height.value) ? (formObj.pr_height.value) : '';				
			args.align 	= (formObj.pr_align.value)  ? (formObj.pr_align.value)  : '';
			args.border = (formObj.pr_border.value) ? (formObj.pr_border.value) : '';				
			args.alt 	= (formObj.pr_alt.value)    ? (formObj.pr_alt.value)    : '';
			args.title 	= (formObj.pr_title.value)  ? (formObj.pr_title.value)  : '';
			args.hspace = (formObj.pr_hspace.value) ? (formObj.pr_hspace.value) : '';
			args.vspace = (formObj.pr_vspace.value) ? (formObj.pr_vspace.value) : ''; 
			if (formObj.pr_class.selectedIndex > 0) { // if class style is selected
				args.className = (formObj.pr_class.options[formObj.pr_class.selectedIndex].value) ? (formObj.pr_class.options[formObj.pr_class.selectedIndex].value) : '';
			}
			// caption parameters
			args.caption = formObj.pr_chkCaption.checked ? formObj.pr_chkCaption.value : '';
			args.captionClass = (formObj.pr_captionClass.options[formObj.pr_captionClass.selectedIndex].value) ? (formObj.pr_captionClass.options[formObj.pr_captionClass.selectedIndex].value) : '';
		} else { // check whether there is valid popup image
			if (formObj.popSrc.value == '') { // no valid picture has been selected				
				var msg = escapeHTML('<?php echo $l->m('er_001') . ': ' . $l->m('er_002'); ?>');
				alert(msg);
				return;
			}
		}		
			
		
		
		//-------------------------------------------------------------------------	
		// save image to wysiwyg editor and close window		
		window.returnValue = args;
		window.close();				
		
		if (!iBrowser.isMSIE) { // Gecko	
			<?php					
				if (!empty($_REQUEST['callback'])) {          				
					echo "opener." . @$_REQUEST['callback'] . "('" . @$_REQUEST['editor'] . "',this);\n";
				};
			?>	
		}	
		
	
	
	}
	//Used to build and insert the img url when users are not logged in
	function saveContent() {
			URL = document.getElementById('htmlSource').value;
			var args = {};
			args.src = URL;

			window.returnValue = args;
			window.close();				
		
			if (!iBrowser.isMSIE) { // Gecko	
				<?php					
					if (!empty($_REQUEST['callback'])) {          				
						echo "opener." . @$_REQUEST['callback'] . "('" . @$_REQUEST['editor'] . "',this);\n";
					};
				?>	
			}
		}
// ============================================================
// = image change - set attributes V 1.0, date: 12/03/2004    =
// ============================================================
	function imageChange() {		
		var formObj = document.forms[0];
		var args 	= imageChange.arguments;  												// image change arguments - set by rfiles.php						
		var clib    = "<?php echo $cfg['ilibs'][0]['value'] ?>";	// current library - absolute path		
		var cfile   = document.getElementById('cimg').attributes['cfile'].value;			// get current image
		var cwidth  = document.getElementById('cimg').attributes['cwidth'].value;			// get current width	
		var cheight = document.getElementById('cimg').attributes['cheight'].value;			// get current height		
		var csize   = document.getElementById('cimg').attributes['csize'].value.split('|');	// get current size (array)
		var ctype   = document.getElementById('cimg').attributes['ctype'].value.split('|');	// get current type (array)	
			
		//-------------------------------------------------------------------------
		// set default image attributes
		formObj.pr_src.value    = clib + cfile;		
		formObj.pr_width.value  = cwidth;
		formObj.pr_height.value = cheight;		
		formObj.pr_size.value   = csize[0];		
		formObj.pr_align.options.selectedIndex = 0;
		formObj.pr_class.options.selectedIndex = 0;
		document.getElementById('pr_sizeUnit').innerHTML = csize[1]; // e.g. kb		
		formObj.pr_alt.value 	= cfile.substr(0, cfile.length-4);
		formObj.pr_title.value 	= cfile.substr(0, cfile.length-4);		
		changeClass(0,'alertImg','hideit');
		//-------------------------------------------------------------------------
		// update preview window	
		var sizes = resizePreview(cwidth, cheight, 150, 150);		
		var src = '<?php echo $cfg['scripts']; ?>' + 'phpThumb/phpThumb.php'; // command
		src = src + '?src=' + clib + cfile; // source file
		src = src + '&w=' + sizes['w']; // width		
		document.getElementById('inPrevFrame').src = src; // update regular preview
		
		//-------------------------------------------------------------------------
		// reset rename and delete info
		if ('<?php echo $cfg['rename']; ?>' == true) {
			formObj.in_srcnew.value  = cfile.substr(0, cfile.length-4); // default rename value			
		}
		if ('<?php echo $cfg['delete']; ?>' == true) {
			formObj.in_delinfo.value = cfile; 							// default delete value
		}
		
		//-------------------------------------------------------------------------
		// change image attributes in case it's an existing image		
		if (args[0] == 'update') { 	// if argument from rfiles.php received				
			setImageArgs(); 		// update image attributes
		} else if (args[0] == 'delete') { // image was deleted
			document.getElementById('cimg').attributes['cfile'].value = '';			
			document.getElementById('in_srcnew').value  = '';
			document.getElementById('in_delinfo').value = '';			
			document.getElementById('inPrevFrame').src = 'images/noImg.gif'; // update preview
		}

	}


// ============================================================
// = resize image to fit preview V 1.0, date: 12/19/2004      =
// ============================================================	
	function resizePreview(w,h,mw,mh) { // width, height, max width, max height				
		var sizes = new Array();		
		if (w > mw || h > mh) { // thumbnailing required
			f = w / h; // proportions of image: (f > 1) = landscape; (f < 1) = portrait; (f = 1) = square			
			if (f > 1) { // landscape and square
				w = mw;
				h = Math.round(w / f);			
			} else if (f <= 1) {	// portrait
				h = mh;				
				w = Math.round(h * f);			
			}	
		}				
		sizes['w'] = w;
		sizes['h'] = h;
		return sizes;
	}

// ============================================================
// = preload Images, date: 11/13/2004                         =
// ============================================================		
	function preloadImages() {
  		var d=document;
		if(d.images) {
			if(!d.MM_p)
				d.MM_p = new Array();
    			var i,j=d.MM_p.length,a = preloadImages.arguments;
				for(i= 0; i < a.length; i++)
    				if (a[i].indexOf("#") != 0) {
						d.MM_p[j] = new Image;
						d.MM_p[j++].src = a[i];
			}
		}
	}

// ============================================================
// = upload image, date: 05/24/2005                           =
// ============================================================
	function uploadClick() {
		var formObj = document.forms[0];		
		if (!checkUpload()) {
			var msg = escapeHTML('<?php echo $l->m('er_001') . ': ' . $l->m('er_023'); ?>');
			alert(msg);
			return;
		}
		
		<?php if ($cfg['captcha'] ){ ?>
		if(!checkCaptcha()) {
			alert("Captcha validation failed");
			return;
		}
		<?php } ?>
		
		if (confirm('<?php echo $l->m('er_021'); ?>')) {			
			formObj.param.value = 'upload'; // parameter: <action>			
			formObj.submit();						
		}
	}
	// check whether image file is selected for uploading
	function checkUpload() {		
		var formObj = document.forms[0];	
		var upload = false;
		var x = document.getElementById('fiUplDiv').getElementsByTagName('input');
		for (var i = 0; i < x.length; i++) {
			if (x[i].type == 'file') {
				if (x[i].value != '') { // check whether files has been selected for upload					
					
					for (z=0; document.getElementById('chkThumbSize['+ z +']'); z++) {						
						if(document.getElementById('chkThumbSize['+ z +']').checked) {
							upload = true;							
						}						
					}
				}
			}			
		}
		return upload;	
	}
	
	function checkCaptcha() {
		var x = document.getElementById('captcha');
		var y = document.getElementById('cVal');
		if (x.value != y.value) { 
			return false;
		}
		else return true;

	}
// ============================================================
// = delete image V 1.0, date: 04/22/2005                     =
// ============================================================
	function deleteClick() {
		var formObj = document.forms[0];		
		var cfile = document.getElementById('cimg').attributes['cfile'].value;
		if (cfile == '') { // check if image is selected	
			var msg = escapeHTML('<?php echo $l->m('er_001') . ': ' . $l->m('er_002'); ?>');
			alert(msg);
			return;
		}
				
		if (confirm('<?php echo $l->m('er_008'); ?> ' + cfile + '!')) {				
			formObj.param.value = 'delete' + '|' + cfile; // parameter: <action>|<file>				
			formObj.submit();	
		}	  	
	} 
// ============================================================
// = rename image V 1.0, date: 04/22/2005                     =
// ============================================================
	function renameClick() {
		var formObj = document.forms[0];
		var clib =  "<?php echo $cfg['ilibs'][0]['value'] ?>"; // current library
		var cfile = document.getElementById('cimg').attributes['cfile'].value;		
		var ctype = document.getElementById('cimg').attributes['ctype'].value.split('|');		
				
		if (cfile == '') { // check if image is selected
			var msg = escapeHTML('<?php echo $l->m('er_001') . ': ' . $l->m('er_002'); ?>');
			alert(msg);
			return;
		}		
		
		var ctype = '.' + imageType(ctype[0]);		
		if (formObj.in_srcnew.value == '' || formObj.in_srcnew.value + ctype == cfile) { // new name is either empty or hasn't changed
			var msg = escapeHTML('<?php echo $l->m('er_011'); ?>');
			alert(msg);
			return;
		}
				
		if (confirm('<?php echo $l->m('er_010'); ?>: ' + formObj.in_srcnew.value + ctype)) { // do rename					
			var nfile = formObj.in_srcnew.value + ctype;				 
			formObj.param.value = 'rename' + '|' + cfile + '|' + nfile; // parameter: <action>|<filename>|<newname>		
			formObj.submit();				
		}		  	
	}
// ============================================================
// = change class, date: 12/01/2004                           =
// ============================================================
	function changeClass() { 		
		var args = changeClass.arguments; 		
		if (args[0] == 0 || args[0] == 1) { // 0 = no resizeDialogToContent; 1 = resizeDialogToContent
			var start = 1;
		} else {
			var start = 0;
		}
		
		for(var i = start; i < args.length; i += 2) {
			if(document.getElementById(args[i]) != null) {				
				document.getElementById(args[i]).className = args[i+1];
			}
		}
		// resize dialog to content
		if (args[0] == 1) {					
			resizeDialogToContent();
		}		
	}	
// ============================================================
// = image dimension change, date: 05/08/2005                 =
// ============================================================		
	function changeDim(sel) {		
		var formObj = document.forms[0];
		var cwidth  = document.getElementById('cimg').attributes['cwidth'].value;			// get current width	
		var cheight = document.getElementById('cimg').attributes['cheight'].value;			// get current height	
		
		if (eval(formObj.pr_width.value) > cwidth || eval(formObj.pr_height.value) > cheight) { 		// check for enlarging			
			var msg = escapeHTML('<?php echo $l->m('er_001') . ': ' . $l->m('er_035'); ?>');
			alert(msg);
			resetDim();
			return;
		}		
		
		f = cheight/cwidth; // factor		
		if (sel == 1) { 																	// height changed				
			formObj.pr_width.value  = Math.round(formObj.pr_height.value / f);
		} else if (sel == 0) { 																// width changed			
			formObj.pr_height.value = Math.round(formObj.pr_width.value * f);			
		}		
	}
	
	function resetDim() { // reset dimensions
 		var formObj = document.forms[0];
		var cwidth  = document.getElementById('cimg').attributes['cwidth'].value;			// get current width	
		var cheight = document.getElementById('cimg').attributes['cheight'].value;			// get current height	
		formObj.pr_width.value  = cwidth;
		formObj.pr_height.value = cheight;
	}			
// ============================================================
// = show about, date: 06/04/2005                             =
// ============================================================	
	function about() {		
		var formObj = document.forms[0];		
		if (document.getElementById('imDiv').className == 'hideit') {
			var x = document.getElementById('menuBarDiv').getElementsByTagName('li');
			for (var i = 0; i < x.length; i++) {
				if (x[i].className == 'btnDown') {				
					formObj.param.value = (x[i].id);
					elm = x[i].id.substring(x[i].id.length-2, x[i].id.length);			
					if (elm == 'po') { // popup windows - uses inDiv
						elm = 'in'
					}
					elm = elm + 'Div';
					document.getElementById('mainDivHeader').innerHTML = setTitle('imDiv'); 		
					changeClass(1,elm,'hideit','imDiv','showit');											
				}
			}
		} else if (document.getElementById('imDiv').className == 'showit' && formObj.param.value != '') {
			elm = formObj.param.value;			
			btn_click(elm);			
		}
	}
// ============================================================
// = image file type extension V 1.0, date: 11/27/2004        =
// ============================================================	
	function imageType(type) {		
		var ext;		
		switch(parseInt(type)) {
			case 1 : ext = 'gif'; break;
   			case 2 : ext = 'jpg'; break;
			case 3 : ext = 'png'; break;
			case 6 : ext = 'bmp'; break;
   			default: ext = 'unknown';		
		}		
		return ext;
	}

// ============================================================
// = returns absolute path, date: 04/22/2005                  =
// ============================================================
	function absPath(path) {
		if (path.charAt(0) != '/') {
			path = '/' + path;			
		}
		return path;
	}
// ============================================================
// = escapeHTML, date: 08/12/2005                             =
// ============================================================
	function escapeHTML(str) {		
		var divElm = document.createElement('div');
		divElm.innerHTML = str; 
		str = divElm.innerHTML;
		return str;
  	}
//-->
</script>
</head>
<body onLoad="init();" dir="<?php echo $l->getDir(); ?>">
<?php  if (!isset($user)) {
		include dirname(__FILE__) . '/scripts/urlForm.inc.php';
		exit;
	} ?>
<!- image info layer (cimg) -->
<div id="infoDiv" class="hideit">
  <div>
    <label><?php echo $l->m('in_028'); ?>:</label>
    <span id="inf_cwidth"> </span>
  </div>
  <div>
    <label><?php echo $l->m('in_029'); ?>:</label>
    <span id="inf_cheight"> </span>
  </div>
  <div>
    <label><?php echo $l->m('in_030'); ?>:</label>
    <span id="inf_ctype"> </span>
  </div>
  <div>
    <label><?php echo $l->m('in_031'); ?>:</label>
    <span id="inf_csize"> </span>
  </div>
  <div>
    <label><?php echo $l->m('in_033'); ?>:</label>
    <span id="inf_ccdate"> </span>
  </div>
  <div>
    <label><?php echo $l->m('in_034'); ?>:</label>
    <span id="inf_cmdate"> </span>
  </div>
</div>
<form id="iBrowser" name="iBrowser" method="post" action="scripts/rfiles.php" enctype="multipart/form-data" target="inSelFrame">
  <input type="hidden" name="lang" value="<?php echo $l->lang; ?>" />
  <input type="hidden" id="param" name="param" value="" />
  <input type="hidden" id="flist" name="flist" value="<?php echo $cfg['list']; ?>" />
  <input type="hidden" id="cimg" name="cimg" value="" cfile="" cwidth="" cheight="" csize="" ctype="" ccdate="" cmdate="" />
  <div id="outerDivWrap">
    <div class="headerDiv">
      <h4><?php echo $l->m('im_002'); ?></h4>
      <div class="thickSeparator"></div>
    </div>
    <div class="brdPad">

      
      <!- MAIN CONTENT ------------------------------------------------------ -->
      <div id="mainDivWrap">
        
        <div class="brdPad">
          <div id="mainDiv">
            <!- INSERT/CHANGE ----------------------------------------------------- -->
            <div id="inDiv" class="showit">
              <!- select library ---------------------------------------------------- -->
              <div id="ilibsDiv" class="hideit">
                <div class="rowDiv">
                  <label for="ilibs"> <span class="title"> <?php echo $l->m('in_002'); ?> </span> </label>
                  <select class="fldlg" id="ilibs" name="ilibs" size="1" onChange="ilibsClick(this);">
                    <?php echo $lib_options; ?>
                  </select>
                </div>
              </div>
              <div class="floatWrap">
                <!- left column ------------------------------------------------------- -->
                <div class="colLeft">
                  <div style="float: left;">
                    <!- select image ------------------------------------------------------ -->
                    <div class="rowDiv">
                      <div class="btnRight">
                        <img id="alertImg" class="hideit" src="images/alert_off.gif" onClick="alert(this.alt);" onMouseOver="this.src='images/alert.gif';" onMouseOut="this.src='images/alert_off.gif';" alt="<?php echo $l->m('in_051'); ?>" title="<?php echo $l->m('in_051'); ?>" width="16" height="16" border="0" />
                      </div>
                      <label> <span class="title"> <?php echo $l->m('in_004'); ?> </span> </label>
                    </div>
                    <div class="rowDiv">
                      <div id="inSelDiv">
                        <iframe name="inSelFrame" id="inSelFrame" src="scripts/rfiles.php?clib=<?php echo $clib; ?>" style="width: 100%; height: 100%;" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe>
                      </div>
                    </div>
                  </div>
                </div>
                <!- // colLeft -->
                <!- right column ----------------------------------------------------- -->
                <div class="colRight">
                  <div style="float: left;">
                    <!- preview image ---------------------------------------------------- -->
                    <div class="rowDiv">
                      <label> <span class="title"> <?php echo $l->m('in_005'); ?> </span> </label>
                    </div>
                    <div class="rowDiv">
                      <div id="inPrevDiv">
                        <iframe name="inPrevFrame" id="inPrevFrame" src="images/noImg.gif" style="width: 100%; height: 100%;" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!- // colRight -->
           
              <!- file section ----------------------------------------------------- -->
              <div id="fileDivWrap" class="showit">
                <div class="rowDiv">
                  <div class="btnLeft">
				    <?php if ($cfg['create'] && isset($cfg['ilibs_inc'])) {; ?>
                    <img src="images/dir_off.gif" onClick="changeClass(0,'fileDiv','showit','fiDirDiv','showit','fiUplDiv','hideit','fiRenDiv','hideit','fiDelDiv','hideit');" onMouseOver="this.src='images/dir.gif';" onMouseOut="this.src='images/dir_off.gif';" alt="<?php echo $l->m('in_027'); ?>" title="<?php echo $l->m('in_027'); ?>" width="16" height="16" />
                    <?php }; ?>
                    <?php if ($cfg['upload']) {; ?>
                    <img src="images/upimg_off.gif" onClick="changeClass(1,'fileDiv','showit','fiDirDiv','hideit','fiUplDiv','showit','fiRenDiv','hideit','fiDelDiv','hideit');" onMouseOver="this.src='images/upimg.gif';" onMouseOut="this.src='images/upimg_off.gif';" alt="<?php echo $l->m('in_019'); ?>" title="<?php echo $l->m('in_019'); ?>" width="16" height="16" />
                    <?php }; ?>
                    <?php if ($cfg['rename']) {; ?>
                    <img class="isecbtn"src="images/renimg_off.gif" onClick="changeClass(0,'fileDiv','showit','fiDirDiv','hideit','fiRenDiv','showit','fiUplDiv','hideit','fiDelDiv','hideit');" onMouseOver="this.src='images/renimg.gif';" onMouseOut="this.src='images/renimg_off.gif';" alt="<?php echo $l->m('in_017'); ?>" title="<?php echo $l->m('in_017'); ?>" width="16" height="16" border="0" />
                    <?php }; ?>
                    <?php if ($cfg['delete']) {; ?>
                    <img src="images/delimg_off.gif" onClick="changeClass(0,'fileDiv','showit','fiDirDiv','hideit','fiDelDiv','showit','fiRenDiv','hideit','fiUplDiv','hideit');" onMouseOver="this.src='images/delimg.gif';" onMouseOut="this.src='images/delimg_off.gif';" alt="<?php echo $l->m('in_006'); ?>" title="<?php echo $l->m('in_006'); ?>" width="16" height="16" border="0" />
                    <?php }; ?>
                  </div>
                </div>
                <!- clear floats ------------------------------------------------------ -->
                <div class="clrFloatRight">
                </div>
                <div id="fileDiv" class="showit">
                  <?php if ($cfg['delete']) { ?>
                  <div id="fiDelDiv" class="hideit">
                    <div class="rowDiv">
                      
                      <label for="in_srcnew"> <span class="pad10"> <?php echo $l->m('in_024'); ?> </span> </label>
                      <input class="fldlg readonly" id="in_delinfo" name="in_delinfo" type="text" value="" disabled="true" readonly="true" />
                    </div>
                    <br /><br /> <input type="button" value="<?php echo $l->m('in_024'); ?>" class="btn" onClick="deleteClick();" style="margin-left: 105px;"/>
                  </div>
                  <?php }; ?>
                  <?php if ($cfg['rename']) { ?>
                  <div id="fiRenDiv" class="hideit">
                    <div class="rowDiv">
                      <label for="in_srcnew"> <span class="pad10"> <?php echo $l->m('in_016'); ?> </span> </label>
                      <input class="fldlg" id="in_srcnew" name="in_srcnew" type="text" value="" onKeyUp="RemoveInvalidChars(this, '[^A-Za-z0-9 \_-]'); ForceLowercase(this); CharacterReplace(this, ' ', '_'); return false;"  />
                    </div>
                    <br /><br /> <input type="button" value="<?php echo $l->m('in_016'); ?>" class="btn" onClick="renameClick();" style="margin-left: 105px;"/>
                  </div>
                  <?php }; ?>
                  <?php if ($cfg['create']) { ?>
                  <div id="fiDirDiv" class="hideit">
                    <div class="rowDiv">
                      <div class="btnRight">
                        <img onClick="createClick();" src="images/okclick_off.gif" onMouseOver="this.src='images/okclick.gif';" onMouseOut="this.src='images/okclick_off.gif';" alt="<?php echo $l->m('in_026'); ?>" title="<?php echo $l->m('in_026'); ?>" width="16" height="16" border="0" />
                      </div>
                      <label for="in_srcnew"> <span class="pad10"> <?php echo $l->m('in_025'); ?> </span> </label>
                      <input class="fldlg" id="in_dirnew" name="in_dirnew" type="text" value="" onKeyUp="RemoveInvalidChars(this, '[^A-Za-z0-9 \_-]'); ForceLowercase(this); CharacterReplace(this, ' ', '_'); return false;" />
                    </div>
                  </div>
                  <?php }; ?>
                  <?php if ($cfg['upload']) {; ?>
                  <div id="fiUplDiv" class="showit">
                    <div class="rowDiv">
                      <?php 
							$max = isset($cfg['umax']) && $cfg['umax'] >= 1 ? $cfg['umax'] : 1;					
							for($i=1; $i <= $max; $i++) {; ?>
                      <label for="nfile"> <span class="pad20"> <?php echo $l->m('in_018'); if ($max > 1){ echo ' (' . $i . ')';} ?> </span> </label>
                      <input name="nfile[]" type="file" class="fldlg" id="nfile[]" size="40" accept="image/*" />
                      <?php }; ?>
                    </div>
                    <div class="rowDiv">
                      <label for="chkThumbSize[]"> <span class="pad20"> <?php echo $l->m('in_020'); ?> </span> </label>
                      <div id="fmtDiv">
                        <?php echo thumbSizes($cfg['thumbs']); ?>
                      </div>
                    </div>
                  
					<?php  
						if ($cfg['captcha'] ){
							import('captcha.CaptchaManager');
							$captchaManager = new CaptchaManager();
							if ($captchaManager->isEnabled()) { 
								$captcha = $captchaManager->createCaptcha();
								
							?>
							<div class="rowDiv">
							<label for="captchaPic"> <span class="pad20"> Captcha </span> </label>
									<img src="<?php echo $init['captchaPath'] . $captcha->getId(); ?>"/><br />
							</div><br />
							<div class="rowDiv">
							<label for="captchaInput"> <span class="pad20"> <?php echo $l->m('im_101'); ?> </span> </label>
									<input name="captcha" id="captcha" value="" size="20" maxlength="32" class="textField" />
									<input type="hidden" name="cVal" id="cVal" value="<?php echo $captcha->getValue(); ?>" />
							</div>
					<?php } }; ?>
					<br /><br /> <input type="button" value="<?php echo $l->m('in_018'); ?>" class="btn" onClick="uploadClick();" style="margin-left: 105px;"/>
		           	</div>
                  <?php }; ?>
                </div>
              </div>
              <!- // fiDiv -->
            </div>
            <!- // inDiv -->
            <!- ATTRIBUTES -------------------------------------------------------- -->
            <div id="atDiv" class="hideit">
              <fieldset>
              <div class="rowDiv">
                <label for="pr_src"> <span class="title"> <?php echo $l->m('at_002'); ?> </span> </label>
                <input class="fldlg readonly" id="pr_src" name="pr_src" type="text" value="" disabled="true" readonly="true" />
              </div>
              <div class="rowDiv">
                <div class="btnRight">
                  <img onClick="selSymbol('pr_title');" src="images/symbols_off.gif" onMouseOver="this.src='images/symbols.gif';" onMouseOut="this.src='images/symbols_off.gif';" title="<?php echo $l->m('at_029'); ?>" alt="<?php echo $l->m('at_029'); ?>" width="16" height="16" border="0" /><img class="hlpBtn" src="images/help_off.gif" onMouseOver="this.src='images/help.gif';" onMouseOut="this.src='images/help_off.gif';" onClick="alert(this.alt);" alt="<?php echo $l->m('at_004'); ?>" title="<?php echo $l->m('at_004'); ?>" width="16" height="16" border="0" />
                </div>
                <label for="pr_title"> <span class="title"> <?php echo $l->m('at_003'); ?> </span> </label>
                <input class="fldmlg" id="pr_title" name="pr_title" type="text" value="" onChange="updateStyle()" />
              </div>
              <div class="rowDiv">
                <div class="btnRight">
                  <img onClick="selSymbol('pr_alt');" src="images/symbols_off.gif" onMouseOver="this.src='images/symbols.gif';" onMouseOut="this.src='images/symbols_off.gif';" title="<?php echo $l->m('at_030'); ?>" alt="<?php echo $l->m('at_030'); ?>" width="16" height="16" border="0" /><img class="hlpBtn" src="images/help_off.gif" onMouseOver="this.src='images/help.gif';" onMouseOut="this.src='images/help_off.gif';" onClick="alert(this.alt);" alt="<?php echo $l->m('at_006'); ?>" title="<?php echo $l->m('at_006'); ?>" width="16" height="16" border="0" />
                </div>
                <label for="pr_alt"> <span class="title"> <?php echo $l->m('at_005'); ?> </span> </label>
                <input class="fldmlg" id="pr_alt" name="pr_alt" type="text" value="" onChange="updateStyle()" />
              </div>
              </fieldset>
              <div class="floatWrap">
                <!- left column ------------------------------------------------------ -->
                <div class="colLeft">
                  <div class="rowDiv">
                    <label> <span class="title"> <?php echo $l->m('at_007'); ?> </span> </label>
                  </div>
                  <div class="rowDiv">
                    <div class="btnRight">
                      <img class="hlpBtn" src="images/help_off.gif" onMouseOver="this.src='images/help.gif';" onMouseOut="this.src='images/help_off.gif';" onClick="alert(this.alt);" alt="<?php echo $l->m('at_008'); ?>" title="<?php echo $l->m('at_008'); ?>" width="16" height="16" border="0" />
                    </div>
                    <label for="pr_class"> <span class="pad10"> <?php echo $l->m('at_009'); ?> </span> </label>
                    <select class="fldm" id="pr_class" name="pr_class" onChange="updateStyle()">
                      <option value="default" selected="selected"><?php echo $l->m('at_099'); ?></option>
                      <?php echo getStyles(false); ?>
                    </select>
                  </div>
                  <div class="rowDiv">
                    <div class="btnRight">
                      <img class="hlpBtn" src="images/help_off.gif" onMouseOver="this.src='images/help.gif';" onMouseOut="this.src='images/help_off.gif';" onClick="alert(this.alt);" title="<?php echo $l->m('at_011'); ?>" alt="<?php echo $l->m('at_011'); ?>" width="16" height="16" border="0" />
                    </div>
                    <label> <span class="title"> <?php echo $l->m('at_010'); ?> </span> </label>
                  </div>
                  <div class="rowDiv">
                    <label for="pr_align"> <span class="pad10"> <?php echo $l->m('at_012'); ?> </span> </label>
                    <select class="fldm" id="pr_align" name="pr_align" onChange="updateStyle()">
                      <option value=""><?php echo $l->m('at_013'); ?></option>
                      <option value="left"><?php echo $l->m('at_014'); ?></option>
                      <option value="right"><?php echo $l->m('at_015'); ?></option>
                      <option value="top"><?php echo $l->m('at_016'); ?></option>
                      <option value="middle"><?php echo $l->m('at_017'); ?></option>
                      <option value="bottom"><?php echo $l->m('at_018'); ?></option>
                    </select>
                  </div>
                  <div class="rowDiv">
                    <label for="pr_size"> <span class="pad10"> <?php echo $l->m('at_022'); ?> </span> </label>
                    <input class="fldsm readonly" id="pr_size" name="pr_size" type="text"value="" maxlength="8" disabled="true" readonly="true" />
                    <span class="frmText">(<span id="pr_sizeUnit"></span>)</span>
                  </div>
                  <div class="rowDiv">
                    <?php if ($cfg['attrib'] == true) {; ?>
                    <div class="btnRight">
                      <img src="images/img_size_off.gif" onMouseOver="this.src='images/img_size.gif';" onMouseOut="this.src='images/img_size_off.gif';" onClick="resetDim();" alt="<?php echo $l->m('at_031'); ?>" title="<?php echo $l->m('at_031'); ?>" width="16" height="16" border="0" />
                    </div>
                    <?php }; ?>
                    <label for="pr_width"> <span class="pad10"> <?php echo $l->m('at_023'); ?> </span> </label>
                    <input id="pr_width" name="pr_width" type="text"value="" maxlength="4" <?php if ($cfg['attrib'] != true) {; ?> class="fldsm readonly" disabled="true" readonly="true" <?php } else {; ?> class="fldsm" onchange="changeDim(0);" onkeyup="RemoveInvalidChars(this, '[^0-9]');" <?php }; ?> />
                    <span class="frmText"> (px) </span>
                  </div>
                  <div class="rowDiv">
                    <label for="pr_height"> <span class="pad10"> <?php echo $l->m('at_024'); ?> </span> </label>
                    <input id="pr_height" name="pr_height" type="text"value="" maxlength="4" <?php if ($cfg['attrib'] != true) {; ?> class="fldsm readonly" disabled="true" readonly="true" <?php } else {; ?> class="fldsm" onchange="changeDim(1);" onkeyup="RemoveInvalidChars(this, '[^0-9]');" <?php }; ?> />
                    <span class="frmText"> (px) </span>
                  </div>
                  <div class="rowDiv">
                    <label for="pr_border"> <span class="pad10"> <?php echo $l->m('at_025'); ?> </span> </label>
                    <input class="fldsm" id="pr_border" name="pr_border" type="text"value="" maxlength="2" onChange="updateStyle();" onKeyUp="RemoveInvalidChars(this, '[^0-9]');"  />
                    <span class="frmText"> (px) </span>
                  </div>
                  <div class="rowDiv">
                    <label for="pr_vspace"> <span class="pad10"> <?php echo $l->m('at_026'); ?> </span> </label>
                    <input class="fldsm" id="pr_vspace" name="pr_vspace" type="text" value="" maxlength="2" onChange="updateStyle();" onKeyUp="RemoveInvalidChars(this, '[^0-9]');" />
                    <span class="frmText"> (px) </span>
                  </div>
                  <div class="rowDiv">
                    <label for="pr_hspace"> <span class="pad10"> <?php echo $l->m('at_027'); ?> </span> </label>
                    <input class="fldsm" id="pr_hspace" name="pr_hspace" type="text" value="" maxlength="2" onChange="updateStyle();" onKeyUp="RemoveInvalidChars(this, '[^0-9]');" />
                    <span class="frmText"> (px) </span>
                  </div>
                </div>
                <!- // colLeft -->
                <!- right column ----------------------------------------------------- -->
                <div class="colRight">
                  <div style="float: left;">
                    <div class="rowDiv">
                      <label> <span class="title"> <?php echo $l->m('at_028'); ?> </span> </label>
                    </div>
                    <div class="rowDiv">
                      <div id="atPrevDiv">
                        <p><img id="atPrevImg" src="images/textflow.gif" width="45" height="45" alt="" title="" hspace="" vspace="" border="" class="" />Lorem ipsum, Dolor sit amet, consectetuer adipiscing loreum ipsum edipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.Loreum ipsum edipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exercitation ullamcorper suscipit. Lorem ipsum, Dolor sit amet, consectetuer adipiscing loreum ipsum edipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
                      </div>
                    </div>
                  </div>
                </div>
                <!- // colRight -->
                <div class="rowDiv">
                  <div class="btnRight">
                    <img class="hlpBtn" src="images/help_off.gif" onMouseOver="this.src='images/help.gif';" onMouseOut="this.src='images/help_off.gif';" onClick="alert(this.alt);" alt="<?php echo $l->m('at_033'); ?>" title="<?php echo $l->m('at_033'); ?>" width="16" height="16" border="0" />
                  </div>
                  <label for="pr_chkCaption"> <span class="title"> <?php echo $l->m('at_032'); ?> </span> </label>
                  <input name="pr_chkCaption" type="checkbox" class="chkBox" id="pr_chkCaption" onChange="updateStyle()" value="1" />
                  <span class="frmText">(<?php echo $l->m('at_034'); ?>)</span>
                </div>
                <div class="rowDiv">
                  <div class="btnRight">
                    <img class="hlpBtn" src="images/help_off.gif" onMouseOver="this.src='images/help.gif';" onMouseOut="this.src='images/help_off.gif';" onClick="alert(this.alt);" alt="<?php echo $l->m('at_008'); ?>" title="<?php echo $l->m('at_008'); ?>" width="16" height="16" border="0" />
                  </div>
                  <label for="pr_captionClass"> <span class="pad10"> <?php echo $l->m('at_009'); ?> </span> </label>
                  <select class="fldm" id="pr_captionClass" name="pr_captionClass" onChange="updateStyle()">
                    <option value="default" selected="selected"><?php echo $l->m('at_099'); ?></option>
                    <?php echo getStyles(true); ?>
                  </select>
                </div>
              </div>
            </div>
            <!- // atDiv -->
          </div>
        </div>
      </div>
      <!- // mainDivWrap -->
      <!- footer ----------------------------------------------------------- -->
      <div id="ftDivWrap">
        <div id="ftDiv">
          <input type="button" value="<?php echo $l->m('im_005'); ?>" class="btn" onClick="insertImage();" />
          <span class="pad5">
          <input type="button" value="<?php echo $l->m('im_006'); ?>" class="btn" onClick="top.window.close();" />
          </span>
        </div>
        <br />
        <div id="attribDiv">
        	<p class="attrib"><?php echo $l->m('im_100'); ?> <a href="http://net4visions.com" target="_blank">iBrowser</a></p>
        </div>
      </div>
      <!- // ftDivWrap -->
    </div>
  </div>
  <!- // outerDivWrap -->
</form>
</body>
</html><?php
// ============================================================
// = create library list V 1.0, date: 05/10/2005              =
// ============================================================
	function liboptions($arr, $prefix = '', $sel = '') {
  		$retval = '';
  		foreach($arr as $lib) {			
    		$retval .= '<option value="' . absPath($lib['value']) . '"' . (($lib['value'] == $sel) ? ' selected="selected"' : '') . '>' . $prefix . $lib['text'] . '</option>' . "\n";
  		}
  		return $retval;
	}
// ============================================================
// = create thumb sizes V 1.0, date: 05/23/2005               =
// ============================================================
	function thumbSizes($arr, $sel = '') {
  		global $l;
		$retval = '';
  		foreach($arr as $key => $thumb) {			
			$retval .= '<div>' . '<input id="chkThumbSize[' . $key . ']" name="chkThumbSize[' . $key . ']" class="chkBox" type="checkbox" value="' . $key . '"' . (($key == 0) ? ' checked="checked"' : '') . ' />' . '<span class="frmText">' . (($thumb['size'] == '*') ? $l->m('in_022') . '&nbsp;'  : $thumb['size'] . ' px' ) . '</span>' . (($thumb['crop'] == true) ? '<img src="images/thbCrop.gif" align="absmiddle" width="10px" height="10px" alt="' . $l->m('in_023') . '" title="' . $l->m('in_023') . '" />' : '') . '</div>' . "\n";
		}
  		return $retval;
	}
// ============================================================
// = abs path - add slashes V 1.0, date: 05/10/2005           =
// ============================================================
	function absPath($path) {		
		if (substr($path,-1)  != '/') $path .= '/';
		if (substr($path,0,1) != '/') $path  = '/' . $path;
		return $path;
	}
// ============================================================
// = css styles V 1.0, date: 08/03/2005                       =
// ============================================================
	function getStyles($cap) {
		$styles = '';
		global $cfg;
		foreach ($cfg['style'] as $key => $value) {
			$pos = strrpos($key,'capDiv'); // is caption style
			if ($cap == false && $pos === false) {
					$styles .= '<option value="'. $key . '">' . $value . '</option>';
			} elseif ($cap == true && $pos !== false) {
					$styles .= '<option value="'. $key . '">' . $value . '</option>';
			}
		}
		return $styles;
	}
//-------------------------------------------------------------------------
?>
