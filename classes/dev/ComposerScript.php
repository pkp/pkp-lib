<?php
/**
 * @file classes/dev/ComposerScript.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ComposerScript
 *
 * @brief Custom composer scripts to run post installs/updates
 */

namespace PKP\dev;

use Exception;

require_once '../../tools/bootstrap.inc.php';
import('lib.pkp.classes.file.FileManager');

class ComposerScript
{
	/**
	 * A post-install-cmd custom composer script that
	 * copies composer installs from repositories
	 * to the correct/existing directories of the following dependencies:
	 * jquery-ui and jquery validation
	 */
	public static function copyVendorAssets(): void
	{
		$fileManager = new \FileManager();
		$vendorBaseDir = __DIR__ . '/../../lib/vendor';
		$jsPluginsDir = __DIR__ . '/../../js/lib';

		$source = [
			'jquery-ui.js' => $vendorBaseDir . '/jquery/ui/dist/jquery-ui.js',
			'jquery-ui.min.js' => $vendorBaseDir . '/jquery/ui/dist/jquery-ui.min.js',
			'jquery-validate' => $vendorBaseDir . '/jquery/validation/dist'
		];

		$dest = [
			'jquery-ui.js' => $vendorBaseDir . '/components/jqueryui/jquery-ui.js',
			'jquery-ui.min.js' => $vendorBaseDir . '/components/jqueryui/jquery-ui.min.js',
			'jquery-validate' => $jsPluginsDir . '/jquery/plugins/validate'
		];

		// jQuery UI
		if (!$fileManager->copyFile($source['jquery-ui.js'], $dest['jquery-ui.js'])) {
			throw new Exception('Failed to copy jquery-ui.js to destination folder');
		}
		if (!$fileManager->copyFile($source['jquery-ui.min.js'], $dest['jquery-ui.min.js'])) {
			throw new Exception('Failed to copy jquery-ui.min.js to destination folder');
		}

		// jQuery Validation
		if (!$fileManager->copyDir($source['jquery-validate'], $dest['jquery-validate'])) {
			throw new Exception('Failed to copy jquery-validate to destination folder');
		}
	}
}
