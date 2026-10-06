{**
 * templates/user/notificationSettingsForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User profile form.
 *}
<script>
    $(function() {ldelim}
        $('#notificationSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<style>
    /* Strip OJS default list formatting from checkboxes inside this table */
    #notificationSettingsForm .pkpTable ul, 
    #notificationSettingsForm .pkpTable li {
        list-style: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: none !important;
    }
</style>

<div class="semantic-defaults">
    <form class="pkp_form" id="notificationSettingsForm" method="post" action="{url op="saveNotificationSettings"}" enctype="multipart/form-data">
        <p>{translate key="notification.settingsDescription"}</p>

        {csrf}

        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="notificationSettingsFormNotification"}

        {fbvFormArea id="notificationSettings"}
            
            <table class="pkpTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 60%;">{translate key="common.title"}</th>
                        <th style="width: 20%; text-align: center;">{translate key="notification.allow"}</th>
                        <th style="width: 20%; text-align: center;">Enable these types of emails.</th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$notificationSettingCategories item=notificationSettingCategory}
                    
                    {* --- Category Header Row --- *}
                    <tr>
                        <td colspan="3" style="padding-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #ccc;">
                            <h4 style="margin: 0;">{translate key=$notificationSettingCategory.categoryKey}</h4>
                        </td>
                    </tr>
                    
                    {* --- Individual Notification Rows --- *}
                    {foreach from=$notificationSettingCategory.settings item=settingId}
                        {assign var="settingName" value=$notificationSettings.$settingId.settingName}
                        {assign var="emailSettingName" value=$notificationSettings.$settingId.emailSettingName}
                        {capture assign="settingKey"}{translate key=$notificationSettings.$settingId.settingKey title="common.title"|translate}{/capture}

                        <tr>
                            <td style="vertical-align: middle;">{$settingKey}</td>
                            
                            <td style="text-align: center; vertical-align: middle;">
                                {if $settingId|in_array:$blockedNotifications}
                                    {assign var="checked" value="0"}
                                {else}
                                    {assign var="checked" value="1"}
                                {/if}
                                {fbvElement type="checkbox" id=$settingName checked=$checked}
                            </td>
                            
                            <td style="text-align: center; vertical-align: middle;">
                                {if $settingId|in_array:$emailSettings}
                                    {assign var="emailChecked" value="0"}
                                {else}
                                    {assign var="emailChecked" value="1"}
                                {/if}
                                {fbvElement type="checkbox" id=$emailSettingName checked=$emailChecked}
                            </td>
                        </tr>
                    {/foreach}
                {/foreach}
                </tbody>
            </table>

            <p style="margin-top: 30px;">
                {capture assign="privacyUrl"}{url router=\PKP\core\PKPApplication::ROUTE_PAGE page="about" op="privacy"}{/capture}
                {translate key="user.privacyLink" privacyUrl=$privacyUrl}
            </p>

            <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
            {fbvFormButtons hideCancel=true submitText="common.save"}
        {/fbvFormArea}
    </form>
</div>

