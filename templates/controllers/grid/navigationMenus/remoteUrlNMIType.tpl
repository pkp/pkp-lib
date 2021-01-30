{**
 * templates/controllers/grid/navigationMenus/remoteUrlNMIType.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Custom remote Url NMI Type edit form part
 *}
{fbvFormSection id="NMI_TYPE_REMOTE_URL" class="NMI_TYPE_CUSTOM_EDIT" title="manager.navigationMenus.form.url" for="remoteUrl" list=true required="true"}
	{fbvElement type="text" multilingual=true id="remoteUrl" value=$remoteUrl maxlength="255" required="true"}
{/fbvFormSection}

