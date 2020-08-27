<?php

/**
 * @file classes/migration/upgrade/PKPv3_3_0UpgradeMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPv3_3_0UpgradeMigration extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		Capsule::schema()->table('submissions', function (Blueprint $table) {
			// pkp/pkp-lib#3572 Remove OJS 2.x upgrade tools
			$table->dropColumn('locale');
			// pkp/pkp-lib#2493 Remove obsolete columns
			$table->dropColumn('section_id');
		});
		Capsule::schema()->table('publication_settings', function (Blueprint $table) {
			// pkp/pkp-lib#6096 DB field type TEXT is cutting off long content
			$table->mediumText('setting_value')->nullable()->change();
		});
		Capsule::schema()->table('authors', function (Blueprint $table) {
			// pkp/pkp-lib#2493 Remove obsolete columns
			$table->dropColumn('submission_id');
		});
		Capsule::schema()->table('author_settings', function (Blueprint $table) {
			// pkp/pkp-lib#2493 Remove obsolete columns
			$table->dropColumn('setting_type');
		});
		Capsule::schema()->table('announcements', function (Blueprint $table) {
			// pkp/pkp-lib#5865 Change announcement expiry format in database
			$table->date('date_expire')->change();
		});

		// Transitional: The stage_id column may have already been added by the ADODB schema toolset
		if (!Capsule::schema()->hasColumn('email_templates_default', 'stage_id')) {
			Capsule::schema()->table('email_templates_default', function (Blueprint $table) {
				// pkp/pkp-lib#4796 stage ID as a filter parameter to email templates
				$table->bigInteger('stage_id')->nullable();
			});
		}

		// pkp/pkp-lib#6093 Don't allow nulls (previously an upgrade workaround)
		Capsule::schema()->table('user_settings', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->default(0)->change();
			$table->bigInteger('assoc_id')->default(0)->change();
		});
		Capsule::schema()->table('announcement_types', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
		});
		Capsule::schema()->table('email_templates', function (Blueprint $table) {
			$table->bigInteger('context_id')->default(0)->change();
		});
		Capsule::schema()->table('genres', function (Blueprint $table) {
			$table->bigInteger('seq')->change();
			$table->smallInteger('supplementary')->default(0)->change();
		});
		Capsule::schema()->table('event_log', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('email_log', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('notes', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('review_forms', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('review_assignments', function (Blueprint $table) {
			$table->bigInteger('review_round_id')->change();
		});
		Capsule::schema()->table('authors', function (Blueprint $table) {
			$table->bigInteger('publication_id')->change();
		});
		Capsule::schema()->table('edit_decisions', function (Blueprint $table) {
			$table->bigInteger('review_round_id')->change();
		});
		Capsule::connection()->unprepared('UPDATE review_assignments SET review_form_id=NULL WHERE review_form_id=0');

		$this->_populateEmailTemplates();
		$this->_makeRemoteUrlLocalizable();
	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
		throw new PKP\install\DowngradeNotSupportedException();
	}

	/**
	 * @return void
	 * @brief populate email templates with new records for workflow stage id
	 */
	private function _populateEmailTemplates() {
		$xmlDao = new XMLDAO();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$data = $xmlDao->parseStruct($emailTemplateDao->getMainEmailTemplatesFilename(), array('email'));
		foreach ($data['email'] as $template) {
			$attr = $template['attributes'];
			if (array_key_exists('stage_id', $attr)) {
				Capsule::table('email_templates_default')->where('email_key', $attr['key'])->update(array('stage_id' => $attr['stage_id']));
			}
		}
	}

	/**
	 * @return void
	 * @brief make remoteUrl navigation item type multilingual and drop the url column
	 */
	private function _makeRemoteUrlLocalizable() {
		$contextService = Services::get('context');
		$contextIds = $contextService->getIds();
		foreach ($contextIds as $contextId) {
			$context = $contextService->get($contextId);
			$locales = $context->getData('supportedLocales');

			$navigationItems = Capsule::table('navigation_menu_items')->where('context_id', $contextId)->pluck('url', 'navigation_menu_item_id')->filter()->all();
			foreach ($navigationItems as $navigation_menu_item_id => $url) {
				foreach ($locales as $locale) {
					Capsule::table('navigation_menu_item_settings')->insert([
						'navigation_menu_item_id' => $navigation_menu_item_id,
						'locale' => $locale,
						'setting_name' => 'remoteUrl',
						'setting_value' => $url,
						'setting_type' => 'string'
					]);
				}
			}
		}

		$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site = $siteDao->getSite();
		$supportedLocales = $site->getSupportedLocales();
		$navigationItems = Capsule::table('navigation_menu_items')->where('context_id', '0')->pluck('url', 'navigation_menu_item_id')->filter()->all();
		foreach ($navigationItems as $navigation_menu_item_id => $url) {
			foreach ($supportedLocales as $locale) {
				Capsule::table('navigation_menu_item_settings')->insert([
					'navigation_menu_item_id' => $navigation_menu_item_id,
					'locale' => $locale,
					'setting_name' => 'remoteUrl',
					'setting_value' => $url,
					'setting_type' => 'string'
				]);
			}
		}

		Capsule::schema()->table('navigation_menu_items', function (Blueprint $table) {
			$table->dropColumn('url');
		});
	}
}
