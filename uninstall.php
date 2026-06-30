<?php
/**
 * Uninstall cleanup.
 *
 * @package Greek_URL_Guard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Determines whether plugin settings should be removed on uninstall.
 *
 * @return bool
 */
function greek_url_guard_should_remove_settings_on_uninstall() {
	$greek_url_guard_settings = get_option( 'greek_url_guard_settings', array() );

	return is_array( $greek_url_guard_settings ) && ! empty( $greek_url_guard_settings['remove_data_on_uninstall'] );
}

if ( greek_url_guard_should_remove_settings_on_uninstall() ) {
	delete_option( 'greek_url_guard_settings' );
}
