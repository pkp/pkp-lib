<?php

/**
 * @file classes/migration/ConvertSiteWideAboutToRichTextArea.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertSiteWideAboutToRichTextArea
 * @brief Convert the site-wide About to Rich TextArea
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class ConvertSiteWideAboutToRichTextArea extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		// pkp/pkp-lib#5844: Escaping "about" values to avoid WYSIWYG issues
		$aboutValuesIds = Capsule::table('site_settings AS settings')
			->where('settings.setting_name', 'about')
			->pluck('setting_value', 'locale');
		foreach ($aboutValuesIds as $locale => $value) {
			Capsule::table('site_settings')
				->where([
					['setting_name', '=', 'about'],
					['locale', '=', $locale]
				])
				->update([
					'setting_value' => htmlentities($value, ENT_QUOTES)
				]);
		}
	}

	/**
	 * Reverse the modifications
	 * @return void
	 */
	public function down() {
		$aboutValuesIds = Capsule::table('site_settings AS settings')
			->where('settings.setting_name', 'about')
			->pluck('setting_value', 'locale');
		foreach ($aboutValuesIds as $locale => $value) {
			Capsule::table('site_settings')
				->where([
					['setting_name', '=', 'about'],
					['locale', '=', $locale]
				])
				->update([
					'setting_value' => html_entity_decode($value, ENT_QUOTES)
				]);
		}
	}
}
