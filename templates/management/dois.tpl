{extends file="layouts/backend.tpl"}
{block name="page"}
    <!-- Add page content here -->
    <h1 class="app__pageHeading">
        {translate key="doi.manager.displayName"}
    </h1>

    <tabs :track-history="true">
        {if $displaySubmissionsTab}
            <tab id="submission-doi-management" label="{translate key="common.publications"}">
                <h1>{translate key="common.publications"}</h1>
                <doi-list-panel
                        v-bind="components.submissionDoiListPanel"
                        @set="set"
                />
            </tab>
        {/if}
    </tabs>
{/block}
