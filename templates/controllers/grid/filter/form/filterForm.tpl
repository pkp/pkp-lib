{**
 * filterForm.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Filter grid form
 *}

{assign var=uid value="-"|uniqid}
<div id="editFilterFormContainer{$uid}">
	{if $noMoreTemplates}
		{literal}<script type='text/javascript'>
			<!--
			$(function() {
				// Hide the OK button.
				$('.ui-dialog:has(#editFilterFormContainer{/literal}{$uid}{literal}) :button:first')
						.hide()
			});
			// -->
		</script>{/literal}
		<p>{translate key='manager.setup.filter.noMoreTemplates'}</p>
	{else}
		<form class="pkp_form" id="editFilterForm" method="post" action="{url op="updateFilter"}" >
			<h3>{translate key=$formTitle}</h3>

			<p>{translate key=$formDescription filterDisplayName=$filterDisplayName}</p>

			{include file="common/formErrors.tpl"}

			{if $filterTemplates}
				{* Template selection *}
				{fbvElement type="select" id="filterTemplateSelect"|concat:$uid name="filterTemplateId"
						from=$filterTemplates translate=false defaultValue="-1" defaultLabel="manager.setup.filter.pleaseSelect"|translate}
				{literal}<script type='text/javascript'>
					<!--
					$(function() {
						// Hide the OK button as long as we
						// don't have a filter selected.
						$('.ui-dialog:has(#editFilterFormContainer{/literal}{$uid}{literal}) :button:first')
								.hide()

						ajaxAction(
							'post',
							'#editFilterFormContainer{/literal}{$uid}{literal}',
							'#filterTemplateSelect{/literal}{$uid}{literal}',
							'{/literal}{url op="editFilter"}{literal}',
							undefined,
							'change'
						);
					});
					// -->
				</script>{/literal}
			{else}
				{literal}<script type='text/javascript'>
					<!--
					$(function() {
						// Switch the OK button back on.
						$('.ui-dialog:has(#editFilterFormContainer{/literal}{$uid}{literal}) :button:first')
								.show()
					});
					// -->
				</script>{/literal}

				{assign var=hasRequiredField value=false}
				<table>
					{foreach from=$filterSettings item=filterSetting}
						{if $filterSetting->getRequired() == $smarty.const.FORM_VALIDATOR_REQUIRED_VALUE}
							{assign var=filterSettingRequired value='1'}
							{assign var=hasRequiredField value=true}
						{else}
							{assign var=filterSettingRequired value=''}
						{/if}
						<tr valign="top">
							<td class="label">{fieldLabel name=$filterSetting->getName() key=$filterSetting->getDisplayName() required=$filterSettingRequired}</td>
							{capture assign=settingValueVar}{ldelim}${$filterSetting->getName()}{rdelim}{/capture}
							{eval|assign:"settingValue" var=$settingValueVar}
							<td class="value">
								{if $filterSetting|is_a:SetFilterSetting}
									{fbvElement type="select" id=$filterSetting->getName() name=$filterSetting->getName()
											from=$filterSetting->getLocalizedAcceptedValues() selected=$settingValue translate=false}
								{elseif $filterSetting|is_a:BooleanFilterSetting}
									{fbvElement type="checkbox" id=$filterSetting->getName() name=$filterSetting->getName()
											checked=$settingValue}
								{else}
									{fbvElement type="text" id=$filterSetting->getName() name=$filterSetting->getName()
											size=$fbvStyles.size.LARGE maxlength=250 value=$settingValue}
								{/if}
							</td>
						</tr>
					{/foreach}
				</table>
				{if $hasRequiredField}<p><span class="formRequired">{translate key="common.requiredField"}</span></p>{/if}

				{if $filterId}<input type="hidden" name="filterId" value="{$filterId|escape}" />{/if}
				{if $filterTemplateId}<input type="hidden" name="filterTemplateId" value="{$filterTemplateId|escape}" />{/if}
			{/if}
		</form>
	{/if}
</div>

