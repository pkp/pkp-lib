<template>
    <div class="pkp-vue-list-panel">
        <div class="title">{{ i18n.title }}</div>
        <a href="#" @click="refresh">Refresh</a>
        <list-panel-items v-bind:items="items" v-bind:i18n="i18n"></list-panel-items>
        <list-panel-count v-bind:count="itemCount" v-bind:i18n="i18n"></list-panel-count>
    </div>
</template>

<script>
import ListPanelItems from './ListPanelItems.vue';
import ListPanelCount from './ListPanelCount.vue';

export default {
    name: 'ListPanel',
    components: {
        ListPanelItems,
        ListPanelCount,
    },
    data: function() {
      return {
          id: '',
          items: [],
          config: {},
          i18n: {},
      };
    },
    computed: {
      itemCount: function() {
          return this.items.length;
      },
    },
    methods: {
      refresh: function() {
          var self = this;
          $.get(this.config.handlers.get.url, function(r) {
              self.items = JSON.parse(r);
          });
      }
    },
}
</script>
