<?php
/**
 * Locking for Gravity Forms Git Sync.
 *
 * Prevents race conditions during export/import.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Locks
 */
class Locks {

	/**
	 * Transient prefix.
	 */
	const PREFIX = 'gf_git_sync_lock_';

	/**
	 * Default lock expiry (seconds).
	 */
	const TTL = 30;

	/**
	 * Acquire lock for a form.
	 *
	 * @param string $sr_key Form or feed stable key.
	 * @param int    $blog_id Blog ID.
	 * @return bool True if lock acquired.
	 */
	public static function acquire( string $sr_key, int $blog_id = 0 ): bool {
		$blog_id = $blog_id ?: get_current_blog_id();
		$key = self::PREFIX . $blog_id . '_' . sanitize_key( $sr_key );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, time(), self::TTL );
		return true;
	}

	/**
	 * Release lock.
	 *
	 * @param string $sr_key Form or feed stable key.
	 * @param int    $blog_id Blog ID.
	 */
	public static function release( string $sr_key, int $blog_id = 0 ): void {
		$blog_id = $blog_id ?: get_current_blog_id();
		$key = self::PREFIX . $blog_id . '_' . sanitize_key( $sr_key );
		delete_transient( $key );
	}

	/**
	 * Run callback with lock.
	 *
	 * @param string   $sr_key   Form or feed stable key.
	 * @param callable $callback Callback to run.
	 * @param int      $blog_id  Blog ID.
	 * @return mixed Callback return value, or null if lock not acquired.
	 */
	public static function with_lock( string $sr_key, callable $callback, int $blog_id = 0 ) {
		if ( ! self::acquire( $sr_key, $blog_id ) ) {
			return null;
		}
		try {
			return $callback();
		} finally {
			self::release( $sr_key, $blog_id );
		}
	}
}
