<?php
/**
 * Plugin Name: Enrove Folios
 * Description: Create, manage, and publish beautiful digital literature directly within WordPress — ebooks, newsletters, product catalogs, portfolios, proposals, and more. Enrove Folios provides a robust foundation with custom themes, access permissions, dynamic previews, and a dedicated folio builder interface powered by the modern Block Editor.
 * Plugin URI: https://enrove.studioen.us/
 * Author: StudioEN
 * Version: 0.5.1
 * Author URI: https://studioen.us/
 * Requires at least: 5.9
 * Requires PHP: 7.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain: enrove-folios
 *
 * @package Enrove
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
$enrove_plugin_data = get_file_data(__FILE__, ['Version' => 'Version'], 'plugin');
define('ENROVE_VERSION', $enrove_plugin_data['Version']);
define('ENROVE__FILE__', __FILE__);
define('ENROVE_PLUGIN_BASE', plugin_basename(ENROVE__FILE__));
define('ENROVE_PATH', plugin_dir_path(ENROVE__FILE__));

if (defined('ENROVE_TESTS') && ENROVE_TESTS) {
	define('ENROVE_URL', 'file://' . ENROVE_PATH);
} else {
	define('ENROVE_URL', plugins_url('/', ENROVE__FILE__));
}

define('ENROVE_MODULES_PATH', plugin_dir_path(ENROVE__FILE__) . '/modules');
define('ENROVE_ASSETS_PATH', ENROVE_PATH . 'assets/');
define('ENROVE_ASSETS_URL', ENROVE_URL . 'assets/');

// This file must stay parseable on older PHP so the notice below can run;
// the rest of the plugin uses 7.1 syntax (nullable and void types).
if (!version_compare(PHP_VERSION, '7.1', '>=')) {
	add_action('admin_notices', 'enrove_fail_php_version');
} elseif (!version_compare(get_bloginfo('version'), '5.9', '>=')) {
	add_action('admin_notices', 'enrove_fail_wp_version');
} else {
	require_once ENROVE_PATH . 'includes/plugin.php';

	// Flush rewrite rules once on activation so /folio/ URLs resolve immediately.
	register_activation_hook(__FILE__, 'enrove_flush_rewrite_rules');
}

/**
 * Flush rewrite rules on plugin activation.
 * Required so the /folio/ URL rules added via add_rewrite_rule() take effect.
 */
function enrove_flush_rewrite_rules()
{
	// Keep this as a plain flush for forward compatibility.
	// Current folio routing is handled by plugin template interception.
	flush_rewrite_rules();
}

/**
 * Enrove admin notice for minimum PHP version.
 *
 * Warning when the site doesn't have the minimum required PHP version.
 *
 * @since 1.0.0
 *
 * @return void
 */
function enrove_fail_php_version()
{
	$message = sprintf(
		/* translators: 1: `<h3>` opening tag, 2: `</h3>` closing tag, 3: PHP version. 4: Link opening tag, 5: Link closing tag. */
		esc_html__('%1$sEnrove isn’t running because PHP is outdated.%2$s Update to PHP version %3$s and get back to creating! %4$sShow me how%5$s', 'enrove-folios'),
		'<h3>',
		'</h3>',
		'7.1',
		'<a href="https://wordpress.org/support/update-php/" target="_blank" rel="noopener noreferrer">',
		'</a>'
	);
	$html_message = sprintf('<div class="error">%s</div>', wpautop($message));
	echo wp_kses_post($html_message);
}

/**
 * Enrove admin notice for minimum WordPress version.
 *
 * Warning when the site doesn't have the minimum required WordPress version.
 *
 * @since 1.5.0
 *
 * @return void
 */
function enrove_fail_wp_version()
{
	$message = sprintf(
		/* translators: 1: `<h3>` opening tag, 2: `</h3>` closing tag, 3: WP version. 4: Link opening tag, 5: Link closing tag. */
		esc_html__('%1$sEnrove isn’t running because WordPress is outdated.%2$s Update to version %3$s and get back to creating! %4$sShow me how%5$s', 'enrove-folios'),
		'<h3>',
		'</h3>',
		'5.9',
		'<a href="https://wordpress.org/documentation/article/updating-wordpress/" target="_blank" rel="noopener noreferrer">',
		'</a>'
	);
	$html_message = sprintf('<div class="error">%s</div>', wpautop($message));
	echo wp_kses_post($html_message);
}
