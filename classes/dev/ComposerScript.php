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
	 * Recursively copies the contents of a directory from source to destination.
	 *
	 * @param string $src The source directory.
	 * @param string $dst The destination directory.
	 * @throws Exception If a directory cannot be opened or a file cannot be copied.
	 */
	private static function copyDir(string $src, string $dst): void
	{
		if (!is_dir($src)) {
			throw new Exception("Source directory does not exist: $src");
		}

		$dir = @opendir($src);
		if (!$dir) {
			throw new Exception("Failed to open directory: $src");
		}

		if (!@mkdir($dst, 0755, true) && !is_dir($dst)) {
			throw new Exception("Failed to create destination directory: $dst");
		}

		while (false !== ($file = readdir($dir))) {
			if ($file != '.' && $file != '..') {
				$srcFile = $src . '/' . $file;
				$dstFile = $dst . '/' . $file;

				if (is_dir($srcFile)) {
					self::copyDir($srcFile, $dstFile);
				} else {
					if (!@copy($srcFile, $dstFile)) {
						throw new Exception("Failed to copy file: $srcFile to $dstFile");
					}
				}
			}
		}

		closedir($dir);
	}

	/**
	 * A post-install-cmd custom composer script that
	 * copies composer installs from repositories
	 * to the correct/existing directories of the following dependencies:
	 * jquery-ui and jquery validation
	 */
	public static function copyVendorAssets(): void
	{
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

		try {
			// jQuery UI
			if (!file_exists($vendorBaseDir . '/components/jqueryui')) {
				if (!mkdir($vendorBaseDir . '/components/jqueryui', 0755, true)) {
					throw new Exception("Failed to create directory: {$vendorBaseDir}/components/jqueryui");
				}
			}

			if (!copy($source['jquery-ui.js'], $dest['jquery-ui.js'])) {
				throw new Exception('Failed to copy jquery-ui.js to destination folder');
			}

			if (!copy($source['jquery-ui.min.js'], $dest['jquery-ui.min.js'])) {
				throw new Exception('Failed to copy jquery-ui.min.js to destination folder');
			}
			

			// jQuery Validation
			if (!file_exists($dest['jquery-validate'])) {
				if (!mkdir($dest['jquery-validate'], 0755, true)) {
					throw new Exception("Failed to create directory: {$dest['jquery-validate']}");
				}
			}

			self::copyDir($source['jquery-validate'], $dest['jquery-validate']);
		} catch (Exception $e) {
			throw $e;
		}
	}
}
