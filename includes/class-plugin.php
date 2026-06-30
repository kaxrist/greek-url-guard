<?php
/**
 * Main plugin container.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the core services.
 */
final class Plugin {
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
	 */
	public function __construct() {
		$this->settings       = new Settings();
		$this->slug_generator = new Slug_Generator();
	}

	/**
	 * Registers plugin hooks.
	 *
	 * @return void
	 */
	public function register() {
		( new Post_Slug_Service( $this->settings, $this->slug_generator ) )->register();
		( new Term_Slug_Service( $this->settings, $this->slug_generator ) )->register();
		( new Media_File_Service( $this->settings, $this->slug_generator ) )->register();

		if ( is_admin() ) {
			( new Admin( $this->settings, $this->slug_generator, new Health_Check() ) )->register();
		}
	}

	/**
	 * Gets settings.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Gets slug generator.
	 *
	 * @return Slug_Generator
	 */
	public function slug_generator() {
		return $this->slug_generator;
	}
}
