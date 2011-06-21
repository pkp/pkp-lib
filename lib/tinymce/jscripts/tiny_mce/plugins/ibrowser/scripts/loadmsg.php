<?php
	// ================================================
	// PHP image browser - iBrowser
	// ================================================
	// iBrowser dialog - load message
	// ================================================
	// Developed: net4visions.com
	// Copyright: net4visions.com
	// License: GPL - see readme.txt
	// (c)2005 All rights reserved.
	// ================================================
	// Revision: 1.0                   Date: 07/15/2006
	// ================================================

?>
<?php
	//-------------------------------------------------------------------------
	// include configuration settings
	if (!$l) {
		include dirname(__FILE__) . '/../config/config.inc.php';
		include dirname(__FILE__) . '/../langs/lang.class.php';
		// language settings
		$langs = array('cs','da','de','en','es','fr','it','nl','pl','pt_br','sk');
		if(isset($_REQUEST['lang']) && !in_array($_REQUEST['lang'], $langs)) die('invalid language set');
		$l = (isset($_REQUEST['lang']) ? new PLUG_Lang($_REQUEST['lang']) : new PLUG_Lang($cfg['lang']));
		$l->setBlock('ibrowser');
	}
?>
<div align="center" id="dialogLoadMessage" style="display: block;">
  <table width="100%" height="90%">
    <tr>
      <td align="center" valign="middle"><div id="loadMessage"><?php echo $l->m('im_097'); ?></div></td>
    </tr>
  </table>
</div>