/**
 * @file js/load.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Compiler entry point for building the JavaScript package. File imports
 *  using the `@` symbol are aliased to `lib/ui-library/src`.
 */
import PkpLoad from '../lib/pkp/js/load.js';

// Import controllers used by OPS
import Container from '@/components/Container/Container.vue';
import Page from '@/components/Container/Page.vue';
import AccessPage from '@/components/Container/AccessPage.vue';
import AddContextContainer from '@/components/Container/AddContextContainer.vue';
import AdminPage from '@/components/Container/AdminPage.vue';
import DecisionPage from '@/components/Container/DecisionPage.vue';
import DoiPage from '@/components/Container/DoiPageOPS.vue';
import ImportExportPage from '@/components/Container/ImportExportPage.vue';
import ManageEmailsPage from '@/components/Container/ManageEmailsPage.vue';
import SettingsPage from '@/components/Container/SettingsPage.vue';
import StartSubmissionPage from '@/components/Container/StartSubmissionPage.vue';
import StatsEditorialPage from '@/components/Container/StatsEditorialPage.vue';
import StatsPublicationsPage from '@/components/Container/StatsPublicationsPage.vue';
import StatsContextPage from '@/components/Container/StatsContextPage.vue';
import StatsUsersPage from '@/components/Container/StatsUsersPage.vue';
import SubmissionWizardPage from '@/components/Container/SubmissionWizardPageOPS.vue';
import WorkflowPage from '@/components/Container/WorkflowPageOPS.vue';
import JobsPage from '@/components/Container/JobsPage.vue';

// Required by the URN plugin
import FieldText from '@/components/Form/fields/FieldText.vue';

// Expose Vue, the registry and controllers in a global var
window.pkp = Object.assign(PkpLoad, {
	controllers: {
		Container,
		Page,
		AccessPage,
		AddContextContainer,
		AdminPage,
		DecisionPage,
		DoiPage,
		ImportExportPage,
		ManageEmailsPage,
		JobsPage,
		SettingsPage,
		StartSubmissionPage,
		StatsEditorialPage,
		StatsPublicationsPage,
		StatsContextPage,
		StatsUsersPage,
		SubmissionWizardPage,
		WorkflowPage
	}
});

// Required by the URN plugin
window.pkp.Vue.component('field-text', FieldText);
