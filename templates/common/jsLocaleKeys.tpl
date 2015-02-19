{**
 * templates/common/jsLocaleKeys.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Default Locale keys used by JavaScript.  May be overridden by the calling template
 *}

{* List constants for JavaScript in $.pkp.locale namespace *}
<script type="text/javascript">
	jQuery.pkp = jQuery.pkp || {ldelim} {rdelim};
	jQuery.pkp.locale = {ldelim} {rdelim};
	{foreach from=$jsLocaleKeys item=keyName}
		{translate|assign:"keyValue" key=$keyName}
		{* replace periods in the key name with underscores to prevent JS complaints about undefined variables *}
		jQuery.pkp.locale.{$keyName|replace:'.':'_'|escape:"javascript"} = {if is_numeric($keyValue)}{$keyValue}{else}'{$keyValue|escape:"javascript"}'{/if};
	{/foreach}
</script>
