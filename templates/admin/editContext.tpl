{**
 * templates/admin/editContext.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
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
		v-bind="components.{$smarty.const.FORM_CONTEXT}"
		@set="set"
	/>
</div>
<script type="text/javascript">
	pkp.registry.init('editContext', 'SettingsContainer', {$containerData|json_encode});
</script>
