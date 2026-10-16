import {transform} from 'esbuild';

/**
 * Lower Vivliostyle's private static fields before Rolldown processes them.
 * Vite 8 beta's transform loses their lazy counter-style initializers when
 * targeting older browsers, causing FetchStylesheet to fail at runtime.
 */
export default function vivliostyleCompat() {
	let target;
	return {
		name: 'vivliostyle-private-fields-compat',
		enforce: 'pre',
		apply: 'build',
		configResolved(config) {
			target = config.build.target;
		},
		async transform(code, id) {
			if (!id.replaceAll('\\', '/').includes('/@vivliostyle/print/dist/')) {
				return null;
			}
			const result = await transform(code, {
				target,
				// Leave destructuring to Vite; esbuild cannot lower every form.
				supported: {destructuring: true},
				sourcefile: id,
				sourcemap: true,
			});
			return {code: result.code, map: result.map};
		},
	};
}
