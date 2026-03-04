<?php
/**
 * Storage service for Gravity Forms Git Sync.
 *
 * Handles JSON file paths, atomic writes, and multisite-aware base paths.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Storage
 */
class Storage {

	/**
	 * Base path for JSON storage.
	 *
	 * @var string
	 */
	private $base_path;

	/**
	 * Current blog ID for multisite.
	 *
	 * @var int
	 */
	private $blog_id;

	/**
	 * Constructor.
	 *
	 * @param int|null $blog_id Blog ID for multisite context.
	 */
	public function __construct( $blog_id = null ) {
		$this->blog_id = $blog_id ?? get_current_blog_id();
		$this->base_path = $this->resolve_base_path();
	}

	/**
	 * Resolve the base storage path.
	 *
	 * @return string
	 */
	private function resolve_base_path(): string {
		if ( defined( 'GF_GIT_SYNC_BASE_PATH' ) && GF_GIT_SYNC_BASE_PATH ) {
			$path = GF_GIT_SYNC_BASE_PATH;
		} else {
			$path = get_stylesheet_directory() . '/sync/gravity-forms';
		}

		$path = apply_filters( 'gf_git_sync_base_path', $path, $this->blog_id );

		// Multisite: append sites/{blog_id}/ for non-main sites.
		if ( is_multisite() && ! is_main_site( $this->blog_id ) ) {
			$path .= '/sites/' . $this->blog_id;
		}

		return untrailingslashit( $path );
	}

	/**
	 * Get base path.
	 *
	 * @return string
	 */
	public function get_base_path(): string {
		return $this->base_path;
	}

	/**
	 * Get path to a form JSON file.
	 *
	 * @param string $sr_key Form stable key.
	 * @return string
	 */
	public function get_form_path( string $sr_key ): string {
		return $this->base_path . '/forms/' . sanitize_file_name( $sr_key ) . '.form.json';
	}

	/**
	 * Get path to a feed JSON file.
	 *
	 * @param string $sr_key Feed stable key.
	 * @return string
	 */
	public function get_feed_path( string $sr_key ): string {
		return $this->base_path . '/feeds/' . sanitize_file_name( $sr_key ) . '.feed.json';
	}

	/**
	 * Get path to a field mapping file.
	 *
	 * @param string $form_sr_key Form stable key.
	 * @return string
	 */
	public function get_map_path( string $form_sr_key ): string {
		return $this->base_path . '/maps/' . sanitize_file_name( $form_sr_key ) . '.map.json';
	}

	/**
	 * Get path to meta index file.
	 *
	 * @return string
	 */
	public function get_meta_path(): string {
		return $this->base_path . '/meta/index.json';
	}

	/**
	 * Get path to archive directory.
	 *
	 * @return string
	 */
	public function get_archive_path(): string {
		return $this->base_path . '/archive';
	}

	/**
	 * Ensure directory exists and is writable.
	 *
	 * @param string $dir Directory path.
	 * @return bool
	 */
	public function ensure_dir( string $dir ): bool {
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' );
		}
		return is_writable( $dir );
	}

	/**
	 * Ensure all required directories exist.
	 *
	 * @return bool
	 */
	public function ensure_dirs(): bool {
		$dirs = [
			$this->base_path . '/forms',
			$this->base_path . '/feeds',
			$this->base_path . '/maps',
			$this->base_path . '/meta',
			$this->base_path . '/archive',
		];
		foreach ( $dirs as $dir ) {
			if ( ! $this->ensure_dir( $dir ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if base path is writable.
	 *
	 * @return bool
	 */
	public function is_writable(): bool {
		if ( ! $this->ensure_dirs() ) {
			return false;
		}
		return is_writable( $this->base_path );
	}

	/**
	 * Read JSON file.
	 *
	 * @param string $path File path.
	 * @return array|null Decoded data or null on error.
	 */
	public function read_json( string $path ): ?array {
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$content = file_get_contents( $path );
		if ( $content === false ) {
			return null;
		}
		$data = json_decode( $content, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Write JSON file atomically.
	 *
	 * @param string $path File path.
	 * @param array  $data Data to encode.
	 * @return bool Success.
	 */
	public function write_json( string $path, array $data ): bool {
		$dir = dirname( $path );
		if ( ! $this->ensure_dir( $dir ) ) {
			return false;
		}
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( $json === false ) {
			return false;
		}
		$tmp = $path . '.' . uniqid( 'tmp', true ) . '.tmp';
		if ( file_put_contents( $tmp, $json . "\n" ) === false ) {
			return false;
		}
		$renamed = rename( $tmp, $path );
		if ( ! $renamed && file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		return $renamed;
	}

	/**
	 * Move file to archive.
	 *
	 * @param string $path Source file path.
	 * @param string $name Base filename for archive.
	 * @return bool Success.
	 */
	public function archive( string $path, string $name = '' ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}
		$archive_dir = $this->get_archive_path();
		if ( ! $this->ensure_dir( $archive_dir ) ) {
			return false;
		}
		$base = $name ?: basename( $path );
		$timestamp = gmdate( 'Y-m-d_H-i-s' );
		$dest = $archive_dir . '/' . $timestamp . '_' . $base;
		return rename( $path, $dest );
	}

	/**
	 * Delete file.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	public function delete( string $path ): bool {
		if ( file_exists( $path ) ) {
			return unlink( $path );
		}
		return true;
	}

	/**
	 * Get file modification time.
	 *
	 * @param string $path File path.
	 * @return int|null Unix timestamp or null.
	 */
	public function get_mtime( string $path ): ?int {
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$mtime = filemtime( $path );
		return $mtime !== false ? $mtime : null;
	}
}
