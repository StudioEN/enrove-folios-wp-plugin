<?php
namespace Enrove\Modules\EnroveMain;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Development only: serves the admin's Tailwind from the Vite dev server
 * (`npm run dev`), with hot reload, instead of the compiled assets/build.
 *
 * The WordPress.org package leaves this file out (.claude/scripts/
 * package-plugin.sh), so a released plugin has no code that probes or loads
 * anything from localhost; Module::enqueue_scripts() finds no class and reads
 * the committed manifest.
 */
class Vite_Dev
{
	const PORT = 5173;

	/**
	 * Enqueues the Vite client and the Tailwind entry when the dev server is
	 * answering, on a local or development site (WP_ENVIRONMENT_TYPE; Studio
	 * sets 'local'), for a request from this machine.
	 *
	 * @return bool Whether it did; false means use the compiled build.
	 */
	public static function enqueue()
	{
		$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		if (!in_array(wp_get_environment_type(), ['local', 'development'], true) || !in_array($remote_addr, ['127.0.0.1', '::1'], true)) {
			return false;
		}

		// A 200 from /@vite/client means it is Vite on the port, not another local
		// app holding it; a refused connection fails fast, well inside the timeout.
		// GET, not HEAD: Vite answers HEAD on /@vite/client with a 404.
		$response = wp_remote_get('http://localhost:' . self::PORT . '/@vite/client', ['timeout' => 0.5, 'limit_response_size' => 1024]);
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return false;
		}

		// No version on either dev-server script: Vite serves them uncached, and a
		// ?ver= query would change the module URL HMR tracks.
		wp_enqueue_script('vite-client', 'http://localhost:' . self::PORT . '/@vite/client', [], null, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Local Vite dev server; see above.

		// Add type="module" to the tags WordPress printed for the two dev-server scripts
		add_filter('script_loader_tag', function ($tag, $handle, $src) {
			if ($handle === 'vite-client' || $handle === 'enrove-tailwind-vite') {
				$tag = preg_replace('/ type=([\'"])text\/javascript\1/', '', $tag);
				return preg_replace('/ src=/', ' type="module" src=', $tag, 1);
			}
			return $tag;
		}, 10, 3);

		// Our tailwind entry, straight from the Vite dev server
		wp_enqueue_script('enrove-tailwind-vite', 'http://localhost:' . self::PORT . '/assets/css/tailwind.css', [], null, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Local Vite dev server; see above.

		return true;
	}
}
