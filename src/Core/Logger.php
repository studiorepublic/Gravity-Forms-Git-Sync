<?php
/**
 * Logger for Gravity Forms Git Sync.
 *
 * Logs to WP_CLI when available, otherwise optional file/admin notices.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 */
class Logger {

	/**
	 * Log file path (optional).
	 *
	 * @var string|null
	 */
	private static $log_file;

	/**
	 * Set log file path.
	 *
	 * @param string $path File path.
	 */
	public static function set_log_file( string $path ): void {
		self::$log_file = $path;
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function log( string $message, array $context = [] ): void {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::log( $message );
			return;
		}
		self::write_file( 'INFO', $message, $context );
	}

	/**
	 * Log warning.
	 *
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function warning( string $message, array $context = [] ): void {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::warning( $message );
			return;
		}
		self::write_file( 'WARNING', $message, $context );
	}

	/**
	 * Log error.
	 *
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function error( string $message, array $context = [] ): void {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::error( $message, false );
			return;
		}
		self::write_file( 'ERROR', $message, $context );
	}

	/**
	 * Write to log file.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context (never log secret values).
	 */
	private static function write_file( string $level, string $message, array $context ): void {
		if ( ! self::$log_file ) {
			return;
		}
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$line = sprintf( "[%s] %s: %s", $timestamp, $level, $message );
		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}
		$line .= "\n";
		error_log( $line, 3, self::$log_file );
	}
}
