<template>
	<div class="pkpListPanel pkpListPanel--submissions" :class="classStatus">
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
				v-for="item in collection.items"
				:submission="item"
				:i18n="i18n"
				:apiPath="apiPath"
				:infoUrl="infoUrl"
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
import ListPanel from './../ListPanel.vue';
import SubmissionsListItem from './SubmissionsListItem.vue';

export default _.extend({}, ListPanel, {
	name: 'SubmissionsListPanel',
	components: _.extend({}, ListPanel.components, {
		SubmissionsListItem,
	}),
	data: function() {
		return _.extend({}, ListPanel.data(), {
			addUrl: '',
			infoUrl: '',
		});
	},
	mounted: function() {

		// Call the mounted function on parent component
		ListPanel.mounted.call(this);

		// Store a reference to this component for global event callbacks
		var self = this;

		// Refresh the list when a submission is updated in any way
		pkp.eventBus.$on('submissionUpdated', function(data) {
			self.get();
		});

		// Remove a submission from the list when it is deleted
		pkp.eventBus.$on('submissionDeleted', function(data) {
			if (!_.has(data, 'id') || !_.findWhere(self.collection.items, data)) {
				return;
			}
			self.collection.items = _.reject(self.collection.items, data);
			self.collection.maxItems--;
		});
	},
});
</script>
