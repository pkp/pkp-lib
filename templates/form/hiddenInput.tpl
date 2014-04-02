{**
 * templates/form/hiddenInput.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Hidden input element
 *}
<input type="hidden"
	  id="{$FBV_id|escape}"
	  name="{$FBV_name|escape}"
	  class="{$FBV_class}{if $FBV_validation} {$FBV_validation|escape}{/if}"
	  value="{$FBV_value|escape}"
	  {$FBV_hiddenInputParams} />
