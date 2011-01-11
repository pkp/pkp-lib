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
{if !empty($systemNotifications)}
	{translate|assign:"defaultTitleText" key="notification.notification"}
	<script type="text/javascript">
	<!--
	{foreach from=$systemNotifications item=notification}
		{literal}
			$.pnotify({
				pnotify_title: '{/literal}{if $notification->getIsLocalized()}{translate|escape:"js"|default:$defaultTitleText key=$notification->getTitle()}{else}{$notification->getTitle()|escape:"js"|default:$defaultTitleText}{/if}{literal}',
				pnotify_text: '{/literal}{if $notification->getIsLocalized()}{translate|escape:"js" key=$notification->getContents() param=$notification->getParam()}{else}{$notification->getContents()|escape:"js"}{/if}{literal}',
				pnotify_addclass: '{/literal}{$notification->getStyleClass()|escape:"js"}{literal}',
				pnotify_notice_icon: 'notifyIcon {/literal}{$notification->getIconClass()|escape:"js"}{literal}'
			});
		{/literal}
	{/foreach}
	// -->
	</script>
{/if}{* systemNotifications *}
</body>
</html>

