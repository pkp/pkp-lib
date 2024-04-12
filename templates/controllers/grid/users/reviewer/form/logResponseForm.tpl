{**
 * templates/logResponseForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form for logging a reviewer's response.
 *}
<script type="text/javascript">
    $(function() {ldelim}
        // Attach the form handler.
        $('#logResponseForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
        {rdelim});
</script>

<form class="pkp_form" id="logResponseForm" method="post" action="{url op="addLog" params=$requestArgs}">
    {csrf}
    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="logResponseFormNotification"}
    {fbvFormArea id="slideFormArea" class="border"}
    {fbvFormSection description="editor.review.logResponse.form.description" list=true}
    {fbvElement type="radio" name="logResponse" id="lr-accepted" label="editor.review.logResponse.form.option.accepted" value="1"}
    {fbvElement type="radio" name="logResponse" id="lr-declined" label="editor.review.logResponse.form.option.declined" value="0"}
    {/fbvFormSection}
    {/fbvFormArea}
    {fbvFormSection class="formButtons"}
    {fbvFormButtons id="closeButton" hideCancel=false submitText="common.confirm"}
    {/fbvFormSection}
</form>
