<?php
/**
 * Plugin Name: Groove
 * Description: 
 * Plugin URI: 
 * Author: groove.com
 * Version: 0.1.0
 * Author URI: 
 *
 * Text Domain: groove
 *
 * @package Groove
 *
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

define('GROOVE_VERSION', '0.1.0');

define('GROOVE__FILE__', __FILE__);
define('GROOVE_PLUGIN_BASE', plugin_basename(GROOVE__FILE__));
define('GROOVE_PATH', plugin_dir_path(GROOVE__FILE__));

if (defined('GROOVE_TESTS') && GROOVE_TESTS) {
	define('GROOVE_URL', 'file://' . GROOVE_PATH);
}
else {
	define('GROOVE_URL', plugins_url('/', GROOVE__FILE__));
}

define('GROOVE_MODULES_PATH', plugin_dir_path(GROOVE__FILE__) . '/modules');
define('GROOVE_ASSETS_PATH', GROOVE_PATH . 'assets/');
define('GROOVE_ASSETS_URL', GROOVE_URL . 'assets/');

add_action('plugins_loaded', 'groove_load_plugin_textdomain');

if (!version_compare(PHP_VERSION, '7.0', '>=')) {
	add_action('admin_notices', 'groove_fail_php_version');
}
elseif (!version_compare(get_bloginfo('version'), '5.9', '>=')) {
	add_action('admin_notices', 'groove_fail_wp_version');
}
else {
	require_once GROOVE_PATH . 'includes/plugin.php';

	// Flush rewrite rules once on activation so /folio/ URLs resolve immediately.
	register_activation_hook(__FILE__, 'groove_flush_rewrite_rules');
}

/**
 * Flush rewrite rules on plugin activation.
 * Required so the /folio/ URL rules added via add_rewrite_rule() take effect.
 */
function groove_flush_rewrite_rules()
{
	// Register the rules first, then flush.
	add_rewrite_rule('^folio/[^/]+/page/[^/]+/?$', 'index.php?post_type=groove_folio_page', 'top');
	add_rewrite_rule('^folio/[^/]+/?$', 'index.php?post_type=groove_folio', 'top');
	flush_rewrite_rules();
}

/**
 * Load Groove textdomain.
 *
 * Load gettext translate for Groove text domain.
 *
 * @since 1.0.0
 *
 * @return void
 */
function groove_load_plugin_textdomain()
{
	load_plugin_textdomain('groove');
}

/**
 * Groove admin notice for minimum PHP version.
 *
 * Warning when the site doesn't have the minimum required PHP version.
 *
 * @since 1.0.0
 *
 * @return void
 */
function groove_fail_php_version()
{
	$message = sprintf(
		/* translators: 1: `<h3>` opening tag, 2: `</h3>` closing tag, 3: PHP version. 4: Link opening tag, 5: Link closing tag. */
		esc_html__('%1$sGroove isn’t running because PHP is outdated.%2$s Update to PHP version %3$s and get back to creating! %4$sShow me how%5$s', 'groove'),
		'<h3>',
		'</h3>',
		'7.0',
		'<a href="..." target="_blank">',
		'</a>'
	);
	$html_message = sprintf('<div class="error">%s</div>', wpautop($message));
	echo wp_kses_post($html_message);
}

/**
 * Groove admin notice for minimum WordPress version.
 *
 * Warning when the site doesn't have the minimum required WordPress version.
 *
 * @since 1.5.0
 *
 * @return void
 */
function groove_fail_wp_version()
{
	$message = sprintf(
		/* translators: 1: `<h3>` opening tag, 2: `</h3>` closing tag, 3: WP version. 4: Link opening tag, 5: Link closing tag. */
		esc_html__('%1$Groove isn’t running because WordPress is outdated.%2$s Update to version %3$s and get back to creating! %4$sShow me how%5$s', 'groove'),
		'<h3>',
		'</h3>',
		'5.9',
		'<a href="..." target="_blank">',
		'</a>'
	);
	$html_message = sprintf('<div class="error">%s</div>', wpautop($message));
	echo wp_kses_post($html_message);
}