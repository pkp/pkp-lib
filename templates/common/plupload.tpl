{**
 * templates/common/plupload.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Components of the header related to plupload
 *}
	<script src="{$baseUrl}/lib/pkp/js/lib/plupload/js/plupload.full.js"></script>
	<script src="{$baseUrl}/lib/pkp/js/lib/plupload/js/jquery.ui.plupload/jquery.ui.plupload.js"></script>

	{if in_array($currentLocale, array('fr_CA', 'pt_BR'))}
		{* Locale files of the form aa-bb.js *}
		<script src="{$baseUrl}/lib/pkp/js/lib/plupload/src/javascript/i18n/{$currentLocale|regex_replace:"/(.*)_(.*)/":"\\1-\\2"|strtolower}.js"></script>
	{elseif in_array(substr($currentLocale,0,2), array('cs', 'da', 'de', 'el', 'es', 'et', 'fa', 'fi', 'fr', 'hr', 'hu', 'it', 'ja', 'ko', 'lv', 'nl', 'pl', 'ro', 'ru', 'sk', 'sr', 'sv'))}
		{* Locale files of the form aa.js *}
		<script src="{$baseUrl}/lib/pkp/js/lib/plupload/src/javascript/i18n/{$currentLocale|substr:0:2}.js"></script>
	{/if}
