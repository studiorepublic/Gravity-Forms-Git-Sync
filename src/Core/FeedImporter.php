<?php
/**
 * Feed importer for Gravity Forms Git Sync.
 *
 * Import logic for add-on feeds.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FeedImporter
 */
class FeedImporter {

	/**
	 * Prepare feed data for import (resolve placeholders, rewrite field IDs).
	 *
	 * @param array $feed_data  Feed data from JSON.
	 * @param int   $form_id   Target form ID.
	 * @param bool  $allow_missing_secrets Allow unresolved placeholders.
	 * @return array|null Feed array ready for GFAPI::add_feed/update_feed, or null on error.
	 */
	public static function prepare_for_import( array $feed_data, int $form_id, bool $allow_missing_secrets = false ): ?array {
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form ) {
			return null;
		}

		$addon_slug = $feed_data['addon_slug'] ?? '';
		if ( ! $addon_slug ) {
			return null;
		}

		$addon = self::get_addon( $addon_slug );
		if ( ! $addon ) {
			return null;
		}

		$meta = $feed_data['meta'] ?? [];
		$field_map = self::load_field_map( $feed_data['form_sr_key'] ?? '', $form );
		$meta = FieldMapper::rewrite_feed_meta_for_import( $meta, $field_map, $addon_slug );

		try {
			$meta = EnvResolver::resolve_placeholders( $meta, ! $allow_missing_secrets );
		} catch ( \Throwable $e ) {
			Logger::error( $e->getMessage() );
			return null;
		}

		return [
			'form_id'    => $form_id,
			'addon_slug' => $addon_slug,
			'is_active'  => (bool) ( $feed_data['is_active'] ?? true ),
			'meta'       => $meta,
		];
	}

	/**
	 * Get addon instance by slug.
	 *
	 * @param string $addon_slug Addon slug.
	 * @return object|null
	 */
	private static function get_addon( string $addon_slug ): ?object {
		$addons = \GFAddOn::get_registered_addons();
		foreach ( $addons as $addon_class ) {
			if ( ! class_exists( $addon_class ) ) {
				continue;
			}
			$addon = call_user_func( [ $addon_class, 'get_instance' ] );
			if ( $addon && $addon->get_slug() === $addon_slug ) {
				return $addon;
			}
		}
		return null;
	}

	/**
	 * Load field map for form (from map file or build from form).
	 *
	 * @param string $form_sr_key Form sr_key from JSON.
	 * @param array  $form        Current form.
	 * @return array Map of sr:key => id.
	 */
	private static function load_field_map( string $form_sr_key, array $form ): array {
		$storage = new Storage();
		$map_path = $storage->get_map_path( $form_sr_key );
		$map_data = $storage->read_json( $map_path );
		if ( $map_data && ! empty( $map_data['fields'] ) ) {
			return $map_data['fields'];
		}
		return FieldMapper::build_map_from_form( $form );
	}
}
