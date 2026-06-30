<?php
/**
 * Settings repository.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and sanitizes core plugin settings.
 */
final class Settings {
	const OPTION_NAME   = 'greek_url_guard_settings';
	const SETTING_GROUP = 'greek_url_guard_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults() {
		return array(
			'enabled'                  => true,
			'max_slug_length'          => 70,
			'max_filename_length'      => 70,
			'post_types'               => array( 'post', 'page' ),
			'enable_posts_pages'       => true,
			'enable_public_cpts'       => true,
			'enable_taxonomies'        => true,
			'enable_media'             => true,
			'enable_woocommerce'       => class_exists( '\WooCommerce' ),
			'preserve_manual_slugs'    => true,
			'remove_data_on_uninstall' => false,
		);
	}

	/**
	 * Gets all settings with defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	public function all() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return $this->sanitize( array_merge( $this->defaults(), $settings ) );
	}

	/**
	 * Gets a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( $key ) {
		$settings = $this->all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	/**
	 * Registers the settings option.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			self::SETTING_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => $this->defaults(),
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitizes settings.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $settings ) {
		$settings           = is_array( $settings ) ? $settings : array();
		$woocommerce_active = class_exists( '\WooCommerce' );

		$max_slug_length = isset( $settings['max_slug_length'] ) ? absint( $settings['max_slug_length'] ) : 70;
		$max_slug_length = max( 20, min( 150, $max_slug_length ) );

		$max_filename_length = isset( $settings['max_filename_length'] ) ? absint( $settings['max_filename_length'] ) : 70;
		$max_filename_length = max( 20, min( 150, $max_filename_length ) );

		$post_types = array();
		if ( isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ) {
			$post_types = array_map( 'sanitize_key', $settings['post_types'] );
		}

		$post_types = array_values( array_intersect( $post_types, array( 'post', 'page' ) ) );

		return array(
			'enabled'                  => ! empty( $settings['enabled'] ),
			'max_slug_length'          => $max_slug_length,
			'max_filename_length'      => $max_filename_length,
			'post_types'               => $post_types,
			'enable_posts_pages'       => ! empty( $settings['enable_posts_pages'] ),
			'enable_public_cpts'       => ! empty( $settings['enable_public_cpts'] ),
			'enable_taxonomies'        => ! empty( $settings['enable_taxonomies'] ),
			'enable_media'             => ! empty( $settings['enable_media'] ),
			'enable_woocommerce'       => $woocommerce_active && ! empty( $settings['enable_woocommerce'] ),
			'preserve_manual_slugs'    => ! empty( $settings['preserve_manual_slugs'] ),
			'remove_data_on_uninstall' => ! empty( $settings['remove_data_on_uninstall'] ),
		);
	}

	/**
	 * Gets the free post types selected by the site owner.
	 *
	 * @return string[]
	 */
	public function selected_post_types() {
		$settings = $this->all();

		return isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? array_values( array_intersect( $settings['post_types'], array( 'post', 'page' ) ) )
			: array();
	}
}
