{**
 * controllers/fileUploadContainer.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Markup for file uploader widget.
 *}

<div id="{$id}">
	{translate|assign:"warningMessage" key="common.fileUploaderError"}
	{translate|assign:"warningTitle" key="common.warning"}
	{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=$id
		notificationStyleClass="notifyWarning" notificationContents=$warningMessage notificationTitle=$warningTitle}
</div>
