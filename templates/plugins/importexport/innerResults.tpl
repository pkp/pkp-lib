{**
 * plugins/importexport/native/templates/innerResults.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Result of operations this plugin performed
 *}
{if $key == 'warnings'}
	{assign var=translateTitleKey value="plugins.importexport.common.warningsEncountered"}
{elseif $key == 'errors'}
	 {assign var=translateTitleKey value="plugins.importexport.common.errorsOccured"}
{/if}

{if array_key_exists($key, $errorsAndWarnings) && $errorsAndWarnings.$key|@count > 0}
	<h2>{translate key=$translateTitleKey}</h2>
	{foreach from=$errorsAndWarnings.$key item=allRelatedTypes key=relatedTypeName}
		{foreach from=$allRelatedTypes item=thisTypeIds key=thisTypeId}
			{if $thisTypeIds|@count > 0}
				<p>{$relatedTypeName|escape} {if $thisTypeId > 0} {translate key='plugins.importexport.common.id' id=$thisTypeId} {/if}</p>
				<ul>
					{foreach from=$thisTypeIds item=idRelatedItems}
						{foreach from=$idRelatedItems item=relatedItemMessage}
							<li>{$relatedItemMessage|escape}</li>
						{/foreach}
					{/foreach}
				</ul>
			{/if}
		{/foreach}
	{/foreach}
{/if}
