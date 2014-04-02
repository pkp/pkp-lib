{**
 * controllers/grid/feature/featuresOptions.tpl
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Grid features js options.
 *}
{ldelim}
	{foreach name=features from=$features item=feature}
		{$feature->getId()}: {ldelim}
			JSClass: '{$feature->getJSClass()}',
			options: {ldelim}
				{foreach name=featureOptions from=$feature->getOptions() key=optionName item=optionValue}
					{$optionName}: {if $optionValue}'{$optionValue|escape:javascript}'{else}false{/if}{if !$smarty.foreach.featureOptions.last},{/if}
				{/foreach}
			{rdelim}
		{rdelim}{if !$smarty.foreach.features.last},{/if}
	{/foreach}
{rdelim}
