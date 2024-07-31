<?php

/**
 * @file classes/migration/upgrade/I10249_FixProfileImageDataLoss.inc.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10249_FixProfileImageDataLoss
 * @brief Fix data loss at the user profile image
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

class I10249_FixProfileImageDataLoss extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		$orderByModifiedDate = function (string $a, string $b) {
			return filemtime($a) - filemtime($b);
		};
		$publicFilesPath = Config::getVar('files', 'public_files_dir') . '/site';
		Capsule::table('user_settings')
			->where('setting_name', '=', 'profileImage')
			->whereRaw("COALESCE(setting_value, '') <> ''")
			->whereRaw("setting_value NOT LIKE CONCAT('%profileImage-', user_id, '.%')")
			->select('user_id')
			->chunkById(1000, function (Collection $rows) use ($publicFilesPath, $orderByModifiedDate) {
				foreach ($rows as $row) {
					$globPattern = "{$publicFilesPath}/profileImage-{$row->user_id}.*";
					$candidates = glob($globPattern, GLOB_NOSORT);
					if (empty($candidates)) {
						error_log("Failed to locate a profile image for the user ID {$row->user_id} at {$globPattern}, cleaning up the value");
						Capsule::table('user_settings')
							->where('user_id', $row->user_id)
							->where('setting_name', 'profileImage')
							->update(['setting_value' => null]);
						continue;
					}

					if (count($candidates) > 1) {
						usort($candidates, $orderByModifiedDate);
					}

					$filePath = array_pop($candidates);
					$fileName = basename($filePath);
					[$width, $height] = getimagesize($filePath) ?: [0, 0];
					Capsule::table('user_settings')
						->where('user_id', $row->user_id)
						->where('setting_name', 'profileImage')
						->update([
							'setting_value' => json_encode([
								'name' => $fileName,
								'uploadName' => $fileName,
								'width' => $width,
								'height' => $height,
								'dateUploaded' => date('Y-m-d H:i:s', filemtime($filePath))
							]),
							'setting_type' => 'object'
						]);
				}
			}, 'user_id');
	}

	/**
	 * Reverse the migration
	 * @return void
	 */
	public function down() {
		throw new PKP\install\DowngradeNotSupportedException();
	}
}
