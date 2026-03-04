<?php
/**
 * Hashing service for Gravity Forms Git Sync.
 *
 * Content hashes for diff detection and sync status.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hashing
 */
class Hashing {

	/**
	 * Compute content hash of normalised data.
	 *
	 * @param array $data Data to hash (will be JSON-encoded).
	 * @return string SHA-256 hash.
	 */
	public static function hash( array $data ): string {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return hash( 'sha256', $json ?: '' );
	}

	/**
	 * Compute hash of form data (for comparison).
	 *
	 * @param array $form Form array.
	 * @return string
	 */
	public static function hash_form( array $form ): string {
		return self::hash( $form );
	}

	/**
	 * Compute hash of feed data (for comparison).
	 *
	 * @param array $feed Feed array.
	 * @return string
	 */
	public static function hash_feed( array $feed ): string {
		return self::hash( $feed );
	}

	/**
	 * Compute hash of file contents.
	 *
	 * @param string $path File path.
	 * @return string|null Hash or null if file missing.
	 */
	public static function hash_file( string $path ): ?string {
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$content = file_get_contents( $path );
		return $content !== false ? hash( 'sha256', $content ) : null;
	}
}
