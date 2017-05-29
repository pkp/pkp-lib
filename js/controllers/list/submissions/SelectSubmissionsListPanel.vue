<template>
	<div class="pkpListPanel pkpListPanel--submissions pkpListPanel--selectSubmissions" :class="classStatus">
		<div class="pkpListPanel__header">
			<div class="pkpListPanel__title">{{ i18n.title }}</div>
			<list-panel-search
				@searchPhraseChanged="set"
				:isSearching="isSearching"
				:searchPhrase="searchPhrase"
				:i18n="i18n"
			/>
		</div>
		<ul class="pkpListPanel__items" aria-live="polite">
			<select-submissions-list-item
				v-for="item in collection.items"
				:submission="item"
				:i18n="i18n"
				:inputName="inputName"
			/>
		</ul>
		<div class="pkpListPanel__footer">
			<list-panel-load-more
				v-if="canLoadMore"
				@loadMore="loadMore"
				:isLoading="isLoading"
				:i18n="i18n"
			/>
			<list-panel-count
				:count="itemCount"
				:total="this.collection.maxItems"
				:i18n="i18n"
			/>
		</div>
	</div>
</template>

<script>
import SubmissionsListPanel from './SubmissionsListPanel.vue';
import SelectSubmissionsListItem from './SelectSubmissionsListItem.vue';

export default _.extend({}, SubmissionsListPanel, {
	name: 'SelectSubmissionsListPanel',
	components: _.extend({}, SubmissionsListPanel.components, {
		SelectSubmissionsListItem,
	}),
	data: function() {
		return _.extend({}, SubmissionsListPanel.data(), {
			inputName: '',
		});
	},
});
</script>
