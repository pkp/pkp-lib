{**
 * formErrors.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List errors that occurred during form processing.
 *
 *}
{if $isError}
<div id="formErrors">
	<p>
	<span class="formError">{translate key="form.errorsOccurred"}:</span>
	<ul class="formErrorList">
	{foreach key=field item=message from=$errors}
		<li><a href="#{$field|escape}">{$message}</a></li>
	{/foreach}
	</ul>
	</p>
</div>
<script type="text/javascript">
{literal}
<!--
// Jump to form errors.
window.location.hash="formErrors";
// -->
{/literal}
</script>
{/if}
