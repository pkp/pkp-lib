{**
 * templates/controllers/grid/tasks/task.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief A single task appearing in the task grid
 *
 * @uses $notificationMgr NotificationManager
 * @uses $notification Notification The notification object
 * @uses $context Journal|Press The journal or press which this notification
 *   comes from.
 * @uses $notificationObjectTitle string The title of the object this
 *   notification is about.
 * @uses $message string The message of the notification
 * @uses $isMultiContext bool Do we have multiple contexts?
 *}
<div class="task{if !$notification->getDateRead()} unread{/if}">
	<span class="message">
		{$message}
	</span>
	<div class="details">
		{if $isMultiContext}
			<span class="acronym">
				{$context->getLocalizedAcronym()|htmlspecialchars}
			</span>
		{/if}
		<span class="submission">
			{$notificationObjectTitle|htmlspecialchars}
		</span>
	</div>
</div>
