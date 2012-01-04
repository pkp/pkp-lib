 {**
 * button.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form button
 *}

<button type="{$FBV_type|escape}"{if $FBV_disabled} disabled="disabled"{/if} {$FBV_buttonParams}>{translate key=$FBV_label}</button>
