<?php
/**
 * Admin health checks.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds lightweight safety checks for the dashboard.
 */
final class Health_Check {
	const HEALTH_MATCH_LIMIT   = 20;
	const HEALTH_EXAMPLE_LIMIT = 3;
	const HEALTH_SCAN_LIMIT    = 500;

	/**
	 * Returns checks for the admin dashboard.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function checks() {
		$checks = array(
			$this->permalink_check(),
			$this->existing_greek_post_slug_check(),
			$this->existing_greek_term_slug_check(),
			$this->existing_greek_media_filename_check(),
			$this->custom_code_conflict_check(),
			$this->integration_summary_check(),
		);

		/**
		 * Filters dashboard health checks.
		 *
		 * @param array<int, array<string, string>> $checks Checks.
		 */
		return apply_filters( 'greek_url_guard_health_checks', $checks );
	}

	/**
	 * Checks permalink structure.
	 *
	 * @return array<string, string>
	 */
	private function permalink_check() {
		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			return array(
				'label'       => __( 'Permalinks', 'greek-url-guard' ),
				'status'      => 'warning',
				'description' => __( 'Pretty permalinks are disabled. Slug quality matters most when pretty permalinks are enabled.', 'greek-url-guard' ),
			);
		}

		return array(
			'label'       => __( 'Permalinks', 'greek-url-guard' ),
			'status'      => 'good',
			'description' => __( 'Pretty permalinks are enabled.', 'greek-url-guard' ),
		);
	}

	/**
	 * Checks whether old Greek post slugs exist without scanning full content.
	 *
	 * @return array<string, string>
	 */
	private function existing_greek_post_slug_check() {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Limited admin health check query with suppress_filters=false and no_found_rows.
		$post_ids = get_posts(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => self::HEALTH_SCAN_LIMIT,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$rows = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || ! $this->value_has_greek( $post->post_name ) ) {
				continue;
			}

			$rows[] = (object) array(
				'post_title' => $post->post_title,
				'post_name'  => $post->post_name,
			);

			if ( count( $rows ) > self::HEALTH_MATCH_LIMIT ) {
				break;
			}
		}

		return $this->existing_greek_post_slug_result( $rows );
	}

	/**
	 * Checks whether old Greek category, tag, or taxonomy slugs exist.
	 *
	 * @return array<string, string>
	 */
	private function existing_greek_term_slug_check() {
		$taxonomies = $this->public_taxonomies();

		if ( empty( $taxonomies ) ) {
			return $this->existing_greek_term_slug_result( array() );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'number'     => self::HEALTH_SCAN_LIMIT,
				'orderby'    => 'term_id',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$rows = array();

		foreach ( $terms as $term ) {
			if ( ! $this->value_has_greek( $term->slug ) ) {
				continue;
			}

			$rows[] = (object) array(
				'name'     => $term->name,
				'slug'     => $term->slug,
				'taxonomy' => $term->taxonomy,
			);

			if ( count( $rows ) > self::HEALTH_MATCH_LIMIT ) {
				break;
			}
		}

		return $this->existing_greek_term_slug_result( $rows );
	}

	/**
	 * Checks whether old Greek media filenames exist.
	 *
	 * @return array<string, string>
	 */
	private function existing_greek_media_filename_check() {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Limited admin health check query with suppress_filters=false and no_found_rows.
		$attachment_ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => self::HEALTH_SCAN_LIMIT,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$rows = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$file = get_post_meta( $attachment_id, '_wp_attached_file', true );

			if ( ! $this->value_has_greek( basename( (string) $file ) ) ) {
				continue;
			}

			$rows[] = (object) array(
				'file_path' => $file,
			);

			if ( count( $rows ) > self::HEALTH_MATCH_LIMIT ) {
				break;
			}
		}

		return $this->existing_greek_media_filename_result( $rows );
	}

	/**
	 * Builds the post slug check result.
	 *
	 * @param array<int, object> $rows Sample rows.
	 * @return array<string, string>
	 */
	private function existing_greek_post_slug_result( $rows ) {
		$summary = $this->sample_summary(
			$rows,
			static function ( $row ) {
				return '' !== (string) $row->post_title ? $row->post_title : $row->post_name;
			}
		);

		if ( $summary['found'] ) {
			return array(
				'label'       => __( 'Existing post/page slugs', 'greek-url-guard' ),
				'status'      => 'info',
				'description' => sprintf(
					/* translators: 1: bounded match count, 2: example post titles or slugs. */
					__( 'Existing Greek post or page slugs were found (%1$s). Examples: %2$s. Greek URL Guard does not rewrite old URLs automatically. Review them before changing anything, because redirects may be needed.', 'greek-url-guard' ),
					$summary['count_text'],
					$summary['examples']
				),
			);
		}

		return array(
			'label'       => __( 'Existing post/page slugs', 'greek-url-guard' ),
			'status'      => 'good',
			'description' => __( 'No Greek post or page slugs were detected in a lightweight check.', 'greek-url-guard' ),
		);
	}

	/**
	 * Builds the term slug check result.
	 *
	 * @param array<int, object> $rows Sample rows.
	 * @return array<string, string>
	 */
	private function existing_greek_term_slug_result( $rows ) {
		$summary = $this->sample_summary(
			$rows,
			static function ( $row ) {
				$name = trim( (string) $row->name );
				$slug = trim( rawurldecode( (string) $row->slug ) );

				if ( '' !== $name && '' !== $slug && $name !== $slug ) {
					$label = $name . ' -> ' . $slug;
				} else {
					$label = '' !== $slug ? $slug : $name;
				}

				return $label . ' (' . $row->taxonomy . ')';
			}
		);

		if ( $summary['found'] ) {
			return array(
				'label'       => __( 'Existing category/tag slugs', 'greek-url-guard' ),
				'status'      => 'info',
				'description' => sprintf(
					/* translators: 1: bounded match count, 2: example term names or slugs. */
					__( 'Existing categories, tags, or other public taxonomy items with Greek slugs were found (%1$s). Examples: %2$s. New items are cleaned automatically; old URLs are left unchanged.', 'greek-url-guard' ),
					$summary['count_text'],
					$summary['examples']
				),
			);
		}

		return array(
			'label'       => __( 'Existing category/tag slugs', 'greek-url-guard' ),
			'status'      => 'good',
			'description' => __( 'No Greek category, tag, or taxonomy slugs were detected in a lightweight check.', 'greek-url-guard' ),
		);
	}

	/**
	 * Builds the media filename check result.
	 *
	 * @param array<int, object> $rows Sample rows.
	 * @return array<string, string>
	 */
	private function existing_greek_media_filename_result( $rows ) {
		$summary = $this->sample_summary(
			$rows,
			static function ( $row ) {
				return basename( (string) $row->file_path );
			}
		);

		if ( $summary['found'] ) {
			return array(
				'label'       => __( 'Media filenames', 'greek-url-guard' ),
				'status'      => 'info',
				'description' => sprintf(
					/* translators: 1: bounded match count, 2: example media filenames. */
					__( 'Existing media files with Greek characters in the filename were found (%1$s). Examples: %2$s. New uploads are cleaned automatically; old files are not renamed.', 'greek-url-guard' ),
					$summary['count_text'],
					$summary['examples']
				),
			);
		}

		return array(
			'label'       => __( 'Media filenames', 'greek-url-guard' ),
			'status'      => 'good',
			'description' => __( 'No Greek media filenames were detected in a lightweight check.', 'greek-url-guard' ),
		);
	}

	/**
	 * Builds a compact bounded sample summary.
	 *
	 * @param array<int, object> $rows           Sample rows.
	 * @param callable           $label_callback Callback that returns a display label.
	 * @return array{found: bool, count_text: string, examples: string}
	 */
	private function sample_summary( $rows, $label_callback ) {
		$rows     = array_values( (array) $rows );
		$has_more = count( $rows ) > self::HEALTH_MATCH_LIMIT;

		if ( $has_more ) {
			$rows = array_slice( $rows, 0, self::HEALTH_MATCH_LIMIT );
		}

		if ( empty( $rows ) ) {
			return array(
				'found'      => false,
				'count_text' => '',
				'examples'   => '',
			);
		}

		$labels = array();

		foreach ( array_slice( $rows, 0, self::HEALTH_EXAMPLE_LIMIT ) as $row ) {
			$label = $this->compact_sample_label( call_user_func( $label_callback, $row ) );

			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}

		if ( empty( $labels ) ) {
			$labels[] = __( 'matching records found', 'greek-url-guard' );
		}

		return array(
			'found'      => true,
			'count_text' => $this->health_count_text( count( $rows ), $has_more ),
			'examples'   => implode( ', ', $labels ),
		);
	}

	/**
	 * Formats a bounded match count.
	 *
	 * @param int  $count    Count inside the bounded sample.
	 * @param bool $has_more Whether more rows exist beyond the sample.
	 * @return string
	 */
	private function health_count_text( $count, $has_more ) {
		if ( $has_more ) {
			return sprintf(
				/* translators: %d: bounded match count. */
				__( 'at least %d matches', 'greek-url-guard' ),
				self::HEALTH_MATCH_LIMIT
			);
		}

		return sprintf(
			/* translators: %d: match count. */
			_n( '%d match', '%d matches', $count, 'greek-url-guard' ),
			$count
		);
	}

	/**
	 * Normalizes a sample label for compact admin display.
	 *
	 * @param mixed $label Label.
	 * @return string
	 */
	private function compact_sample_label( $label ) {
		$label = wp_strip_all_tags( rawurldecode( (string) $label ) );
		$label = preg_replace( '/\s+/', ' ', $label );
		$label = trim( null === $label ? '' : $label );

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $label ) > 64 ? mb_substr( $label, 0, 61 ) . '...' : $label;
		}

		return strlen( $label ) > 64 ? substr( $label, 0, 61 ) . '...' : $label;
	}

	/**
	 * Checks whether custom code appears to alter the same slug hooks.
	 *
	 * @return array<string, string>
	 */
	private function custom_code_conflict_check() {
		$matches = array_merge(
			$this->theme_file_matches(),
			$this->runtime_hook_matches()
		);

		$matches = array_values( array_unique( array_filter( $matches ) ) );

		if ( ! empty( $matches ) ) {
			return array(
				'label'       => __( 'Custom slug code', 'greek-url-guard' ),
				'status'      => 'warning',
				'description' => sprintf(
					/* translators: %s: comma-separated list of active custom code matches. */
					__( 'Potential active custom code that may alter Greek slugs or filenames was detected: %s. Review it before enabling Greek URL Guard automations.', 'greek-url-guard' ),
					implode( ', ', array_slice( $matches, 0, 6 ) )
				),
			);
		}

		return array(
			'label'       => __( 'Custom slug code', 'greek-url-guard' ),
			'status'      => 'good',
			'description' => __( 'No active snippet, theme file, custom file, or runtime hook that looks like a Greek slug or filename converter was detected.', 'greek-url-guard' ),
		);
	}

	/**
	 * Finds likely code in active theme files.
	 *
	 * @return string[]
	 */
	private function theme_file_matches() {
		$matches = array();
		$files   = array();

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$files[] = trailingslashit( get_stylesheet_directory() ) . 'functions.php';
		}

		if ( function_exists( 'get_template_directory' ) ) {
			$files[] = trailingslashit( get_template_directory() ) . 'functions.php';
		}

		foreach ( array_unique( $files ) as $file ) {
			if ( ! $this->file_looks_like_slug_converter( $file ) ) {
				continue;
			}

			$matches[] = basename( dirname( $file ) ) . '/functions.php';
		}

		return $matches;
	}

	/**
	 * Finds runtime callbacks on slug-related hooks.
	 *
	 * @return string[]
	 */
	private function runtime_hook_matches() {
		global $wp_filter;

		$matches = array();

		foreach ( $this->slug_hook_needles() as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = isset( $callback['function'] ) ? $callback['function'] : null;
					$file     = $this->callback_file( $function );
					$label    = $this->callback_label( $function );

					if ( '' === $file && $this->contains_any( strtolower( $label ), $this->slug_marker_needles() ) ) {
						$matches[] = $label . ' on ' . $hook;
						continue;
					}

					if ( '' === $file || $this->is_ignored_callback_file( $file ) ) {
						continue;
					}

					if ( ! $this->file_looks_like_slug_converter( $file ) && ! $this->path_looks_relevant( $file ) && ! $this->contains_any( strtolower( $label ), $this->slug_marker_needles() ) ) {
						continue;
					}

					$matches[] = $this->relative_path_label( $file ) . ' on ' . $hook;
				}
			}
		}

		return $matches;
	}

	/**
	 * Needles for relevant hook names.
	 *
	 * @return string[]
	 */
	private function slug_hook_needles() {
		return array(
			'wp_insert_post_data',
			'wp_insert_term_data',
			'wp_handle_upload_prefilter',
			'wp_handle_sideload_prefilter',
			'sanitize_title',
			'wp_unique_post_slug',
			'wp_unique_term_slug',
		);
	}

	/**
	 * Needles for likely Greek slug conversion code.
	 *
	 * @return string[]
	 */
	private function slug_marker_needles() {
		return array(
			'greeklish',
			'transliterat',
			'greek slug',
			'greek url',
			'greek permalink',
			'greek filename',
			'greek character',
			'elot',
			'iso 843',
			'romaniz',
			$this->greek_marker(),
		);
	}

	/**
	 * Returns a Greek character regex without relying on source-file encoding.
	 *
	 * @return string
	 */
	private function greek_character_regex() {
		return (string) json_decode( '"[\\u0386-\\u03CE\\u0370-\\u03FF\\u1F00-\\u1FFF]"' );
	}

	/**
	 * Returns the Greek word marker for "Greek" without relying on source-file encoding.
	 *
	 * @return string
	 */
	private function greek_marker() {
		return (string) json_decode( '"\\u03B5\\u03BB\\u03BB\\u03B7\\u03BD"' );
	}

	/**
	 * Returns public taxonomies worth checking for existing term slug issues.
	 *
	 * @return string[]
	 */
	private function public_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$taxonomies = is_array( $taxonomies ) ? array_values( $taxonomies ) : array();

		return array_values( array_diff( $taxonomies, array( 'nav_menu', 'link_category', 'post_format' ) ) );
	}

	/**
	 * Checks whether a stored value contains Greek text or percent-encoded Greek bytes.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	private function value_has_greek( $value ) {
		$value   = (string) $value;
		$decoded = rawurldecode( $value );

		if ( 1 === preg_match( '/' . $this->greek_character_regex() . '/u', $decoded ) ) {
			return true;
		}

		$lower = strtolower( $value );

		foreach ( array( '%cd', '%ce', '%cf', '%e1' ) as $encoded_byte ) {
			if ( false !== strpos( $lower, $encoded_byte ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the PHP file for a hook callback when possible.
	 *
	 * @param mixed $callback Hook callback.
	 * @return string
	 */
	private function callback_file( $callback ) {
		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
				return (string) $reflection->getFileName();
			}

			if ( $callback instanceof \Closure ) {
				$reflection = new \ReflectionFunction( $callback );
				return (string) $reflection->getFileName();
			}

			if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
				return (string) $reflection->getFileName();
			}
		} catch ( \ReflectionException $e ) {
			return '';
		}

		return '';
	}

	/**
	 * Gets a readable hook callback label.
	 *
	 * @param mixed $callback Hook callback.
	 * @return string
	 */
	private function callback_label( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( $callback instanceof \Closure ) {
			return 'closure';
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $class . '::' . (string) $callback[1];
		}

		return '';
	}

	/**
	 * Checks whether a callback file should be ignored.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	private function is_ignored_callback_file( $file ) {
		$normalized = wp_normalize_path( $file );

		if ( false !== strpos( $normalized, wp_normalize_path( GREEK_URL_GUARD_PLUGIN_DIR ) ) ) {
			return true;
		}

		return defined( 'ABSPATH' ) && false !== strpos( $normalized, wp_normalize_path( ABSPATH . WPINC ) );
	}

	/**
	 * Checks whether a file content looks like overlapping slug conversion code.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	private function file_looks_like_slug_converter( $file ) {
		if ( ! is_readable( $file ) || filesize( $file ) > 524288 ) {
			return false;
		}

		$contents = $this->get_local_file_contents( $file );

		if ( false === $contents ) {
			return false;
		}

		$contents = strtolower( $contents );

		return $this->contains_any( $contents, $this->slug_hook_needles() )
			&& (
				$this->contains_any( $contents, $this->slug_marker_needles() )
				|| $this->value_has_greek( $contents )
			);
	}

	/**
	 * Checks whether a path itself looks relevant.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	private function path_looks_relevant( $file ) {
		return $this->contains_any( strtolower( wp_normalize_path( $file ) ), $this->slug_marker_needles() );
	}

	/**
	 * Checks whether haystack contains any needle.
	 *
	 * @param string   $haystack Haystack.
	 * @param string[] $needles  Needles.
	 * @return bool
	 */
	private function contains_any( $haystack, $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $haystack, strtolower( $needle ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns a compact path label.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	private function relative_path_label( $file ) {
		$normalized = wp_normalize_path( $file );

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_dir = wp_normalize_path( WP_CONTENT_DIR );

			if ( 0 === strpos( $normalized, $content_dir ) ) {
				return ltrim( substr( $normalized, strlen( $content_dir ) ), '/' );
			}
		}

		return basename( $file );
	}

	/**
	 * Builds one compact integration summary instead of separate neutral rows.
	 *
	 * @return array<string, string>
	 */
	private function integration_summary_check() {
		$integrations = array(
			'WooCommerce'    => array( 'woocommerce/woocommerce.php', class_exists( '\WooCommerce' ) ),
			'Elementor'      => array( 'elementor/elementor.php', class_exists( '\Elementor\Plugin' ) ),
			'Divi'           => array( 'divi-builder/divi-builder.php', defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_setup_theme' ) ),
			'WPBakery'       => array( 'js_composer/js_composer.php', defined( 'WPB_VC_VERSION' ) ),
			'Beaver Builder' => array( 'bb-plugin/fl-builder.php', class_exists( '\FLBuilder' ) ),
			'Oxygen'         => array( 'oxygen/functions.php', defined( 'CT_VERSION' ) ),
			'Bricks'         => array( '', defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Theme' ) ),
			'WPML'           => array( 'sitepress-multilingual-cms/sitepress.php', defined( 'ICL_SITEPRESS_VERSION' ) ),
			'Polylang'       => array( 'polylang/polylang.php', function_exists( 'pll_languages_list' ) ),
			'TranslatePress' => array( 'translatepress-multilingual/index.php', class_exists( '\TRP_Translate_Press' ) ),
			'Weglot'         => array( 'weglot/weglot.php', function_exists( 'weglot_get_service' ) ),
		);

		$detected = array();

		foreach ( $integrations as $name => $integration ) {
			if ( $this->integration_is_active( (string) $integration[0], (bool) $integration[1] ) ) {
				$detected[] = $name;
			}
		}

		if ( empty( $detected ) ) {
			return array(
				'label'       => __( 'Integrations', 'greek-url-guard' ),
				'status'      => 'neutral',
				'description' => __( 'No WooCommerce, page builder, or translation plugin was detected. No action is needed.', 'greek-url-guard' ),
			);
		}

		return array(
			'label'       => __( 'Integrations', 'greek-url-guard' ),
			'status'      => 'info',
			'description' => sprintf(
				/* translators: %s: comma-separated list of detected integrations. */
				__( 'Detected: %s. Greek URL Guard only works on slugs and new upload filenames; it does not edit builder content or translated strings.', 'greek-url-guard' ),
				implode( ', ', $detected )
			),
		);
	}

	/**
	 * Checks a plugin basename and fallback signal.
	 *
	 * @param string $plugin_file   Plugin basename.
	 * @param bool   $fallback_bool Fallback detection result.
	 * @return bool
	 */
	private function integration_is_active( $plugin_file, $fallback_bool ) {
		if ( $fallback_bool ) {
			return true;
		}

		return '' !== $plugin_file && function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );
	}

	/**
	 * Gets local file contents through the WordPress filesystem API.
	 *
	 * @param string $file File path.
	 * @return string|false
	 */
	private function get_local_file_contents( $file ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return false;
		}

		return $wp_filesystem->get_contents( $file );
	}
}
