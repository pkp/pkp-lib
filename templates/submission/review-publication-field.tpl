{**
 * templates/submission/review-publication-field.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * A helper template to show the value of a publication property.
 *
 * @uses string $prop The name of the property, eg `keywords`
 * @uses string $inLocale The locale key to show. Only include this for localized data
 * @uses string $name The user-facing name of this property, eg "Keywords"
 * @uses string $type The type of the value. Accepts `string`, `array`, `html`
 *}

{if $inLocale}
    {assign var="localizedProp" value=$prop|cat:"['"|cat:$inLocale|cat:"']"}
{else}
    {assign var="localizedProp" value=$prop}
{/if}

<div class="submissionWizard__reviewPanel__item">
    <template v-if="errors.{$prop|escape} && errors.{$localizedProp|escape}">
        <notification
            v-for="(error, i) in errors.{$localizedProp|escape}"
            :key="i"
            type="warning"
        >
            <icon icon="exclamation-triangle"></icon>
            {{ error }}
        </notification>
    </template>
    <h4 class="submissionWizard__reviewPanel__item__header">
        {$name}
    </h4>
    <div
        class="submissionWizard__reviewPanel__item__value"
        {if $type === 'html'}
            v-html="publication.{$localizedProp|escape}
                ? publication.{$localizedProp|escape}
                : '{translate key="common.noneProvided"}'"
        {/if}
    >
        {if $type === 'array'}
            <template v-if="publication.{$localizedProp|escape} && publication.{$localizedProp|escape}.length">
                {{
                    publication.{$localizedProp|escape}
                        .join(
                            t('common.commaListSeparator')
                        )
                }}
            </template>
            <template v-else>
                {translate key="common.noneProvided"}
            </template>
        {elseif $type === 'html'}
            {* empty. see v-html above *}
        {else}
            <template v-if="publication.{$localizedProp|escape}">
                {{ publication.{$localizedProp|escape} }}
            </template>
            <template v-else>
                {translate key="common.noneProvided"}
            </template>
        {/if}
    </div>
</div>