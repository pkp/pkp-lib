{**
 * templates/admin/editContext.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display the form to add or edit a context
 *}
<div id="editContext">
	{if $isAddingNewContext}
	<add-context-form
	{else}
	<pkp-form
	{/if}
		v-bind="forms.{$smarty.const.FORM_CONTEXT}"
		@set-fields="setFormFields"
		@set-errors="setFormErrors"
		@set-visible-locales="setFormVisibleLocales"
	/>
</div>
<script type="text/javascript">
	pkp.registry.init('editContext', 'Container', {$containerData|json_encode});
</script>
