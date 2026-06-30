<?php
/**
 * Post and page slug hooks.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies Greeklish slugs to new post-like content.
 */
final class Post_Slug_Service {
	/**
	 * Whether an after-insert slug repair is running.
	 *
	 * @var bool
	 */
	private $repairing_empty_slug = false;

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
		add_filter( 'wp_insert_post_data', array( $this, 'filter_insert_post_data' ), 10, 4 );
		add_action( 'wp_after_insert_post', array( $this, 'repair_empty_slug_after_insert' ), 100, 4 );
	}

	/**
	 * Generates a slug when WordPress is creating content without a manual slug.
	 *
	 * @param array<string, mixed> $data                Sanitized post data.
	 * @param array<string, mixed> $postarr             Raw post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @param bool                 $update              Whether this is an update.
	 * @return array<string, mixed>
	 */
	public function filter_insert_post_data( $data, $postarr, $unsanitized_postarr = array(), $update = false ) {
		unset( $update );

		if ( $this->repairing_empty_slug ) {
			return $data;
		}

		if ( empty( $data['post_type'] ) || ! $this->is_enabled_for_post_type( (string) $data['post_type'] ) ) {
			return $data;
		}

		$title = isset( $unsanitized_postarr['post_title'] )
			? wp_unslash( $unsanitized_postarr['post_title'] )
			: wp_unslash( isset( $data['post_title'] ) ? $data['post_title'] : '' );

		$title = trim( (string) $title );

		if ( '' === $title || $this->is_auto_draft_request( $data, $title ) ) {
			return $data;
		}

		$forced_slug_source = $this->forced_slug_source( $data, $postarr, $unsanitized_postarr );

		if ( '' !== $forced_slug_source ) {
			return $this->set_unique_slug_from_source( $data, $postarr, $forced_slug_source );
		}

		if ( $this->has_manual_or_existing_slug( $data, $postarr, $unsanitized_postarr ) ) {
			return $data;
		}

		return $this->set_unique_slug_from_source( $data, $postarr, $title );
	}

	/**
	 * Repairs editor-generated draft slugs after a real save.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        Post object after insert.
	 * @param bool          $update      Whether this was an update.
	 * @param \WP_Post|null $post_before Post object before update.
	 * @return void
	 */
	public function repair_empty_slug_after_insert( $post_id, $post, $update, $post_before ) {
		unset( $update );

		if ( $this->repairing_empty_slug ) {
			return;
		}

		$post_id = absint( $post_id );

		if ( $post_id <= 0 ) {
			return;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || empty( $post->post_type ) || ! $this->is_enabled_for_post_type( (string) $post->post_type ) ) {
			return;
		}

		$status = isset( $post->post_status ) ? (string) $post->post_status : '';

		if ( in_array( $status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return;
		}

		$title = trim( (string) $post->post_title );

		if ( '' === $title || $this->is_auto_draft_request( array( 'post_status' => $status ), $title ) ) {
			return;
		}

		$current_slug            = trim( (string) $post->post_name );
		$was_unpublished         = is_object( $post_before ) && isset( $post_before->post_status )
			? $this->is_unpublished_status( (string) $post_before->post_status )
			: $this->is_unpublished_status( $status );
		$replaceable_placeholder = $this->is_auto_draft_placeholder_slug( $current_slug )
			|| ( $was_unpublished && $this->is_generated_id_placeholder_slug( $current_slug, $post_id ) );
		$replaceable_greek_slug  = $was_unpublished && $this->is_likely_auto_generated_greek_slug_for_title( $current_slug, $title );

		if ( '' !== $current_slug && ! $replaceable_placeholder && ! $replaceable_greek_slug ) {
			return;
		}

		$slug = $this->slug_generator->make_slug( $title, (int) $this->settings->get( 'max_slug_length' ) );

		if ( '' === $slug ) {
			return;
		}

		$unique_slug = wp_unique_post_slug(
			$slug,
			$post_id,
			$status,
			(string) $post->post_type,
			isset( $post->post_parent ) ? absint( $post->post_parent ) : 0
		);

		if ( '' === $unique_slug || $unique_slug === $current_slug ) {
			return;
		}

		$this->repairing_empty_slug = true;

		try {
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $unique_slug,
				)
			);
		} finally {
			$this->repairing_empty_slug = false;
		}
	}

	/**
	 * Determines whether the current post type is handled.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function is_enabled_for_post_type( $post_type ) {
		if ( ! $this->settings->get( 'enabled' ) ) {
			return false;
		}

		$post_types = array();

		if ( $this->settings->get( 'enable_posts_pages' ) ) {
			$post_types = array_merge( $post_types, $this->settings->selected_post_types() );
		}

		if ( $this->settings->get( 'enable_public_cpts' ) ) {
			$public_post_types = get_post_types( array( 'public' => true ), 'names' );
			unset( $public_post_types['attachment'], $public_post_types['post'], $public_post_types['page'], $public_post_types['product'] );

			$post_types = array_merge( $post_types, array_values( $public_post_types ) );
		}

		if ( $this->settings->get( 'enable_woocommerce' ) ) {
			$post_types[] = 'product';
		}

		/**
		 * Allows custom integrations to extend handled post types.
		 *
		 * @param string[] $post_types Core handled post types.
		 * @param string   $post_type  Current post type.
		 */
		$post_types = apply_filters( 'greek_url_guard_allowed_post_types', $post_types, $post_type );
		$post_types = array_map( 'sanitize_key', is_array( $post_types ) ? $post_types : array() );
		$post_types = array_unique( $post_types );

		return in_array( $post_type, $post_types, true );
	}

	/**
	 * Detects manual or existing slugs so old URLs are not changed silently.
	 *
	 * @param array<string, mixed> $data                Sanitized post data.
	 * @param array<string, mixed> $postarr             Raw post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @return bool
	 */
	private function has_manual_or_existing_slug( $data, $postarr, $unsanitized_postarr ) {
		$post_id       = ! empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		$current_state = $this->stored_or_current_status( $data, $post_id );

		if ( $this->settings->get( 'preserve_manual_slugs' ) ) {
			$incoming_slug = '';

			if ( isset( $unsanitized_postarr['post_name'] ) ) {
				$incoming_slug = trim( (string) wp_unslash( $unsanitized_postarr['post_name'] ) );
			} elseif ( isset( $postarr['post_name'] ) ) {
				$incoming_slug = trim( (string) wp_unslash( $postarr['post_name'] ) );
			}

			if ( '' !== $incoming_slug && ! $this->is_auto_draft_placeholder_slug( $incoming_slug ) ) {
				$is_generated_id_placeholder = $this->is_generated_id_placeholder_slug( $incoming_slug, $post_id );

				if ( $this->is_unpublished_status( $current_state ) && $is_generated_id_placeholder ) {
					return false;
				}

				if ( $this->is_likely_auto_generated_greek_slug( $incoming_slug, $data, $postarr, $unsanitized_postarr ) ) {
					return false;
				}

				return true;
			}
		}

		if ( $post_id > 0 ) {
			$existing_post = get_post( $post_id );

			if ( $existing_post && '' !== (string) $existing_post->post_name && ! $this->is_auto_draft_placeholder_slug( (string) $existing_post->post_name ) ) {
				$existing_status             = (string) $existing_post->post_status;
				$is_generated_id_placeholder = $this->is_generated_id_placeholder_slug( (string) $existing_post->post_name, $post_id );
				$is_generated_title_slug     = $this->is_likely_auto_generated_greek_slug( (string) $existing_post->post_name, $data, $postarr, $unsanitized_postarr );

				if ( $this->is_unpublished_status( $existing_status ) && ( $is_generated_id_placeholder || $is_generated_title_slug ) ) {
					return false;
				}

				return true;
			}
		}

		if ( empty( $data['post_name'] ) || $this->is_auto_draft_placeholder_slug( (string) $data['post_name'] ) ) {
			return false;
		}

		return ! $this->is_likely_auto_generated_greek_slug( (string) $data['post_name'], $data, $postarr, $unsanitized_postarr );
	}

	/**
	 * Finds a manual/existing Greek slug that should be converted when preservation is disabled.
	 *
	 * @param array<string, mixed> $data                Sanitized post data.
	 * @param array<string, mixed> $postarr             Raw post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @return string
	 */
	private function forced_slug_source( $data, $postarr, $unsanitized_postarr ) {
		if ( $this->settings->get( 'preserve_manual_slugs' ) ) {
			return '';
		}

		$candidates = array(
			$this->incoming_slug( $postarr, $unsanitized_postarr ),
			isset( $data['post_name'] ) ? (string) $data['post_name'] : '',
		);

		$post_id = ! empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;

		if ( $post_id > 0 ) {
			$existing_post = get_post( $post_id );

			if ( $existing_post ) {
				$candidates[] = (string) $existing_post->post_name;
			}
		}

		foreach ( $candidates as $candidate ) {
			$candidate = trim( (string) $candidate );

			if ( '' !== $candidate && ! $this->is_auto_draft_placeholder_slug( $candidate ) && $this->contains_greek( $candidate ) ) {
				return rawurldecode( $candidate );
			}
		}

		return '';
	}

	/**
	 * Gets an incoming slug from raw post arrays.
	 *
	 * @param array<string, mixed> $postarr             Raw post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @return string
	 */
	private function incoming_slug( $postarr, $unsanitized_postarr ) {
		if ( isset( $unsanitized_postarr['post_name'] ) ) {
			return trim( (string) wp_unslash( $unsanitized_postarr['post_name'] ) );
		}

		if ( isset( $postarr['post_name'] ) ) {
			return trim( (string) wp_unslash( $postarr['post_name'] ) );
		}

		return '';
	}

	/**
	 * Sets a unique slug from source text.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post data.
	 * @param string               $source  Source text.
	 * @return array<string, mixed>
	 */
	private function set_unique_slug_from_source( $data, $postarr, $source ) {
		$slug = $this->slug_generator->make_slug( $source, (int) $this->settings->get( 'max_slug_length' ) );

		if ( '' === $slug ) {
			return $data;
		}

		$post_id = ! empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;

		$data['post_name'] = wp_unique_post_slug(
			$slug,
			$post_id,
			isset( $data['post_status'] ) ? (string) $data['post_status'] : 'draft',
			(string) $data['post_type'],
			isset( $data['post_parent'] ) ? absint( $data['post_parent'] ) : 0
		);

		return $data;
	}

	/**
	 * Detects a Greek slug WordPress generated from the title, not one typed by an editor.
	 *
	 * @param string               $slug                Candidate slug.
	 * @param array<string, mixed> $data                Sanitized post data.
	 * @param array<string, mixed> $postarr             Raw post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @return bool
	 */
	private function is_likely_auto_generated_greek_slug( $slug, $data, $postarr, $unsanitized_postarr ) {
		$title = $this->current_title( $data, $postarr, $unsanitized_postarr );

		return $this->is_likely_auto_generated_greek_slug_for_title( $slug, $title );
	}

	/**
	 * Detects a Greek slug WordPress generated from a known title.
	 *
	 * @param string $slug  Candidate slug.
	 * @param string $title Current title.
	 * @return bool
	 */
	private function is_likely_auto_generated_greek_slug_for_title( $slug, $title ) {
		if ( ! $this->contains_greek( $slug ) ) {
			return false;
		}

		$title = trim( (string) $title );

		if ( '' === $title ) {
			return false;
		}

		$normalized_slug  = $this->normalize_slug_for_compare( $slug );
		$normalized_title = $this->normalize_slug_for_compare( $title );

		if ( $this->slug_matches_title( $normalized_slug, $normalized_title ) ) {
			return true;
		}

		$max_length      = (int) $this->settings->get( 'max_slug_length' );
		$greeklish_slug  = $this->slug_generator->make_slug( rawurldecode( (string) $slug ), $max_length );
		$greeklish_title = $this->slug_generator->make_slug( $title, $max_length );

		return $this->slug_matches_title( $greeklish_slug, $greeklish_title );
	}

	/**
	 * Compares a candidate slug with a generated title slug.
	 *
	 * @param string $slug  Candidate slug.
	 * @param string $title Generated title slug.
	 * @return bool
	 */
	private function slug_matches_title( $slug, $title ) {
		$slug  = trim( (string) $slug, '-' );
		$title = trim( (string) $title, '-' );

		if ( '' === $slug || '' === $title ) {
			return false;
		}

		if ( $slug === $title ) {
			return true;
		}

		$slug_without_suffix = preg_replace( '/-\d+$/', '', $slug );

		return is_string( $slug_without_suffix ) && $slug_without_suffix === $title;
	}

	/**
	 * Gets the best available current title.
	 *
	 * @param array<string, mixed> $data                Sanitized post data.
	 * @param array<string, mixed> $postarr             Raw post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @return string
	 */
	private function current_title( $data, $postarr, $unsanitized_postarr ) {
		if ( isset( $unsanitized_postarr['post_title'] ) ) {
			return trim( (string) wp_unslash( $unsanitized_postarr['post_title'] ) );
		}

		if ( isset( $postarr['post_title'] ) ) {
			return trim( (string) wp_unslash( $postarr['post_title'] ) );
		}

		return trim( (string) wp_unslash( isset( $data['post_title'] ) ? $data['post_title'] : '' ) );
	}

	/**
	 * Normalizes a title or slug for auto-slug comparison.
	 *
	 * @param string $value Title or slug.
	 * @return string
	 */
	private function normalize_slug_for_compare( $value ) {
		return trim( sanitize_title( rawurldecode( (string) $value ) ), '-' );
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

	/**
	 * Gets the stored status when available, otherwise the incoming status.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param int                  $post_id Post ID.
	 * @return string
	 */
	private function stored_or_current_status( $data, $post_id ) {
		$post_id = absint( $post_id );

		if ( $post_id > 0 ) {
			$existing_post = get_post( $post_id );

			if ( $existing_post && isset( $existing_post->post_status ) ) {
				return (string) $existing_post->post_status;
			}
		}

		return isset( $data['post_status'] ) ? (string) $data['post_status'] : '';
	}

	/**
	 * Checks whether a post status is not public yet.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private function is_unpublished_status( $status ) {
		return in_array( $status, array( 'auto-draft', 'draft', 'pending' ), true );
	}

	/**
	 * Detects numeric slugs WordPress can create when a draft has no title.
	 *
	 * @param string $slug    Slug.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	private function is_generated_id_placeholder_slug( $slug, $post_id ) {
		$post_id = absint( $post_id );
		$slug    = sanitize_title( $slug );

		if ( $post_id <= 0 || '' === $slug ) {
			return false;
		}

		return 1 === preg_match( '/^' . preg_quote( (string) $post_id, '/' ) . '(?:-\d+)?$/', $slug );
	}

	/**
	 * Detects WordPress auto-draft requests before a real title exists.
	 *
	 * @param array<string, mixed> $data  Sanitized post data.
	 * @param string               $title Current title.
	 * @return bool
	 */
	private function is_auto_draft_request( $data, $title ) {
		if ( isset( $data['post_status'] ) && 'auto-draft' === (string) $data['post_status'] ) {
			return true;
		}

		foreach ( $this->auto_draft_titles() as $auto_draft_title ) {
			if ( $title === $auto_draft_title ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns known auto-draft titles in the current locale.
	 *
	 * @return string[]
	 */
	private function auto_draft_titles() {
		$titles = array(
			'Auto Draft',
			$this->text_from_json( '"\u0391\u03c5\u03c4\u03cc\u03bc\u03b1\u03c4\u03bf \u03c0\u03c1\u03bf\u03c3\u03c7\u03ad\u03b4\u03b9\u03bf"' ),
		);

		return array_values( array_unique( array_filter( array_map( 'trim', $titles ) ) ) );
	}

	/**
	 * Decodes fixed UTF-8 text without depending on source-file encoding.
	 *
	 * @param string $json JSON string.
	 * @return string
	 */
	private function text_from_json( $json ) {
		$text = json_decode( $json );

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Detects placeholder slugs created from auto-draft titles.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	private function is_auto_draft_placeholder_slug( $slug ) {
		$slug         = sanitize_title( $slug );
		$placeholders = array( 'auto-draft', 'avtomato-prosxedio', 'aftomato-prosxedio', 'aftomato-proschedio' );

		foreach ( $this->auto_draft_titles() as $title ) {
			$generated = $this->slug_generator->make_slug( $title, (int) $this->settings->get( 'max_slug_length' ) );

			if ( '' !== $generated ) {
				$placeholders[] = $generated;
			}
		}

		return in_array( $slug, array_unique( $placeholders ), true );
	}
}
