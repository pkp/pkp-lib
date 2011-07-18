{**
 * footer.tpl
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Common site footer.
 *
 *}
{if $displayCreativeCommons}
{translate key="common.ccLicense"}
{/if}
{if $pageFooter}
<br /><br />
{$pageFooter}
{/if}
{call_hook name="Templates::Common::Footer::PageFooter"}
</div><!-- content -->
</div><!-- main -->
</div><!-- body -->

{get_debug_info}
{if $enableDebugStats}{include file=$pqpTemplate}{/if}

</div><!-- container -->
{if $hasSystemNotifications}
	{url|assign:fetchNotificationUrl page='notification' op='fetchNotification' escape=false}
	<script type="text/javascript">
		$.get('{$fetchNotificationUrl}', null,
			function(data){ldelim}
				var notification = data.content;
				var i, l;
				for (i = 0, l = notification.length; i < l; i++) {ldelim}
					$.pnotify(notification[i]);
				{rdelim}
		{rdelim}, 'json');
	</script>
{/if}{* hasSystemNotifications *}
</body>
</html>

