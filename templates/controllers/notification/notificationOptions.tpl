{**
 * controllers/notification/notificationOptions.tpl
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Notification options.
 *}

fetchNotificationUrl: '{url|escape:javascript router=$smarty.const.ROUTE_PAGE page='notification' op='fetchNotification' escape=false}',
hasSystemNotifications: '{$hasSystemNotifications}'
{if $requestOptions}
	,
	requestOptions: {ldelim}
		{foreach name=levels from=$requestOptions key=level item=levelOptions}
			{$level}: {if $levelOptions} {ldelim}
				{foreach name=types from=$levelOptions key=type item=typeOptions}
					{$type}: {if $typeOptions} {ldelim}
						assocType: '{$typeOptions[0]}',
						assocId: '{$typeOptions[1]}'
					{rdelim}{else}0{/if}{if !$smarty.foreach.types.last},{/if}
				{/foreach}
			{rdelim}{else}0{/if}{if !$smarty.foreach.levels.last},{/if}
		{/foreach}
	{rdelim}
{/if}

