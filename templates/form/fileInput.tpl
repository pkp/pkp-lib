{**
 * radioButton.tpl
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * form radio button
 *}

<div class="fileInputContainer">
	<input type="file" id="{$FBV_id}" name="{$FBV_name}" class="uploadField"{if $FBV_disabled} disabled="disabled"{/if} />
	<div class="fakeFile">
		<input class="fakeInput" {if $FBV_disabled} disabled="disabled"{/if}>
		<input type="button" value="{translate key='navigation.browse'}..." class="button fakeButton"{if $FBV_disabled} disabled="disabled"{/if}/>
	</div>
</div>
{if $FBV_submit}<input type="submit" name="{$FBV_submit}" value="{translate key="common.upload"}" class="button uploadFile"{if $FBV_disabled} disabled="disabled"{/if} />{/if}
