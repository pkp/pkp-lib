{**
 * templates/notification/unsubscribeNotificationsForm.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Unsubscribe Notifications Form
 *
 *}
{include file="frontend/components/header.tpl" pageTitle="notification.unsubscribeNotifications"}

<div class="page page_unsubscribe_notifications">
	{capture assign="profileNotificationUrl"}<a href="{url page="user" op="profile"}">{translate key="notification.notifications"}</a>{/capture}
	{translate key="notification.unsubscribeNotifications.pageMessage" profileNotificationUrl=$profileNotificationUrl contextName=$contextName username=$username}
</div>

<form class="pkp_form" id="unsubscribeNotificationForm" method="post" action="{url router=$smarty.const.ROUTE_PAGE page="notification" op="unsubscribe"}">
	{csrf}

	<input type="hidden" name="validate" value="{$validationToken|escape}" />
	<input type="hidden" name="id" value="{$notificationId|escape}" />

	{foreach from=$emailSettings key=$emailKey item=$emailSetting}
		{assign var="emailSettingName" value=$emailSetting.emailSettingName}
		{capture assign="settingKey"}{translate key=$emailSetting.settingKey title="common.title"|translate}{/capture}

		{fbvFormSection title=$settingKey list=true translate=false}
			{fbvElement type="checkbox" id=$emailSettingName checked=1 label="notification.email"}
		{/fbvFormSection}
	{/foreach}

	{fbvFormButtons id="unsubscribeNotificationSave" hideCancel=true submitText="common.save"}
</form>

{include file="frontend/components/footer.tpl"}
