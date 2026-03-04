<?php
/**
 * Transformers for Gravity Forms Git Sync.
 *
 * Normalises form/feed data for deterministic JSON export.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Transformers
 */
class Transformers {

	/**
	 * Volatile form keys to strip for export.
	 */
	const VOLATILE_FORM_KEYS = [ 'id', 'date_created', 'date_updated' ];

	/**
	 * Volatile feed keys to strip for export.
	 */
	const VOLATILE_FEED_KEYS = [ 'id', 'date_created', 'date_updated' ];

	/**
	 * Normalise form data for export.
	 *
	 * @param array $form Form array from GFAPI.
	 * @return array Normalised form.
	 */
	public static function normalise_form( array $form ): array {
		$normalised = $form;

		// Strip volatile keys.
		foreach ( self::VOLATILE_FORM_KEYS as $key ) {
			unset( $normalised[ $key ] );
		}

		// Sort notifications by name.
		if ( ! empty( $normalised['notifications'] ) && is_array( $normalised['notifications'] ) ) {
			uasort( $normalised['notifications'], function ( $a, $b ) {
				$name_a = $a['name'] ?? '';
				$name_b = $b['name'] ?? '';
				return strcmp( $name_a, $name_b );
			} );
		}

		// Sort confirmations by name.
		if ( ! empty( $normalised['confirmations'] ) && is_array( $normalised['confirmations'] ) ) {
			uasort( $normalised['confirmations'], function ( $a, $b ) {
				$name_a = $a['name'] ?? '';
				$name_b = $b['name'] ?? '';
				return strcmp( $name_a, $name_b );
			} );
		}

		// Recursively sort arrays for stable output.
		$normalised = self::sort_keys_recursive( $normalised );

		return $normalised;
	}

	/**
	 * Normalise feed data for export.
	 *
	 * @param array $feed Feed array.
	 * @return array Normalised feed.
	 */
	public static function normalise_feed( array $feed ): array {
		$normalised = $feed;

		foreach ( self::VOLATILE_FEED_KEYS as $key ) {
			unset( $normalised[ $key ] );
		}

		return self::sort_keys_recursive( $normalised );
	}

	/**
	 * Replace secret values with placeholders.
	 *
	 * @param array $data  Data (form or feed meta).
	 * @param array $map  Map of secret patterns/regexes to placeholder names.
	 * @return array Data with secrets masked.
	 */
	public static function mask_secrets( array $data, array $map = [] ): array {
		if ( empty( $map ) && defined( 'GF_GIT_SYNC_PLACEHOLDER_MAP' ) && is_array( GF_GIT_SYNC_PLACEHOLDER_MAP ) ) {
			$map = GF_GIT_SYNC_PLACEHOLDER_MAP;
		}

		if ( empty( $map ) ) {
			$map = self::get_default_placeholder_map();
		}

		return self::mask_secrets_recursive( $data, $map );
	}

	/**
	 * Default placeholder map for common secrets.
	 *
	 * @return array
	 */
	private static function get_default_placeholder_map(): array {
		return [
			'api_key'             => '{{API_KEY}}',
			'api_secret'          => '{{API_SECRET}}',
			'secret_key'          => '{{STRIPE_SECRET_KEY}}',
			'live_secret_key'     => '{{STRIPE_LIVE_SECRET_KEY}}',
			'test_secret_key'     => '{{STRIPE_TEST_SECRET_KEY}}',
			'webhook_url'         => '{{WEBHOOK_URL}}',
			'webhooks_url'        => '{{WEBHOOK_URL}}',
			'mailchimp_api_key'   => '{{MAILCHIMP_API_KEY}}',
			'mailchimp_apiKey'    => '{{MAILCHIMP_API_KEY}}',
		];
	}

	/**
	 * Recursively mask secrets in array.
	 *
	 * @param array $data Data.
	 * @param array $map  Key => placeholder map (key can be meta key name).
	 * @return array
	 */
	private static function mask_secrets_recursive( array $data, array $map ): array {
		$result = [];
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$result[ $key ] = self::mask_secrets_recursive( $value, $map );
			} elseif ( is_string( $value ) && ! empty( $value ) ) {
				$placeholder = $map[ $key ] ?? null;
				if ( $placeholder ) {
					$result[ $key ] = $placeholder;
				} else {
					// Check if value looks like a secret (heuristic).
					$result[ $key ] = self::maybe_mask_value( $value );
				}
			} else {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Heuristically mask value that looks like a secret.
	 *
	 * @param string $value Value.
	 * @return string Original or placeholder.
	 */
	private static function maybe_mask_value( string $value ): string {
		// Stripe keys: sk_live_, sk_test_, rk_live_, rk_test_
		if ( preg_match( '/^(sk|rk)_(live|test)_[a-zA-Z0-9]{24,}/', $value ) ) {
			return '{{STRIPE_SECRET_KEY}}';
		}
		// Generic API key pattern (long alphanumeric).
		if ( strlen( $value ) > 32 && preg_match( '/^[a-zA-Z0-9_-]+$/', $value ) ) {
			return '{{API_KEY}}';
		}
		return $value;
	}

	/**
	 * Recursively sort array keys for deterministic output.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function sort_keys_recursive( array $data ): array {
		$result = [];
		$keys = array_keys( $data );
		sort( $keys, SORT_STRING );
		foreach ( $keys as $key ) {
			$value = $data[ $key ];
			if ( is_array( $value ) && isset( $value[0] ) === false ) {
				$result[ $key ] = self::sort_keys_recursive( $value );
			} else {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}
}
