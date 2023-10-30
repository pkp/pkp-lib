import path from 'path';
import fs from 'fs';

const uniqueKeys = new Set();

function extractRegexPlugin({extraKeys}) {
	const fileOutput = path.join('registry', 'uiLocaleKeysBackend.json');
	/**
	 * Supported variants:
	 * this.t('key');
	 * {{ t('key') }}
	 * this.t(
	 *   'key'
	 * )
	 */
	const regex = /\Wt\([\s]*['"`](?<localeKey>[^'"`]+)['"`]/g;

	extraKeys ||= [];

	return {
		name: 'extract-keys',
		transform(code, id) {
			if (!id.includes('node_modules') && (id.endsWith('.vue') || id.endsWith('.js'))) {

				const matches = [...code.matchAll(regex)];
				for (const match of matches) {
					uniqueKeys.add(match[1]);
				}
			}
			return code;
		},
		buildEnd() {
			for (const key of extraKeys) {
				uniqueKeys.add(key);
			}

			if (uniqueKeys.size) {
				const dir = path.dirname(fileOutput);

				if (!fs.existsSync(dir)) {
					fs.mkdirSync(dir, {recursive: true});
				}

				const outputArray = [...uniqueKeys].sort();

				fs.writeFileSync(
					fileOutput,
					`${JSON.stringify(outputArray, null, 2)}\n`,
				);
				console.log(`Written all existing locale keys to ${fileOutput}`);
			}
		},
	};
}

module.exports = extractRegexPlugin;
