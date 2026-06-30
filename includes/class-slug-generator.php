<?php
/**
 * Shared Greeklish slug generator.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates slugs and filenames from Greek text.
 */
final class Slug_Generator {
	/**
	 * Converts arbitrary text to a clean slug.
	 *
	 * @param string $text       Source text.
	 * @param int    $max_length Maximum slug length.
	 * @return string
	 */
	public function make_slug( $text, $max_length = 70 ) {
		$text = $this->normalize_numeric_separators( $text );
		$text = $this->transliterate( $text );
		$text = str_replace( '_', '-', $text );

		$slug = sanitize_title( $text );
		$slug = preg_replace( '/-+/', '-', $slug );

		if ( null === $slug ) {
			$slug = '';
		}

		return $this->limit_slug_length( $slug, $max_length );
	}

	/**
	 * Converts an uploaded filename to a clean Greeklish filename.
	 *
	 * @param string $filename     Sanitized filename.
	 * @param string $filename_raw Original filename.
	 * @param int    $max_length   Maximum basename length.
	 * @return string
	 */
	public function make_filename( $filename, $filename_raw = '', $max_length = 70 ) {
		$raw = '' !== (string) $filename_raw ? (string) $filename_raw : (string) $filename;

		$extension     = pathinfo( (string) $filename, PATHINFO_EXTENSION );
		$raw_extension = pathinfo( $raw, PATHINFO_EXTENSION );
		$name          = $raw_extension
			? basename( $raw, '.' . $raw_extension )
			: pathinfo( $raw, PATHINFO_FILENAME );

		$slug = $this->make_slug( $name, $max_length );

		if ( '' === $slug ) {
			return (string) $filename;
		}

		$extension = strtolower( (string) $extension );

		return '' !== $extension ? $slug . '.' . $extension : $slug;
	}

	/**
	 * Converts Greek text to Latin characters.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	public function transliterate( $text ) {
		$text = wp_strip_all_tags( rawurldecode( (string) $text ) );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = $this->normalize_contextual_diphthongs( $text );
		$map  = $this->transliteration_map();

		/**
		 * Filters the transliteration map.
		 *
		 * @param array<string, string> $map  Character replacement map.
		 * @param string                $text Source text.
		 */
		$map = apply_filters( 'greek_url_guard_transliteration_map', $map, $text );

		return strtr( $text, $map );
	}

	/**
	 * Returns the base transliteration map using ASCII-safe Unicode escapes.
	 *
	 * @return array<string, string>
	 */
	private function transliteration_map() {
		$pairs = array(
			array( '"\u03bf\u03c5"', 'ou' ),
			array( '"\u03bf\u03cd"', 'ou' ),
			array( '"\u039f\u03a5"', 'ou' ),
			array( '"\u039f\u03c5"', 'ou' ),
			array( '"\u039f\u03cd"', 'ou' ),
			array( '"\u03b1\u03b9"', 'ai' ),
			array( '"\u03b1\u03af"', 'ai' ),
			array( '"\u0391\u0399"', 'ai' ),
			array( '"\u0391\u03b9"', 'ai' ),
			array( '"\u0391\u03af"', 'ai' ),
			array( '"\u03b5\u03b9"', 'ei' ),
			array( '"\u03b5\u03af"', 'ei' ),
			array( '"\u0395\u0399"', 'ei' ),
			array( '"\u0395\u03b9"', 'ei' ),
			array( '"\u0395\u03af"', 'ei' ),
			array( '"\u03bf\u03b9"', 'oi' ),
			array( '"\u03bf\u03af"', 'oi' ),
			array( '"\u039f\u0399"', 'oi' ),
			array( '"\u039f\u03b9"', 'oi' ),
			array( '"\u039f\u03af"', 'oi' ),
			array( '"\u03b1\u03c5"', 'av' ),
			array( '"\u03b1\u03cd"', 'av' ),
			array( '"\u0391\u03a5"', 'av' ),
			array( '"\u0391\u03c5"', 'av' ),
			array( '"\u0391\u03cd"', 'av' ),
			array( '"\u03b5\u03c5"', 'ev' ),
			array( '"\u03b5\u03cd"', 'ev' ),
			array( '"\u0395\u03a5"', 'ev' ),
			array( '"\u0395\u03c5"', 'ev' ),
			array( '"\u0395\u03cd"', 'ev' ),
			array( '"\u03bc\u03c0"', 'mp' ),
			array( '"\u039c\u03a0"', 'mp' ),
			array( '"\u039c\u03c0"', 'mp' ),
			array( '"\u03bd\u03c4"', 'nt' ),
			array( '"\u039d\u03a4"', 'nt' ),
			array( '"\u039d\u03c4"', 'nt' ),
			array( '"\u03b3\u03ba"', 'gk' ),
			array( '"\u0393\u039a"', 'gk' ),
			array( '"\u0393\u03ba"', 'gk' ),
			array( '"\u03c4\u03c3"', 'ts' ),
			array( '"\u03a4\u03a3"', 'ts' ),
			array( '"\u03a4\u03c3"', 'ts' ),
			array( '"\u03c4\u03b6"', 'tz' ),
			array( '"\u03a4\u0396"', 'tz' ),
			array( '"\u03a4\u03b6"', 'tz' ),
			array( '"\u03b1"', 'a' ),
			array( '"\u03ac"', 'a' ),
			array( '"\u0391"', 'a' ),
			array( '"\u0386"', 'a' ),
			array( '"\u03b2"', 'v' ),
			array( '"\u0392"', 'v' ),
			array( '"\u03b3"', 'g' ),
			array( '"\u0393"', 'g' ),
			array( '"\u03b4"', 'd' ),
			array( '"\u0394"', 'd' ),
			array( '"\u03b5"', 'e' ),
			array( '"\u03ad"', 'e' ),
			array( '"\u0395"', 'e' ),
			array( '"\u0388"', 'e' ),
			array( '"\u03b6"', 'z' ),
			array( '"\u0396"', 'z' ),
			array( '"\u03b7"', 'i' ),
			array( '"\u03ae"', 'i' ),
			array( '"\u0397"', 'i' ),
			array( '"\u0389"', 'i' ),
			array( '"\u03b8"', 'th' ),
			array( '"\u0398"', 'th' ),
			array( '"\u03b9"', 'i' ),
			array( '"\u03af"', 'i' ),
			array( '"\u03ca"', 'i' ),
			array( '"\u0390"', 'i' ),
			array( '"\u0399"', 'i' ),
			array( '"\u038a"', 'i' ),
			array( '"\u03aa"', 'i' ),
			array( '"\u03ba"', 'k' ),
			array( '"\u039a"', 'k' ),
			array( '"\u03bb"', 'l' ),
			array( '"\u039b"', 'l' ),
			array( '"\u03bc"', 'm' ),
			array( '"\u039c"', 'm' ),
			array( '"\u03bd"', 'n' ),
			array( '"\u039d"', 'n' ),
			array( '"\u03be"', 'x' ),
			array( '"\u039e"', 'x' ),
			array( '"\u03bf"', 'o' ),
			array( '"\u03cc"', 'o' ),
			array( '"\u039f"', 'o' ),
			array( '"\u038c"', 'o' ),
			array( '"\u03c0"', 'p' ),
			array( '"\u03a0"', 'p' ),
			array( '"\u03c1"', 'r' ),
			array( '"\u03a1"', 'r' ),
			array( '"\u03c3"', 's' ),
			array( '"\u03c2"', 's' ),
			array( '"\u03a3"', 's' ),
			array( '"\u03c4"', 't' ),
			array( '"\u03a4"', 't' ),
			array( '"\u03c5"', 'y' ),
			array( '"\u03cd"', 'y' ),
			array( '"\u03cb"', 'y' ),
			array( '"\u03b0"', 'y' ),
			array( '"\u03a5"', 'y' ),
			array( '"\u038e"', 'y' ),
			array( '"\u03ab"', 'y' ),
			array( '"\u03c6"', 'f' ),
			array( '"\u03a6"', 'f' ),
			array( '"\u03c7"', 'x' ),
			array( '"\u03a7"', 'x' ),
			array( '"\u03c8"', 'ps' ),
			array( '"\u03a8"', 'ps' ),
			array( '"\u03c9"', 'o' ),
			array( '"\u03ce"', 'o' ),
			array( '"\u03a9"', 'o' ),
			array( '"\u038f"', 'o' ),
		);

		$map = array();

		foreach ( $pairs as $pair ) {
			$key = $this->text_from_json( $pair[0] );

			if ( '' !== $key ) {
				$map[ $key ] = $pair[1];
			}
		}

		return $map;
	}

	/**
	 * Applies common Greeklish context rules before single-letter replacement.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	private function normalize_contextual_diphthongs( $text ) {
		$voiceless = (string) json_decode( '"[\\u03B8\\u0398\\u03BA\\u039A\\u03BE\\u039E\\u03C0\\u03A0\\u03C3\\u03C2\\u03A3\\u03C4\\u03A4\\u03C6\\u03A6\\u03C7\\u03A7\\u03C8\\u03A8]"' );
		$alpha     = (string) json_decode( '"[\\u03B1\\u03AC\\u0391\\u0386]"' );
		$epsilon   = (string) json_decode( '"[\\u03B5\\u03AD\\u0395\\u0388]"' );
		$upsilon   = (string) json_decode( '"[\\u03C5\\u03CD\\u03A5\\u038E]"' );

		$patterns = array(
			array( '/' . $alpha . $upsilon . '(?=' . $voiceless . '|\s|$)/u', 'af' ),
			array( '/' . $alpha . $upsilon . '/u', 'av' ),
			array( '/' . $epsilon . $upsilon . '(?=' . $voiceless . '|\s|$)/u', 'ef' ),
			array( '/' . $epsilon . $upsilon . '/u', 'ev' ),
		);

		foreach ( $patterns as $pattern ) {
			$text = preg_replace( $pattern[0], $pattern[1], (string) $text );

			if ( null === $text ) {
				return '';
			}
		}

		return (string) $text;
	}

	/**
	 * Keeps grouped numeric values compact before punctuation becomes dashes.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	private function normalize_numeric_separators( $text ) {
		$text = preg_replace_callback(
			'/(^|[^\d\.,])(\d{1,3}(?:[\.,]\d{3})+)(?=$|[^\d\.,])/u',
			static function ( $matches ) {
				return $matches[1] . str_replace( array( '.', ',' ), '', $matches[2] );
			},
			(string) $text
		);

		if ( null === $text ) {
			return '';
		}

		$text = preg_replace( '/(?<=\d)[\.,](?=\d)/', '-', $text );

		return null === $text ? '' : $text;
	}

	/**
	 * Limits a slug without cutting words when possible.
	 *
	 * @param string $slug       Slug.
	 * @param int    $max_length Maximum length.
	 * @return string
	 */
	public function limit_slug_length( $slug, $max_length = 70 ) {
		$slug       = trim( (string) $slug, '-' );
		$max_length = max( 20, min( 150, absint( $max_length ) ) );

		if ( strlen( $slug ) <= $max_length ) {
			return $slug;
		}

		$short_slug = substr( $slug, 0, $max_length );
		$last_dash  = strrpos( $short_slug, '-' );

		if ( false !== $last_dash && $last_dash >= 20 ) {
			$short_slug = substr( $short_slug, 0, $last_dash );
		}

		return trim( $short_slug, '-' );
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
}
