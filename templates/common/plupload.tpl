{**
 * templates/common/plupload.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Components of the header related to plupload
 *}
<script src="{$baseUrl}/lib/pkp/lib/vendor/moxiecode/plupload/js/plupload.full.min.js"></script>
<script src="{$baseUrl}/lib/pkp/lib/vendor/moxiecode/plupload/js/jquery.ui.plupload/jquery.ui.plupload.js"></script>

{if in_array($currentLocale, array('pt_BR'))}
	{* Locale files of the form aa-bb.js *}
	<script src="{$baseUrl}/lib/pkp/lib/vendor/moxiecode/plupload/js/i18n/{$currentLocale|regex_replace:"/(.*)_(.*)/":"\\1-\\2"|strtolower}.js"></script>
{elseif in_array(substr($currentLocale,0,2), array('ar', 'da', 'et', 'hr', 'ja', 'lt', 'pl', 'sq', 'tr', 'az', 'de', 'fa', 'hu', 'ka', 'lv', 'sr', 'bs', 'el', 'fi', 'hy', 'kk', 'mn', 'ro', 'cs', 'en', 'fr', 'id', 'km', 'ms', 'ru', 'sv', 'cy', 'es', 'he', 'it', 'ko', 'nl', 'sk'))}
	{* Locale files of the form aa.js *}
	<script src="{$baseUrl}/lib/pkp/lib/vendor/moxiecode/plupload/js/i18n/{$currentLocale|substr:0:2}.js"></script>
{/if}
