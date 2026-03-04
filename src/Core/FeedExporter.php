<?php
/**
 * Feed exporter for Gravity Forms Git Sync.
 *
 * Export logic specific to add-on feeds.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FeedExporter
 */
class FeedExporter {

	/**
	 * Export feed to normalised array.
	 *
	 * @param array $feed Feed data from GFFeedAddOn.
	 * @param array $form Form data.
	 * @return array Normalised feed data for JSON.
	 */
	public static function prepare_for_export( array $feed, array $form ): array {
		$form_sr_key = self::get_form_sr_key( $form );
		$feed_sr_key = self::get_feed_sr_key( $feed, $form_sr_key );

		$field_map = FieldMapper::build_map_from_form( $form );
		$addon_slug = $feed['addon_slug'] ?? '';

		$meta = $feed['meta'] ?? [];
		$meta = FieldMapper::rewrite_feed_meta_for_export( $meta, $field_map, $addon_slug );
		$meta = Transformers::mask_secrets( $meta );

		$data = [
			'sr_key'      => $feed_sr_key,
			'form_sr_key' => $form_sr_key,
			'addon_slug'  => $addon_slug,
			'is_active'   => (bool) ( $feed['is_active'] ?? true ),
			'meta'        => $meta,
		];

		return Transformers::normalise_feed( $data );
	}

	/**
	 * Get form sr_key from form or meta.
	 *
	 * @param array $form Form data.
	 * @return string
	 */
	public static function get_form_sr_key( array $form ): string {
		if ( ! empty( $form['gf_git_sync_sr_key'] ) ) {
			return (string) $form['gf_git_sync_sr_key'];
		}
		if ( ! empty( $form['form_key'] ) ) {
			return sanitize_key( (string) $form['form_key'] );
		}
		$title = $form['title'] ?? 'form';
		return sanitize_key( sanitize_title( $title ) );
	}

	/**
	 * Get feed sr_key.
	 *
	 * @param array  $feed        Feed data.
	 * @param string $form_sr_key Form sr_key.
	 * @return string
	 */
	public static function get_feed_sr_key( array $feed, string $form_sr_key ): string {
		if ( ! empty( $feed['gf_git_sync_sr_key'] ) ) {
			return (string) $feed['gf_git_sync_sr_key'];
		}
		$addon_slug = $feed['addon_slug'] ?? 'unknown';
		$feed_name = $feed['meta']['feedName'] ?? $feed['meta']['feed_name'] ?? $feed['id'] ?? 'default';
		$feed_name = is_string( $feed_name ) ? sanitize_key( $feed_name ) : 'default';
		return $form_sr_key . '.' . $addon_slug . '.' . $feed_name;
	}
}
