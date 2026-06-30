<?php
/**
 * Plugin Name: Greek URL Guard
 * Description: Convert new Greek slugs and upload filenames into clean, SEO-friendly Greeklish without changing existing URLs.
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: CCDesign.gr
 * Author URI: https://ccdesign.gr
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: greek-url-guard
 * Domain Path: /languages
 *
 * @package Greek_URL_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GREEK_URL_GUARD_VERSION', '1.0.1' );
define( 'GREEK_URL_GUARD_PLUGIN_FILE', __FILE__ );
define( 'GREEK_URL_GUARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GREEK_URL_GUARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GREEK_URL_GUARD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-settings.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-slug-generator.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-post-slug-service.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-term-slug-service.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-media-file-service.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-health-check.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-admin.php';
require_once GREEK_URL_GUARD_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Returns the shared plugin instance.
 *
 * @return \Greek_URL_Guard\Plugin
 */
function greek_url_guard() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new \Greek_URL_Guard\Plugin();
	}

	return $plugin;
}

add_action(
	'plugins_loaded',
	static function () {
		greek_url_guard()->register();
	}
);
