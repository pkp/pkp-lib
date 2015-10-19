{**
 * linkActionButton.tpl
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template that renders a button for a link action.
 *
 * Parameter:
 *  action: The LinkAction we create a button for.
 *  buttonId: The id of the link.
 *  hoverTitle: Whether to show the title as hover text only.
 *}
<a href="#" id="{$buttonId|escape}" title="{$action->getHoverTitle()|escape}" class="pkp_controllers_linkAction pkp_linkaction_{$action->getId()} pkp_linkaction_icon_{$action->getImage()}">{$action->getTitle()|strip_unsafe_html}</a>
