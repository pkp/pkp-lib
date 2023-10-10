{**
 * plugins/importexport/native/templates/resultsImport.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Result of operations this plugin performed
 *}
{if $errorsFound}
	{translate key="plugins.importexport.native.processFailed"}
{else}
	{translate key="plugins.importexport.native.importComplete"}
	<ul>
		{foreach from=$importedRootObjects item=contentItemArrays key=contentItemName}
			<b>{$contentItemName|escape}</b>
			{foreach from=$contentItemArrays item=contentItemArray}
				{foreach from=$contentItemArray item=contentItem}
					<li>
						{$contentItem->getUIDisplayString()|escape}
					</li>
				{/foreach}
			{/foreach}
		{/foreach}
	</ul>
{/if}

{include file='core:plugins/importexport/innerResults.tpl' key='warnings' errorsAndWarnings=$errorsAndWarnings}
{include file='core:plugins/importexport/innerResults.tpl' key='errors' errorsAndWarnings=$errorsAndWarnings}

{if $validationErrors}
	<h2>{translate key="plugins.importexport.common.validationErrors"}</h2>
	<ul>
		{foreach from=$validationErrors item=validationError}
			<li>{$validationError->message|escape}</li>
		{/foreach}
	</ul>
{/if}
