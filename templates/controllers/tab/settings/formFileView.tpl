{**
 * controllers/tab/settings/formImageView.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form file view.
 *
 *}

<div class="inline">
	{translate key="common.fileName"}: <a href="{$publicFilesDir}/{$file.uploadName|escape:"url"}?{$file.dateUploaded}" class="file">{$file.name|escape}</a>
	{$file.dateUploaded|date_format:$datetimeFormatShort}
</div>
<div id="{$deleteLinkAction->getId()}" class="pkp_linkActions inline">
	{include file="linkAction/linkAction.tpl" action=$deleteLinkAction contextId=$fileSettingName}
</div>
