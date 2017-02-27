<template>
    <div class="pkpListPanel pkpMySubmissions" v-bind:class="classLoading">
        <div class="pkpListPanel__header">
            <div class="pkpListPanel__title">{{ i18n.title }}</div>
			<ul class="pkpListPanel__actions">
				<li>
					<a :href="addUrl">{{ i18n.add }}</a>
				</li>
			</ul>
            <list-panel-search
    			@searchPhraseChanged="set"
    			:isSearching="isSearching"
    			:searchPhrase="searchPhrase"
    			:i18n="i18n"
    		/>
        </div>
    	<ul class="pkpListPanel__items">
    		<submissions-list-item
    			v-for="item in items"
                :submission="item"
                :infoUrl="infoUrl"
    			:i18n="i18n"
    		/>
    	</ul>
        <list-panel-count v-bind:count="itemCount" v-bind:i18n="i18n"></list-panel-count>
		<a href="#" @click="refresh">Refresh</a>
    </div>
</template>

<script>
import ListPanel from './../ListPanel.vue';
import SubmissionsListItem from './SubmissionsListItem.vue';

export default _.extend({}, ListPanel, {
    name: 'MySubmissions',
	components: _.extend({}, ListPanel.components, {
        SubmissionsListItem,
	}),
});
</script>
