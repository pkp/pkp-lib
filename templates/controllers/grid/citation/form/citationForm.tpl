
{**
 * sponsors.tpl
 *
 * Copyright (c) 2003-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Sponsors grid form
 *
 * $Id$
 *}
{**FIXME: fix URL action to use new Request URL method **}
<form name="editSponsorForm" id="editSponsorForm" method="post" action="{$baseUrl}/index.php/dev/$$$call$$$/grid/sponsor/sponsor-row/update-sponsor"}
{include file="common/formErrors.tpl"}

<h3>1.5 {translate key="manager.setup.sponsors"}</h3>

<p>{translate key="manager.setup.sponsorsDescription"}</p>

<table width="100%" class="data">
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="institution" key="manager.setup.institution"}</td>
		<td width="80%" class="value"><input type="text" name="institution" id="institution" size="40" maxlength="90" class="textField rule_required" value="{$institution|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="url" key="common.url"}</td>
		<td width="80%" class="value"><input type="text" name="url" id="url" size="40" maxlength="255" class="textField rule_required rule_url" value="{$url|escape}" /></td>
	</tr>
</table>

{if $gridId}
	<input type="hidden" name="gridId" value="{$gridId|escape}" />	
{/if}
{if $rowId}
	<input type="hidden" name="rowId" value={$rowId|escape} />
{/if}
{if $sponsorId}
	<input type="hidden" name="sponsorId" value="{$sponsorId|escape}" />
{/if}

	

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>