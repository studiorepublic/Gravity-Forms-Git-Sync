<?php
/**
 * Field mapper for Gravity Forms Git Sync.
 *
 * Maps stable field keys (sr:…) to numeric IDs for feed rewriting.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FieldMapper
 */
class FieldMapper {

	/**
	 * Get addon field map registry.
	 *
	 * Meta keys per addon that contain field IDs to rewrite.
	 *
	 * @return array<string, array>
	 */
	public static function get_addon_registry(): array {
		if ( defined( 'GF_GIT_SYNC_ADDON_FIELD_MAP_REGISTRY' ) && is_array( GF_GIT_SYNC_ADDON_FIELD_MAP_REGISTRY ) ) {
			return GF_GIT_SYNC_ADDON_FIELD_MAP_REGISTRY;
		}
		return self::get_default_registry();
	}

	/**
	 * Default addon field map registry.
	 *
	 * @return array
	 */
	private static function get_default_registry(): array {
		return [
			'gravityformsstripe'     => [
				'transactionType',
				'paymentAmount',
				'customerEmail',
				'billingInformation_email',
				'billingInformation_address',
			],
			'gravityformsmailchimp' => [
				'list',
				'field_map',
				'double_optin',
			],
			'gravityformswebhooks'   => [
				'field_map',
			],
			'gravityformsuserregistration' => [
				'field_map',
			],
		];
	}

	/**
	 * Build field mapping from form (stable key => id).
	 *
	 * @param array $form Form array.
	 * @return array Map of sr:key => field_id.
	 */
	public static function build_map_from_form( array $form ): array {
		$map = [];
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $map;
		}
		foreach ( $form['fields'] as $field ) {
			$id = (int) ( $field['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			$admin_label = $field['adminLabel'] ?? $field['label'] ?? '';
			$stable_key = self::extract_stable_key( $admin_label );
			if ( $stable_key ) {
				$map[ $stable_key ] = $id;
			}
		}
		return $map;
	}

	/**
	 * Extract stable key from admin label (sr:... convention).
	 *
	 * @param string $admin_label Admin label.
	 * @return string|null Stable key or null.
	 */
	public static function extract_stable_key( string $admin_label ): ?string {
		$admin_label = trim( $admin_label );
		if ( strpos( $admin_label, 'sr:' ) === 0 ) {
			return $admin_label;
		}
		return null;
	}

	/**
	 * Rewrite feed meta for export: numeric IDs to stable keys.
	 *
	 * @param array $meta     Feed meta.
	 * @param array $field_map Map of sr:key => id.
	 * @param string $addon_slug Addon slug.
	 * @return array Rewritten meta.
	 */
	public static function rewrite_feed_meta_for_export( array $meta, array $field_map, string $addon_slug ): array {
		$registry = self::get_addon_registry();
		$keys_to_rewrite = $registry[ $addon_slug ] ?? [];
		if ( empty( $keys_to_rewrite ) ) {
			return $meta;
		}
		$reverse_map = array_flip( $field_map );
		return self::rewrite_meta_ids( $meta, $keys_to_rewrite, $reverse_map, 'id_to_key' );
	}

	/**
	 * Rewrite feed meta for import: stable keys to numeric IDs.
	 *
	 * @param array $meta     Feed meta.
	 * @param array $field_map Map of sr:key => id.
	 * @param string $addon_slug Addon slug.
	 * @return array Rewritten meta.
	 */
	public static function rewrite_feed_meta_for_import( array $meta, array $field_map, string $addon_slug ): array {
		$registry = self::get_addon_registry();
		$keys_to_rewrite = $registry[ $addon_slug ] ?? [];
		if ( empty( $keys_to_rewrite ) ) {
			return $meta;
		}
		return self::rewrite_meta_ids( $meta, $keys_to_rewrite, $field_map, 'key_to_id' );
	}

	/**
	 * Rewrite meta values by direction.
	 *
	 * @param array  $meta            Meta array.
	 * @param array  $keys_to_rewrite Meta keys to process.
	 * @param array  $map             Mapping.
	 * @param string $direction       'id_to_key' or 'key_to_id'.
	 * @return array
	 */
	private static function rewrite_meta_ids( array $meta, array $keys_to_rewrite, array $map, string $direction ): array {
		foreach ( $keys_to_rewrite as $key ) {
			if ( ! isset( $meta[ $key ] ) ) {
				continue;
			}
			$value = $meta[ $key ];
			if ( is_array( $value ) ) {
				$meta[ $key ] = self::rewrite_array_ids( $value, $map, $direction );
			} elseif ( is_numeric( $value ) && $direction === 'id_to_key' ) {
				$meta[ $key ] = $map[ (int) $value ] ?? $value;
			} elseif ( is_string( $value ) && strpos( $value, 'sr:' ) === 0 && $direction === 'key_to_id' ) {
				$meta[ $key ] = $map[ $value ] ?? $value;
			}
		}
		return $meta;
	}

	/**
	 * Rewrite field IDs in nested array (e.g. field_map).
	 *
	 * @param array  $arr       Array.
	 * @param array  $map       Mapping.
	 * @param string $direction Direction.
	 * @return array
	 */
	private static function rewrite_array_ids( array $arr, array $map, string $direction ): array {
		$result = [];
		foreach ( $arr as $k => $v ) {
			if ( is_array( $v ) ) {
				$result[ $k ] = self::rewrite_array_ids( $v, $map, $direction );
			} elseif ( is_numeric( $v ) && $direction === 'id_to_key' ) {
				$result[ $k ] = $map[ (int) $v ] ?? $v;
			} elseif ( is_string( $v ) && strpos( $v, 'sr:' ) === 0 && $direction === 'key_to_id' ) {
				$result[ $k ] = $map[ $v ] ?? $v;
			} else {
				$result[ $k ] = $v;
			}
		}
		return $result;
	}
}
