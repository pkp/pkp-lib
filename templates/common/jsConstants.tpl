{**
 * templates/common/jsConstants.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Expose constants to JavaScript. See the define_exposed function in PHP.
 *}

{* List constants for JavaScript in $.pkp.cons namespace *}
<script>
	jQuery.pkp = jQuery.pkp || {ldelim} {rdelim};
	jQuery.pkp.cons = {ldelim} {rdelim};
	{foreach from=$exposedConstants key=key item=value}
	jQuery.pkp.cons.{$key|escape:"javascript"} = {if is_numeric($value)}{$value}{else}'{$value|escape:"javascript"}'{/if};
	{/foreach}
</script>
