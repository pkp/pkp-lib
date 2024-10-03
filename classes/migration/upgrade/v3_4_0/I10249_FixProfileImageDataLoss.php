<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I10249_FixProfileImageDataLoss.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10249_FixProfileImageDataLoss
 *
 * @brief Fix data loss at the user profile image
 *
 * @see https://github.com/pkp/pkp-lib/issues/10249
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;

class I10249_FixProfileImageDataLoss extends \PKP\migration\Migration
{
    /**
     * Runs the migration
     */
    public function up(): void
    {
        $orderByModifiedDate = fn(string $a, string $b) => filemtime($a) - filemtime($b);
        $publicFilesPath = Config::getVar('files', 'public_files_dir') . '/site';
        DB::table('user_settings')
            ->where('setting_name', '=', 'profileImage')
            ->whereRaw("COALESCE(setting_value, '') <> ''")
            ->whereRaw("setting_value NOT LIKE CONCAT('%profileImage-', user_id, '.%')")
            ->select('user_id')
            ->chunkById(1000, function (Collection $rows) use ($publicFilesPath, $orderByModifiedDate) {
                foreach ($rows as $row) {
                    $globPattern = "{$publicFilesPath}/profileImage-{$row->user_id}.*";
                    $candidates = glob($globPattern, GLOB_NOSORT);
                    if (empty($candidates)) {
                        $this->_installer->log("Failed to locate a profile image for the user ID {$row->user_id} at {$globPattern}, cleaning up the value");
                        DB::table('user_settings')
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
                    DB::table('user_settings')
                        ->where('user_id', $row->user_id)
                        ->where('setting_name', 'profileImage')
                        ->update([
                            'setting_value' => json_encode([
                                'name' => $fileName,
                                'uploadName' => $fileName,
                                'width' => $width,
                                'height' => $height,
                                'dateUploaded' => date('Y-m-d H:i:s', filemtime($filePath))
                            ])
                        ]);
                }
            }, 'user_id');
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
