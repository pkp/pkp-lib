{**
 * templates/controllers/grid/settings/genre/form/genreForm.tpl
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Genre form under press management.
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#genreForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="genreForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.genre.GenreGridHandler" op="updateGenre"}">
{include file="controllers/notification/inPlaceNotification.tpl" notificationId="genreFormNotification"}

{fbvFormArea id="genreInfo"}
{fbvFormSection title="common.name" for="name" required="true"}
	{fbvElement type="text" multilingual="true" id="name" value=$name maxlength="80"}
{/fbvFormSection}
{fbvFormSection title="common.designation" for="designation" required="true"}
	{fbvElement type="text" id="designation" value=$designation maxlength="80"}
{/fbvFormSection}
{fbvFormSection list="true" title="manager.setup.sortableByComponent"}
	{fbvElement type="checkbox" id="sortable" checked=$sortable label="manager.setup.genres.sortable"}
{/fbvFormSection}
{fbvFormSection list="true" title="manager.setup.dependentGenre"}
	{fbvElement type="checkbox" id="dependent" checked=$dependent label="manager.setup.genres.dependent"}
{/fbvFormSection}
{fbvFormSection title="manager.setup.groupType" for="category"}
	{fbvElement type="select" id="category" from=$submissionFileCategories selected=$category translate=false}
{/fbvFormSection}
{/fbvFormArea}

{if $gridId}
	<input type="hidden" name="gridId" value="{$gridId|escape}" />
{/if}
{if $rowId}
	<input type="hidden" name="rowId" value="{$rowId|escape}" />
{/if}
{if $genreId}
	<input type="hidden" name="genreId" value="{$genreId|escape}" />
{/if}
{fbvFormButtons submitText="common.save"}
</form>

