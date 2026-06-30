<?php
/**
 * Media filename hooks.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies Greeklish cleanup to new upload filenames.
 */
final class Media_File_Service {
	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Slug generator.
	 *
	 * @var Slug_Generator
	 */
	private $slug_generator;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings       Settings.
	 * @param Slug_Generator $slug_generator Slug generator.
	 */
	public function __construct( Settings $settings, Slug_Generator $slug_generator ) {
		$this->settings       = $settings;
		$this->slug_generator = $slug_generator;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'filter_upload_file' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'filter_upload_file' ) );
	}

	/**
	 * Cleans the filename for a new WordPress media upload.
	 *
	 * @param array<string, mixed> $file Upload file data.
	 * @return array<string, mixed>
	 */
	public function filter_upload_file( $file ) {
		if ( ! is_array( $file ) || empty( $file['name'] ) ) {
			return $file;
		}

		if ( ! $this->settings->get( 'enabled' ) || ! $this->settings->get( 'enable_media' ) || ! $this->should_handle_upload_file( $file ) ) {
			return $file;
		}

		$original_name = (string) $file['name'];
		$clean_name    = $this->slug_generator->make_filename(
			$original_name,
			$original_name,
			(int) $this->settings->get( 'max_filename_length' )
		);

		if ( '' !== $clean_name ) {
			$file['name'] = $clean_name;
		}

		return $file;
	}

	/**
	 * Checks whether the current upload belongs to media handling.
	 *
	 * @param array<string, mixed> $file Upload file data.
	 * @return bool
	 */
	private function should_handle_upload_file( $file ) {
		if ( $this->is_plugin_or_theme_package_upload() ) {
			return false;
		}

		$filename = isset( $file['name'] ) ? (string) $file['name'] : '';

		if ( '' === $filename ) {
			return false;
		}

		$filetype = wp_check_filetype( $filename );

		if ( empty( $filetype['ext'] ) ) {
			return false;
		}

		return $this->is_media_upload_context();
	}

	/**
	 * Avoids changing plugin, theme, and package upload filenames.
	 *
	 * @return bool
	 */
	private function is_plugin_or_theme_package_upload() {
		global $pagenow;

		$action = $this->request_action();

		if ( in_array( $action, array( 'upload-plugin', 'upload-theme', 'install-plugin', 'install-theme', 'update-plugin', 'update-theme' ), true ) ) {
			return true;
		}

		return in_array( (string) $pagenow, array( 'plugin-install.php', 'theme-install.php', 'update.php' ), true );
	}

	/**
	 * Detects common WordPress media upload contexts.
	 *
	 * @return bool
	 */
	private function is_media_upload_context() {
		global $pagenow;

		$action = $this->request_action();

		if ( in_array( $action, array( 'upload-attachment', 'upload_image', 'custom-header-upload', 'custom-background-upload' ), true ) ) {
			return true;
		}

		if ( in_array( (string) $pagenow, array( 'async-upload.php', 'media-upload.php', 'media-new.php' ), true ) ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$rest_route  = $this->request_value( INPUT_GET, 'rest_route' );
			$request_uri = $this->request_value( INPUT_SERVER, 'REQUEST_URI' );

			return false !== strpos( $rest_route, '/wp/v2/media' ) || false !== strpos( $request_uri, '/wp/v2/media' );
		}

		return false;
	}

	/**
	 * Returns the current request action without processing form data.
	 *
	 * @return string
	 */
	private function request_action() {
		$action = $this->request_value( INPUT_POST, 'action' );

		if ( '' === $action ) {
			$action = $this->request_value( INPUT_GET, 'action' );
		}

		return sanitize_key( $action );
	}

	/**
	 * Reads a request value for context detection only.
	 *
	 * @param int    $input_type INPUT_* constant.
	 * @param string $key        Request key.
	 * @return string
	 */
	private function request_value( $input_type, $key ) {
		$value = filter_input( $input_type, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( null === $value || false === $value ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}
}
