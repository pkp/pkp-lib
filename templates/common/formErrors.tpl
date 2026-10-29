{**
 * lib/pkp/templates/common/formErrors.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * List errors that occurred during form processing.
 *}
{if $isError}
	<div id="formErrors" tabindex="-1" role="alert" aria-labelledby="formErrorsLabel">
		<span id="formErrorsLabel" class="pkp_form_error">{translate key="form.errorsOccurred"}:</span>
		<ul class="pkp_form_error_list">
		{foreach key=field item=message from=$errors}
			<li id="formError-{$field|escape}"><a href="#{$field|escape}">{$message}</a></li>
		{/foreach}
		</ul>
	</div>
	<script>{literal}
		<!--
		// Move focus to form errors so assistive technology users land
		// where the validation feedback is displayed.
		window.location.hash="formErrors";
		document.getElementById("formErrors").focus();
		// -->
	{/literal}</script>
{/if}
