<?php
/**
 * Environment resolver for Gravity Forms Git Sync.
 *
 * Resolves {{PLACEHOLDER}} values from env vars, constants, or .env.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EnvResolver
 */
class EnvResolver {

	/**
	 * Resolve placeholder value.
	 *
	 * @param string $placeholder Placeholder name e.g. STRIPE_SECRET_KEY.
	 * @return string|null Resolved value or null.
	 */
	public static function resolve( string $placeholder ): ?string {
		$key = strtoupper( str_replace( [ '{{', '}}' ], '', $placeholder ) );
		if ( $key === $placeholder ) {
			$key = $placeholder;
		}

		// 1. Environment variable.
		$env_key = str_replace( '-', '_', $key );
		$value = getenv( $env_key );
		if ( $value !== false && $value !== '' ) {
			return $value;
		}

		// 2. WordPress constant.
		$constant_name = 'GF_GIT_SYNC_' . $env_key;
		if ( defined( $constant_name ) ) {
			return (string) constant( $constant_name );
		}
		if ( defined( $env_key ) ) {
			return (string) constant( $env_key );
		}

		// 3. .env file (if vlucas/phpdotenv or similar available).
		$value = self::resolve_from_dotenv( $env_key );
		if ( $value !== null ) {
			return $value;
		}

		return null;
	}

	/**
	 * Resolve from .env file.
	 *
	 * @param string $key Key name.
	 * @return string|null
	 */
	private static function resolve_from_dotenv( string $key ): ?string {
		if ( ! class_exists( 'Dotenv\Dotenv' ) ) {
			return null;
		}
		$env_path = defined( 'GF_GIT_SYNC_ENV_PATH' ) ? GF_GIT_SYNC_ENV_PATH : ABSPATH;
		if ( ! file_exists( $env_path . '/.env' ) ) {
			return null;
		}
		try {
			$dotenv = \Dotenv\Dotenv::createImmutable( $env_path );
			$dotenv->safeLoad();
			$value = $_ENV[ $key ] ?? null;
			return $value !== null ? (string) $value : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Replace all placeholders in string or array.
	 *
	 * @param string|array $data            Data to process.
	 * @param bool         $fail_on_missing Throw or return null if placeholder unresolved.
	 * @return string|array|null Processed data or null on failure.
	 */
	public static function resolve_placeholders( $data, bool $fail_on_missing = true ) {
		if ( is_string( $data ) ) {
			return self::resolve_string_placeholders( $data, $fail_on_missing );
		}
		if ( is_array( $data ) ) {
			return self::resolve_array_placeholders( $data, $fail_on_missing );
		}
		return $data;
	}

	/**
	 * Resolve placeholders in string.
	 *
	 * @param string $data             String possibly containing {{X}}.
	 * @param bool   $fail_on_missing  Fail if any placeholder unresolved.
	 * @return string|null
	 */
	private static function resolve_string_placeholders( string $data, bool $fail_on_missing ): ?string {
		$result = preg_replace_callback( '/\{\{([A-Z0-9_]+)\}\}/', function ( $m ) use ( $fail_on_missing ) {
			$resolved = self::resolve( $m[1] );
			if ( $resolved === null && $fail_on_missing ) {
				throw new \RuntimeException( 'Missing placeholder: {{' . $m[1] . '}}' );
			}
			return $resolved ?? $m[0];
		}, $data );
		return $result;
	}

	/**
	 * Resolve placeholders in array recursively.
	 *
	 * @param array $data            Array.
	 * @param bool  $fail_on_missing Fail if any placeholder unresolved.
	 * @return array|null
	 */
	private static function resolve_array_placeholders( array $data, bool $fail_on_missing ): ?array {
		$result = [];
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) ) {
				try {
					$resolved = self::resolve_string_placeholders( $value, $fail_on_missing );
					$result[ $key ] = $resolved;
				} catch ( \RuntimeException $e ) {
					if ( $fail_on_missing ) {
						throw $e;
					}
					$result[ $key ] = $value;
				}
			} elseif ( is_array( $value ) ) {
				$resolved = self::resolve_array_placeholders( $value, $fail_on_missing );
				$result[ $key ] = $resolved ?? $value;
			} else {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}
}
