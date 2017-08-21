<template>
	<div class="pkpListPanel pkpListPanel--submissions" :class="classStatus">
		<div class="pkpListPanel__header">
			<div class="pkpListPanel__title">{{ i18n.title }}</div>
			<ul class="pkpListPanel__actions">
				<li v-if="currentUserCanFilter">
					<button @click.prevent="toggleFilter" :class="{'--isActive': isFilterVisible}">
						<span class="fa fa-filter"></span>
						{{ i18n.filter }}
					</button>
				</li>
				<li>
					<a :href="addUrl">{{ i18n.add }}</a>
				</li>
			</ul>
			<list-panel-search
				@searchPhraseChanged="setSearchPhrase"
				:searchPhrase="searchPhrase"
				:i18n="i18n"
			/>
		</div>
		<div class="pkpListPanel__body pkpListPanel__body--submissions">
			<submissions-list-filter
				v-if="currentUserCanFilter"
				@filterList="updateFilter"
				:isVisible="isFilterVisible"
				:filters="filters"
				:i18n="i18n"
			/>
			<div class="pkpListPanel__content pkpListPanel__content--submissions">
				<ul class="pkpListPanel__items" aria-live="polite">
					<submissions-list-item
						v-for="item in collection.items"
						:item="item"
						:i18n="i18n"
						:apiPath="apiPath"
						:infoUrl="infoUrl"
					/>
				</ul>
			</div>
		</div>
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
import SubmissionsListFilter from './SubmissionsListFilter.vue';
import SubmissionsListItem from './SubmissionsListItem.vue';

export default {
	extends: ListPanel,
	name: 'SubmissionsListPanel',
	components: {
		SubmissionsListFilter,
		SubmissionsListItem,
	},
	data: function() {
		return {
			addUrl: '',
			infoUrl: '',
		};
	},
	computed: {
		/**
		 * Can the current user filter the list?
		 */
		currentUserCanFilter: function() {
			return pkp.userHasRole(['manager', 'subeditor', 'assistant']);
		}
	},
	mounted: function() {
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
};
</script>
