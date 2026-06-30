<?php
/**
 * Term slug hooks.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies Greeklish slugs to new public terms.
 */
final class Term_Slug_Service {
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
		add_filter( 'wp_insert_term_data', array( $this, 'filter_insert_term_data' ), 10, 3 );
	}

	/**
	 * Generates a slug for new public terms without a manual slug.
	 *
	 * @param array<string, mixed> $data     Term data.
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string, mixed> $args     Insert args.
	 * @return array<string, mixed>
	 */
	public function filter_insert_term_data( $data, $taxonomy, $args ) {
		if ( ! $this->is_enabled_for_taxonomy( $taxonomy ) ) {
			return $data;
		}

		$manual_slug = isset( $args['slug'] ) ? trim( (string) wp_unslash( $args['slug'] ) ) : '';

		if ( '' !== $manual_slug ) {
			if ( $this->settings->get( 'preserve_manual_slugs' ) || ! $this->contains_greek( $manual_slug ) ) {
				return $data;
			}

			$slug = $this->slug_generator->make_slug( rawurldecode( $manual_slug ), (int) $this->settings->get( 'max_slug_length' ) );

			if ( '' === $slug ) {
				return $data;
			}

			$data['slug'] = wp_unique_term_slug(
				$slug,
				(object) array(
					'taxonomy' => $taxonomy,
					'parent'   => isset( $args['parent'] ) ? absint( $args['parent'] ) : 0,
				)
			);

			return $data;
		}

		$name = isset( $data['name'] ) ? wp_unslash( $data['name'] ) : '';

		if ( '' === trim( (string) $name ) ) {
			return $data;
		}

		$slug = $this->slug_generator->make_slug( $name, (int) $this->settings->get( 'max_slug_length' ) );

		if ( '' === $slug ) {
			return $data;
		}

		$data['slug'] = wp_unique_term_slug(
			$slug,
			(object) array(
				'taxonomy' => $taxonomy,
				'parent'   => isset( $args['parent'] ) ? absint( $args['parent'] ) : 0,
			)
		);

		return $data;
	}

	/**
	 * Determines whether a taxonomy is handled.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	private function is_enabled_for_taxonomy( $taxonomy ) {
		if ( ! $this->settings->get( 'enabled' ) || ! $this->settings->get( 'enable_taxonomies' ) ) {
			return false;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		if ( ! $taxonomy_object || empty( $taxonomy_object->public ) ) {
			return false;
		}

		if ( $this->is_woocommerce_taxonomy( $taxonomy ) && ! $this->settings->get( 'enable_woocommerce' ) ) {
			return false;
		}

		$enabled = ! in_array( $taxonomy, array( 'nav_menu', 'link_category', 'post_format' ), true );

		/**
		 * Filters whether Greek URL Guard handles a taxonomy.
		 *
		 * @param bool   $enabled  Whether the taxonomy is enabled.
		 * @param string $taxonomy Taxonomy.
		 */
		return (bool) apply_filters( 'greek_url_guard_handle_taxonomy', $enabled, $taxonomy );
	}

	/**
	 * Checks whether a taxonomy belongs to WooCommerce product URLs.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	private function is_woocommerce_taxonomy( $taxonomy ) {
		return 0 === strpos( $taxonomy, 'product_' ) || 0 === strpos( $taxonomy, 'pa_' );
	}

	/**
	 * Checks whether text contains Greek characters, including percent-encoded slugs.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private function contains_greek( $text ) {
		return 1 === preg_match( '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u', rawurldecode( (string) $text ) );
	}
}
