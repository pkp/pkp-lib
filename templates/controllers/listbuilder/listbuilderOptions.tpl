{**
 * templates/controllers/listbuilder/listbuilderOptions.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Listbuilder java script handler options.
 *}


gridId: '{$grid->getId()|escape:javascript}',
fetchRowUrl: '{url|escape:javascript op='fetchRow' params=$gridRequestArgs escape=false}',
fetchOptionsUrl: '{url|escape:javascript op='fetchOptions' params=$gridRequestArgs escape=false}',
{if $grid->getSaveType() == $smarty.const.LISTBUILDER_SAVE_TYPE_INTERNAL}
	saveUrl: '{url|escape:javascript op='save' params=$gridRequestArgs escape=false}',
	saveFieldName: null,
{else}{* LISTBUILDER_SAVE_TYPE_EXTERNAL *}
	saveUrl: null,
	saveFieldName: '{$grid->getSaveFieldName()|escape:javascript}',
{/if}
sourceType: '{$grid->getSourceType()|escape:javascript}',
bodySelector: '#{$gridActOnId|escape:javascript}',
features: {include file='controllers/grid/feature/featuresOptions.tpl' features=$features},
