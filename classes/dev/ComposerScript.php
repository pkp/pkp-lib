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
		function copyDir($src, $dst) {
			$dir = opendir($src);
			@mkdir($dst, 0755, true);
			while (false !== ($file = readdir($dir))) {
				if ($file != '.' && $file != '..') {
					if (is_dir($src . '/' . $file)) {
						copyDir($src . '/' . $file, $dst . '/' . $file);
					} else {
						copy($src . '/' . $file, $dst . '/' . $file);
					}
				}
			}
			closedir($dir);
		}

		try {
			$vendorBaseDir = __DIR__ . '/../../lib/vendor';
			$jsPluginsDir = __DIR__ . '/../../js/lib';
			$jqueryPluginsDir = $jsPluginsDir . '/jquery/plugins';
			$vendorComponents = __DIR__ . '/../../lib/vendor/components';

			$jqueryUiDist = $vendorBaseDir . '/jquery/ui/dist';
			$jqueryValidationDist = $vendorBaseDir . '/jquery/validation/dist';
			$chartjsDist = $vendorBaseDir . '/chart/js/dist';

			// jQuery UI
			if (!file_exists($vendorComponents . '/jqueryui')) {
				mkdir($vendorComponents . '/jqueryui', 0755, true);
			}
			copy($jqueryUiDist . '/jquery-ui.js', $vendorComponents . '/jqueryui/jquery-ui.js');
			copy($jqueryUiDist . '/jquery-ui.min.js', $vendorComponents . '/jqueryui/jquery-ui.min.js');

			// jQuery Validation
			if (!file_exists($jqueryPluginsDir . '/validate')) {
				mkdir($jqueryPluginsDir . '/validate', 0755, true);
			}
			copyDir($jqueryValidationDist, $jqueryPluginsDir . '/validate');
			
		} catch (Exception $e) {
			error_log($e->getMessage());
		}
	}
}
