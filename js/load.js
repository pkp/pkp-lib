/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Common configuration for building the Javascript package
 */

// Vue lib and custom mixins
import Vue from 'vue';
import GlobalMixins from '@/mixins/global.js';
import VModal from 'vue-js-modal';
import VTooltip from 'v-tooltip';
import VueScrollTo from 'vue-scrollto';

// Mixins exposed for plugins
import dialog from '@/mixins/dialog.js';
import localizeMoment from '@/mixins/localizeMoment.js';

// Global components of UI Library
import Badge from '@/components/Badge/Badge.vue';
import Dropdown from '@/components/Dropdown/Dropdown.vue';
import Icon from '@/components/Icon/Icon.vue';
import Notification from '@/components/Notification/Notification.vue';
import Panel from '@/components/Panel/Panel.vue';
import PanelSection from '@/components/Panel/PanelSection.vue';
import PkpButton from '@/components/Button/Button.vue';
import PkpHeader from '@/components/Header/Header.vue';
import Spinner from '@/components/Spinner/Spinner.vue';
import Step from '@/components/Steps/Step.vue';
import Steps from '@/components/Steps/Steps.vue';
import Tab from '@/components/Tabs/Tab.vue';
import Tabs from '@/components/Tabs/Tabs.vue';

// Other components of UI Library
import ActionPanel from '@/components/ActionPanel/ActionPanel.vue';
import ButtonRow from '@/components/ButtonRow/ButtonRow.vue';
import DoughnutChart from '@/components/Chart/DoughnutChart.vue';
import LineChart from '@/components/Chart/LineChart.vue';
import Composer from '@/components/Composer/Composer.vue';
import DateRange from '@/components/DateRange/DateRange.vue';
import File from '@/components/File/File.vue';
import FileAttacher from '@/components/FileAttacher/FileAttacher.vue';
import FileUploader from '@/components/FileUploader/FileUploader.vue';
import FileUploadProgress from '@/components/FileUploadProgress/FileUploadProgress.vue';
import PkpFilter from '@/components/Filter/Filter.vue';
import FilterAutosuggest from '@/components/Filter/FilterAutosuggest.vue';
import FilterSlider from '@/components/Filter/FilterSlider.vue';
import FilterSliderMultirange from '@/components/Filter/FilterSliderMultirange.vue';
import List from '@/components/List/List.vue';
import ListItem from '@/components/List/ListItem.vue';
import Modal from '@/components/Modal/Modal.vue';
import MultilingualProgress from '@/components/MultilingualProgress/MultilingualProgress.vue';
import Orderer from '@/components/Orderer/Orderer.vue';
import Pagination from '@/components/Pagination/Pagination.vue';
import ProgressBar from '@/components/ProgressBar/ProgressBar.vue';
import Search from '@/components/Search/Search.vue';
import Table from '@/components/Table/Table.vue';
import TableCell from '@/components/Table/TableCell.vue';
import Tooltip from '@/components/Tooltip/Tooltip.vue';

// Form components from UI Library
import Form from '@/components/Form/Form.vue';
import FieldArchivingPn from '@/components/Form/fields/FieldArchivingPn.vue';
import FieldAutosuggestPreset from '@/components/Form/fields/FieldAutosuggestPreset.vue';
import FieldBase from '@/components/Form/fields/FieldBase.vue';
import FieldBaseAutosuggest from '@/components/Form/fields/FieldBaseAutosuggest.vue';
import FieldColor from '@/components/Form/fields/FieldColor.vue';
import FieldControlledVocab from '@/components/Form/fields/FieldControlledVocab.vue';
import FieldHtml from '@/components/Form/fields/FieldHtml.vue';
import FieldMetadataSetting from '@/components/Form/fields/FieldMetadataSetting.vue';
import FieldOptions from '@/components/Form/fields/FieldOptions.vue';
import FieldPreparedContent from '@/components/Form/fields/FieldPreparedContent.vue';
import FieldPubId from '@/components/Form/fields/FieldPubId.vue';
import FieldRadioInput from '@/components/Form/fields/FieldRadioInput.vue';
import FieldRichText from '@/components/Form/fields/FieldRichText.vue';
import FieldRichTextarea from '@/components/Form/fields/FieldRichTextarea.vue';
import FieldSelect from '@/components/Form/fields/FieldSelect.vue';
import FieldSelectIssue from '@/components/Form/fields/FieldSelectIssue.vue';
import FieldSelectIssues from '@/components/Form/fields/FieldSelectIssues.vue';
import FieldSelectSubmissions from '@/components/Form/fields/FieldSelectSubmissions.vue';
import FieldSelectUsers from '@/components/Form/fields/FieldSelectUsers.vue';
import FieldShowEnsuringLink from '@/components/Form/fields/FieldShowEnsuringLink.vue';
import FieldText from '@/components/Form/fields/FieldText.vue';
import FieldTextarea from '@/components/Form/fields/FieldTextarea.vue';
import FieldUpload from '@/components/Form/fields/FieldUpload.vue';
import FieldUploadImage from '@/components/Form/fields/FieldUploadImage.vue';

// Panel components from UI Library
import ListPanel from '@/components/ListPanel/ListPanel.vue';

// Helper for initializing and tracking Vue controllers
import VueRegistry from './classes/VueRegistry.js';

Vue.use(VModal, {
	dynamic: true,
	injectModalsContainer: true,
});
Vue.use(VTooltip, {defaultTrigger: 'click'});
Vue.use(VueScrollTo);
Vue.mixin(GlobalMixins);

// Register global components
Vue.component('Badge', Badge);
Vue.component('PkpBadge', Badge);
Vue.component('Dropdown', Dropdown);
Vue.component('PkpDropdown', Dropdown);
Vue.component('Icon', Icon);
Vue.component('PkpIcon', Icon);
Vue.component('Notification', Notification);
Vue.component('PkpNotification', Notification);
Vue.component('Panel', Panel);
Vue.component('PkpPanel', Panel);
Vue.component('PanelSection', PanelSection);
Vue.component('PkpPanelSection', PanelSection);
Vue.component('PkpButton', PkpButton);
Vue.component('PkpHeader', PkpHeader);
Vue.component('Spinner', Spinner);
Vue.component('PkpSpinner', Spinner);
Vue.component('Step', Step);
Vue.component('PkpStep', Step);
Vue.component('Steps', Steps);
Vue.component('PkpSteps', Steps);
Vue.component('Tab', Tab);
Vue.component('PkpTab', Tab);
Vue.component('Tabs', Tabs);
Vue.component('PkpTabs', Tabs);

// Register other components
Vue.component('PkpActionPanel', ActionPanel);
Vue.component('PkpButtonRow', ButtonRow);
Vue.component('PkpDoughnutChart', DoughnutChart);
Vue.component('PkpLineChart', LineChart);
Vue.component('PkpComposer', Composer);
Vue.component('PkpDateRange', DateRange);
Vue.component('PkpFile', File);
Vue.component('PkpFileAttacher', FileAttacher);
Vue.component('PkpFileUploader', FileUploader);
Vue.component('PkpFileUploadProgress', FileUploadProgress);
Vue.component('PkpFilter', PkpFilter);
Vue.component('PkpFilterAutosuggest', FilterAutosuggest);
Vue.component('PkpFilterSlider', FilterSlider);
Vue.component('PkpFilterSliderMultirange', FilterSliderMultirange);
Vue.component('PkpList', List);
Vue.component('PkpListItem', ListItem);
Vue.component('PkpModal', Modal);
Vue.component('PkpMultilingualProgress', MultilingualProgress);
Vue.component('PkpOrderer', Orderer);
Vue.component('PkpPagination', Pagination);
Vue.component('PkpProgressBar', ProgressBar);
Vue.component('PkpSearch', Search);
Vue.component('PkpTable', Table);
Vue.component('PkpTableCell', TableCell);
Vue.component('PkpTooltip', Tooltip);

// Register Form components
Vue.component('PkpForm', Form);
Vue.component('PkpFieldArchivingPn', FieldArchivingPn);
Vue.component('PkpFieldAutosuggestPreset', FieldAutosuggestPreset);
Vue.component('PkpFieldBase', FieldBase);
Vue.component('PkpFieldBaseAutosuggest', FieldBaseAutosuggest);
Vue.component('PkpFieldColor', FieldColor);
Vue.component('PkpFieldControlledVocab', FieldControlledVocab);
Vue.component('PkpFieldHtml', FieldHtml);
Vue.component('PkpFieldMetadataSetting', FieldMetadataSetting);
Vue.component('PkpFieldOptions', FieldOptions);
Vue.component('PkpFieldPreparedContent', FieldPreparedContent);
Vue.component('PkpFieldPubId', FieldPubId);
Vue.component('PkpFieldRadioInput', FieldRadioInput);
Vue.component('PkpFieldRichText', FieldRichText);
Vue.component('PkpFieldRichTextarea', FieldRichTextarea);
Vue.component('PkpFieldSelect', FieldSelect);
Vue.component('PkpFieldSelectIssue', FieldSelectIssue);
Vue.component('PkpFieldSelectIssues', FieldSelectIssues);
Vue.component('PkpFieldSelectSubmissions', FieldSelectSubmissions);
Vue.component('PkpFieldSelectUsers', FieldSelectUsers);
Vue.component('PkpFieldShowEnsuringLink', FieldShowEnsuringLink);
Vue.component('PkpFieldText', FieldText);
Vue.component('PkpFieldTextarea', FieldTextarea);
Vue.component('PkpFieldUpload', FieldUpload);
Vue.component('PkpFieldUploadImage', FieldUploadImage);

// Register ListPanel
Vue.component('PkpListPanel', ListPanel);

export default {
	Vue: Vue,
	registry: VueRegistry,
	eventBus: new Vue(),
	const: {},
	localeKeys: {},
	currentUser: null,
	vueMixins: {
		dialog,
		localizeMoment,
	},
};
