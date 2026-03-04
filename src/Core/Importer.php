<?php
/**
 * Importer for Gravity Forms Git Sync.
 *
 * Imports forms and feeds from JSON files.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Importer
 */
class Importer {

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
	 * Allow missing secrets (unresolved placeholders).
	 *
	 * @var bool
	 */
	private $allow_missing_secrets;

	/**
	 * Constructor.
	 *
	 * @param Storage|null $storage               Storage instance.
	 * @param int|null     $blog_id              Blog ID.
	 * @param bool         $allow_missing_secrets Allow unresolved placeholders.
	 */
	public function __construct( ?Storage $storage = null, ?int $blog_id = null, bool $allow_missing_secrets = false ) {
		$this->blog_id = $blog_id ?? get_current_blog_id();
		$this->storage = $storage ?? new Storage( $this->blog_id );
		$this->allow_missing_secrets = $allow_missing_secrets;
	}

	/**
	 * Import form from JSON.
	 *
	 * @param string $sr_key Form sr_key.
	 * @param string $mode  'sync' (update existing) or 'create-only'.
	 * @return int|null Form ID or null on error.
	 */
	public function import_form( string $sr_key, string $mode = 'sync' ): ?int {
		$path = $this->storage->get_form_path( $sr_key );
		$data = $this->storage->read_json( $path );
		if ( ! $data ) {
			return null;
		}

		try {
			$data = EnvResolver::resolve_placeholders( $data, ! $this->allow_missing_secrets );
		} catch ( \Throwable $e ) {
			Logger::error( $e->getMessage() );
			return null;
		}

		$existing_id = $this->find_form_by_sr_key( $sr_key );
		if ( $existing_id && $mode === 'sync' ) {
			$data['id'] = $existing_id;
			$result = \GFAPI::update_form( $data );
			if ( is_wp_error( $result ) ) {
				Logger::error( $result->get_error_message() );
				return null;
			}
			$form_id = $existing_id;
		} else {
			unset( $data['id'] );
			$form_id = \GFAPI::add_form( $data );
			if ( is_wp_error( $form_id ) ) {
				Logger::error( $form_id->get_error_message() );
				return null;
			}
		}

		$form = \GFAPI::get_form( $form_id );
		if ( $form ) {
			// Hash the original JSON content (before placeholder resolution) so it matches file content.
			$original_data = $this->storage->read_json( $path );
			$hash = $original_data ? Hashing::hash_form( $original_data ) : '';
			$meta = $this->storage->read_json( $this->storage->get_meta_path() ) ?? [ 'forms' => [], 'feeds' => [] ];
			$meta['forms'] = $meta['forms'] ?? [];
			$meta['forms'][ $sr_key ] = array_merge( $meta['forms'][ $sr_key ] ?? [], [
				'db_id'              => $form_id,
				'json_path'          => $path,
				'last_imported_hash' => $hash,
				'last_synced_at'     => gmdate( 'c' ),
			] );
			$this->storage->write_json( $this->storage->get_meta_path(), $meta );
		}

		return $form_id;
	}

	/**
	 * Import feed from JSON.
	 *
	 * @param string $feed_sr_key Feed sr_key.
	 * @param int    $form_id     Target form ID.
	 * @param string $mode        'sync' or 'create-only'.
	 * @return int|null Feed ID or null.
	 */
	public function import_feed( string $feed_sr_key, int $form_id, string $mode = 'sync' ): ?int {
		$path = $this->storage->get_feed_path( $feed_sr_key );
		$data = $this->storage->read_json( $path );
		if ( ! $data ) {
			return null;
		}

		$prepared = FeedImporter::prepare_for_import( $data, $form_id, $this->allow_missing_secrets );
		if ( ! $prepared ) {
			return null;
		}

		$existing_id = $this->find_feed_by_sr_key( $feed_sr_key );
		if ( $existing_id && $mode === 'sync' ) {
			$result = \GFAPI::update_feed( $existing_id, $prepared['meta'] );
			if ( is_wp_error( $result ) ) {
				Logger::error( $result->get_error_message() );
				return null;
			}
			return $existing_id;
		}

		$feed_id = \GFAPI::add_feed( $form_id, $prepared['meta'], $prepared['addon_slug'] );
		if ( is_wp_error( $feed_id ) ) {
			Logger::error( $feed_id->get_error_message() );
			return null;
		}
		return $feed_id;
	}

	/**
	 * Import all forms and feeds from JSON.
	 *
	 * @param string|null $form_sr_key Optional specific form.
	 * @param string      $mode        'sync' or 'create-only'.
	 * @param bool        $prune_feeds  Delete feeds not in JSON.
	 * @param bool        $dry_run     Don't actually import.
	 * @return array Result with counts.
	 */
	public function import_all( ?string $form_sr_key = null, string $mode = 'sync', bool $prune_feeds = false, bool $dry_run = false ): array {
		$result = [ 'forms' => 0, 'feeds' => 0, 'errors' => [] ];

		$forms_dir = $this->storage->get_base_path() . '/forms';
		if ( ! is_dir( $forms_dir ) ) {
			return $result;
		}

		$form_files = $form_sr_key
			? [ $this->storage->get_form_path( $form_sr_key ) ]
			: glob( $forms_dir . '/*.form.json' );

		if ( ! $form_files ) {
			return $result;
		}

		foreach ( $form_files as $form_path ) {
			if ( ! file_exists( $form_path ) ) {
				continue;
			}
			$sr_key = basename( $form_path, '.form.json' );
			if ( $dry_run ) {
				$result['forms']++;
				continue;
			}
			$form_id = $this->import_form( $sr_key, $mode );
			if ( $form_id ) {
				$result['forms']++;
				$feeds_dir = $this->storage->get_base_path() . '/feeds';
				$feed_files = glob( $feeds_dir . '/*.feed.json' );
				foreach ( $feed_files ?? [] as $feed_path ) {
					$feed_data = $this->storage->read_json( $feed_path );
					if ( ! $feed_data || ( $feed_data['form_sr_key'] ?? '' ) !== $sr_key ) {
						continue;
					}
					$feed_sr_key = $feed_data['sr_key'] ?? basename( $feed_path, '.feed.json' );
					$feed_id = $this->import_feed( $feed_sr_key, $form_id, $mode );
					if ( $feed_id ) {
						$result['feeds']++;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Find form ID by sr_key (from meta or by matching form_key).
	 *
	 * @param string $sr_key Form sr_key.
	 * @return int|null
	 */
	private function find_form_by_sr_key( string $sr_key ): ?int {
		$meta = $this->storage->read_json( $this->storage->get_meta_path() );
		if ( ! empty( $meta['forms'][ $sr_key ]['db_id'] ) ) {
			$id = (int) $meta['forms'][ $sr_key ]['db_id'];
			$form = \GFAPI::get_form( $id );
			return $form ? $id : null;
		}
		$forms = \GFAPI::get_forms();
		foreach ( $forms as $form ) {
			$form_key = $form['form_key'] ?? '';
			$gf_sync_key = $form['gf_git_sync_sr_key'] ?? '';
			if ( ( $form_key && $form_key === $sr_key ) || $gf_sync_key === $sr_key ) {
				return (int) $form['id'];
			}
		}
		return null;
	}

	/**
	 * Find feed ID by sr_key.
	 *
	 * @param string $sr_key Feed sr_key.
	 * @return int|null
	 */
	private function find_feed_by_sr_key( string $sr_key ): ?int {
		$meta = $this->storage->read_json( $this->storage->get_meta_path() );
		if ( ! empty( $meta['feeds'][ $sr_key ]['db_id'] ) ) {
			return (int) $meta['feeds'][ $sr_key ]['db_id'];
		}
		return null;
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
