<?php
/**
 * Exporter for Gravity Forms Git Sync.
 *
 * Exports forms and feeds to JSON files.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Exporter
 */
class Exporter {

	/**
	 * Storage instance.
	 *
	 * @var Storage
	 */
	private $storage;

	/**
	 * Blog ID for multisite.
	 *
	 * @var int
	 */
	private $blog_id;

	/**
	 * Request-level exported set (debounce).
	 *
	 * @var array<string>
	 */
	private static $exported_this_request = [];

	/**
	 * Constructor.
	 *
	 * @param Storage|null $storage Storage instance.
	 * @param int|null     $blog_id Blog ID.
	 */
	public function __construct( ?Storage $storage = null, ?int $blog_id = null ) {
		$this->blog_id = $blog_id ?? get_current_blog_id();
		$this->storage = $storage ?? new Storage( $this->blog_id );
	}

	/**
	 * Export form to JSON.
	 *
	 * @param array $form Form array from GFAPI.
	 * @return bool Success.
	 */
	public function export_form( array $form ): bool {
		$sr_key = $this->get_form_sr_key( $form );
		if ( ! $sr_key ) {
			return false;
		}

		$lock_key = 'form_' . $sr_key;
		return Locks::with_lock( $lock_key, function () use ( $form, $sr_key ) {
			if ( $this->already_exported( $sr_key ) ) {
				return true;
			}

			if ( ! $this->storage->is_writable() ) {
				Logger::error( 'Base path not writable: ' . $this->storage->get_base_path() );
				return false;
			}

			$data = Transformers::normalise_form( $form );
			$data = Transformers::mask_secrets( $data );
			$data['sr_key'] = $sr_key;
			if ( ! empty( $form['gf_git_sync_sr_meta'] ) ) {
				$data['sr_meta'] = $form['gf_git_sync_sr_meta'];
			}

			$path = $this->storage->get_form_path( $sr_key );
			if ( ! $this->storage->write_json( $path, $data ) ) {
				return false;
			}

			// Write field mapping for feed rewriting.
			$field_map = FieldMapper::build_map_from_form( $form );
			if ( ! empty( $field_map ) ) {
				$map_data = [
					'form_sr_key' => $sr_key,
					'fields'      => $field_map,
				];
				$this->storage->write_json( $this->storage->get_map_path( $sr_key ), $map_data );
			}

			$hash = Hashing::hash_form( $data );
			$this->update_meta_form( $sr_key, (int) ( $form['id'] ?? 0 ), $path, $hash, 'exported' );
			$this->mark_exported( $sr_key );
			return true;
		}, $this->blog_id ) ?? false;
	}

	/**
	 * Export feed to JSON.
	 *
	 * @param array $feed Feed array.
	 * @param array $form Form array.
	 * @return bool Success.
	 */
	public function export_feed( array $feed, array $form ): bool {
		$feed_sr_key = FeedExporter::get_feed_sr_key( $feed, FeedExporter::get_form_sr_key( $form ) );
		$lock_key = 'feed_' . $feed_sr_key;

		return Locks::with_lock( $lock_key, function () use ( $feed, $form, $feed_sr_key ) {
			if ( $this->already_exported( $feed_sr_key ) ) {
				return true;
			}

			if ( ! $this->storage->is_writable() ) {
				return false;
			}

			$data = FeedExporter::prepare_for_export( $feed, $form );
			$path = $this->storage->get_feed_path( $feed_sr_key );
			if ( ! $this->storage->write_json( $path, $data ) ) {
				return false;
			}

			$hash = Hashing::hash_feed( $data );
			$this->update_meta_feed( $feed_sr_key, $data['form_sr_key'] ?? '', $feed['addon_slug'] ?? '', (int) ( $feed['id'] ?? 0 ), $path, $hash, 'exported' );
			$this->mark_exported( $feed_sr_key );
			return true;
		}, $this->blog_id ) ?? false;
	}

	/**
	 * Archive form and its feeds on delete.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public function archive_form( int $form_id ): bool {
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form ) {
			return true;
		}
		$sr_key = $this->get_form_sr_key( $form );
		if ( ! $sr_key ) {
			return true;
		}

		$archive = defined( 'GF_GIT_SYNC_ARCHIVE_DELETES' ) ? GF_GIT_SYNC_ARCHIVE_DELETES : true;

		$form_path = $this->storage->get_form_path( $sr_key );
		if ( $archive ) {
			$this->storage->archive( $form_path, $sr_key . '.form.json' );
		} else {
			$this->storage->delete( $form_path );
		}

		// Archive feeds for this form.
		$feeds = \GFAPI::get_feeds( $form_id );
		if ( is_array( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				$feed_sr_key = FeedExporter::get_feed_sr_key( $feed, $sr_key );
				$feed_path = $this->storage->get_feed_path( $feed_sr_key );
				if ( $archive ) {
					$this->storage->archive( $feed_path, $feed_sr_key . '.feed.json' );
				} else {
					$this->storage->delete( $feed_path );
				}
			}
		}

		$this->remove_meta_form( $sr_key );
		return true;
	}

	/**
	 * Archive feed on delete.
	 *
	 * @param int $feed_id Feed ID.
	 * @return bool
	 */
	public function archive_feed( int $feed_id ): bool {
		$feed = \GFAPI::get_feed( $feed_id );
		if ( ! $feed ) {
			return true;
		}
		$form = \GFAPI::get_form( (int) $feed['form_id'] );
		if ( ! $form ) {
			return true;
		}
		$feed_sr_key = FeedExporter::get_feed_sr_key( $feed, FeedExporter::get_form_sr_key( $form ) );
		$feed_path = $this->storage->get_feed_path( $feed_sr_key );

		$archive = defined( 'GF_GIT_SYNC_ARCHIVE_DELETES' ) ? GF_GIT_SYNC_ARCHIVE_DELETES : true;
		if ( $archive ) {
			$this->storage->archive( $feed_path, $feed_sr_key . '.feed.json' );
		} else {
			$this->storage->delete( $feed_path );
		}
		$this->remove_meta_feed( $feed_sr_key );
		return true;
	}

	/**
	 * Get form sr_key.
	 *
	 * @param array $form Form data.
	 * @return string|null
	 */
	private function get_form_sr_key( array $form ): ?string {
		if ( ! empty( $form['gf_git_sync_sr_key'] ) ) {
			return sanitize_key( (string) $form['gf_git_sync_sr_key'] );
		}
		if ( ! empty( $form['form_key'] ) ) {
			return sanitize_key( (string) $form['form_key'] );
		}
		$title = $form['title'] ?? '';
		if ( $title ) {
			return sanitize_key( sanitize_title( $title ) );
		}
		return null;
	}

	/**
	 * Check if already exported this request (debounce).
	 *
	 * @param string $key Sr_key.
	 * @return bool
	 */
	private function already_exported( string $key ): bool {
		return isset( self::$exported_this_request[ $key ] );
	}

	/**
	 * Mark as exported this request.
	 *
	 * @param string $key Sr_key.
	 */
	private function mark_exported( string $key ): void {
		self::$exported_this_request[ $key ] = true;
	}

	/**
	 * Update meta index for form.
	 *
	 * @param string $sr_key  Form sr_key.
	 * @param int    $db_id   DB form ID.
	 * @param string $path    JSON path.
	 * @param string $hash    Content hash.
	 * @param string $type    'exported' or 'imported'.
	 */
	private function update_meta_form( string $sr_key, int $db_id, string $path, string $hash, string $type ): void {
		$meta = $this->storage->read_json( $this->storage->get_meta_path() ) ?? [ 'forms' => [], 'feeds' => [] ];
		$meta['forms'] = $meta['forms'] ?? [];
		$entry = $meta['forms'][ $sr_key ] ?? [];
		$entry['db_id'] = $db_id;
		$entry['json_path'] = $path;
		$entry['last_' . $type . '_hash'] = $hash;
		$entry['last_synced_at'] = gmdate( 'c' );
		$meta['forms'][ $sr_key ] = $entry;
		$this->storage->write_json( $this->storage->get_meta_path(), $meta );
	}

	/**
	 * Update meta index for feed.
	 *
	 * @param string $sr_key       Feed sr_key.
	 * @param string $form_sr_key  Form sr_key.
	 * @param string $addon_slug   Addon slug.
	 * @param int    $db_id        DB feed ID.
	 * @param string $path         JSON path.
	 * @param string $hash         Content hash.
	 * @param string $type         'exported' or 'imported'.
	 */
	private function update_meta_feed( string $sr_key, string $form_sr_key, string $addon_slug, int $db_id, string $path, string $hash, string $type ): void {
		$meta = $this->storage->read_json( $this->storage->get_meta_path() ) ?? [ 'forms' => [], 'feeds' => [] ];
		$meta['feeds'] = $meta['feeds'] ?? [];
		$entry = $meta['feeds'][ $sr_key ] ?? [];
		$entry['form_sr_key'] = $form_sr_key;
		$entry['addon_slug'] = $addon_slug;
		$entry['db_id'] = $db_id;
		$entry['json_path'] = $path;
		$entry['last_' . $type . '_hash'] = $hash;
		$entry['last_synced_at'] = gmdate( 'c' );
		$meta['feeds'][ $sr_key ] = $entry;
		$this->storage->write_json( $this->storage->get_meta_path(), $meta );
	}

	/**
	 * Remove form from meta index.
	 *
	 * @param string $sr_key Form sr_key.
	 */
	private function remove_meta_form( string $sr_key ): void {
		$meta = $this->storage->read_json( $this->storage->get_meta_path() ) ?? [ 'forms' => [], 'feeds' => [] ];
		unset( $meta['forms'][ $sr_key ] );
		$this->storage->write_json( $this->storage->get_meta_path(), $meta );
	}

	/**
	 * Remove feed from meta index.
	 *
	 * @param string $sr_key Feed sr_key.
	 */
	private function remove_meta_feed( string $sr_key ): void {
		$meta = $this->storage->read_json( $this->storage->get_meta_path() ) ?? [ 'forms' => [], 'feeds' => [] ];
		unset( $meta['feeds'][ $sr_key ] );
		$this->storage->write_json( $this->storage->get_meta_path(), $meta );
	}

	/**
	 * Get storage instance.
	 *
	 * @return Storage
	 */
	public function get_storage(): Storage {
		return $this->storage;
	}
}
