{**
 * templates/controllers/grid/gridCellContents.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * a regular grid cell's contents
 *}

{* Preserve the value in case it's used externally *}
{assign var=_label value=$label}

{* Prepare multilingual content for display *}
{if $column->hasFlag('multilingual')}
	{* Multilingual column *}
	{if isset($_label.$currentLocale)}
		{assign var=_label value=$_label.$currentLocale}
	{else}
		{assign var=_primaryLocale value=AppLocale::getPrimaryLocale()}
		{assign var=_label value=$_label.$_primaryLocale}
	{/if}
{/if}

{* Handle escaping as needed *}
{if $column->hasFlag('html')}
	{* Limited HTML is allowed *}
	{assign var=_label value=$_label|strip_unsafe_html}
{else}
	{* No HTML allowed; escape the label. *}
	{assign var=_label value=$_label|escape}
{/if}

{if $_label != ''}
	<span{if count($actions) gt 0} class="pkp_helpers_align_left gridLabelBeforeActions"{/if}>
		{if $column->hasFlag('maxLength')}
			{assign var="maxLength" value=$column->getFlag('maxLength')}
			{$_label|truncate:$maxLength}
		{else}
			{$_label}
		{/if}
	</span>
{/if}

{if count($actions) gt 0}
	{foreach from=$actions item=action}
		{if is_a($action, 'LegacyLinkAction')}
			{include file="linkAction/legacyLinkAction.tpl" id=$cellId|concat:"-action-":$action->getId() action=$action objectId=$cellId}
		{else}
			{include file="linkAction/linkAction.tpl" action=$action contextId=$cellId}
		{/if}
	{/foreach}
{/if}
