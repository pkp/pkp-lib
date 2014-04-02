{**
 * templates/form/link.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form link control (used most commonly as a cancel link)
 *}

<div{if $FBV_layoutInfo} class="{$FBV_layoutInfo}"{/if}>
	<a href="{$FBV_href}" id="{$FBV_id}" class="{$FBV_class}">{translate key=$FBV_label}</a>
</div>
