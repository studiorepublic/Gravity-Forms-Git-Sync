<?php
/**
 * Auto-export hooks for Gravity Forms Git Sync.
 *
 * Exports forms and feeds to JSON when saved in WP Admin.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AutoExport
 */
class AutoExport {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'gform_after_save_form', [ __CLASS__, 'on_form_save' ], 10, 2 );
		add_action( 'gform_post_save_feed_settings', [ __CLASS__, 'on_feed_save' ], 10, 4 );
		add_action( 'gform_before_delete_form', [ __CLASS__, 'on_form_delete' ], 10, 1 );
		add_action( 'gform_pre_delete_feed', [ __CLASS__, 'on_feed_delete' ], 10, 1 );
	}

	/**
	 * Check if auto-export is enabled.
	 *
	 * @return bool
	 */
	private static function should_auto_export(): bool {
		$on_prod = defined( 'GF_GIT_SYNC_AUTO_EXPORT_ON_PROD' ) && GF_GIT_SYNC_AUTO_EXPORT_ON_PROD;
		if ( $on_prod ) {
			return true;
		}
		$enabled = ! defined( 'GF_GIT_SYNC_AUTO_EXPORT' ) || GF_GIT_SYNC_AUTO_EXPORT;
		if ( ! $enabled ) {
			return false;
		}
		// Default: disabled on production.
		$is_production = defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production';
		if ( $is_production && ! $on_prod ) {
			return false;
		}
		return true;
	}

	/**
	 * Handle form save.
	 *
	 * @param array $form   Form array.
	 * @param bool  $is_new Whether form is new.
	 */
	public static function on_form_save( $form, $is_new = false ): void {
		if ( ! is_array( $form ) ) {
			return;
		}
		if ( ! self::should_auto_export() ) {
			return;
		}
		$form_id = (int) ( $form['id'] ?? 0 );
		if ( ! apply_filters( 'gf_git_sync_should_auto_export_form', true, $form_id, [ 'is_new' => $is_new ] ) ) {
			return;
		}
		$exporter = new Exporter();
		$exporter->export_form( $form );
		$exporter->export_feeds_for_form( $form );
	}

	/**
	 * Handle feed save.
	 *
	 * @param string $feed_id   Feed ID.
	 * @param int    $form_id   Form ID.
	 * @param array  $settings  Feed settings.
	 * @param object $addon     GF addon instance.
	 */
	public static function on_feed_save( $feed_id, $form_id, $settings, $addon ): void {
		if ( ! self::should_auto_export() ) {
			return;
		}
		if ( ! apply_filters( 'gf_git_sync_should_auto_export_feed', true, $feed_id, [ 'form_id' => $form_id ] ) ) {
			return;
		}
		$feed = \GFAPI::get_feed( $feed_id );
		$form = \GFAPI::get_form( (int) $form_id );
		if ( ! $feed || ! $form ) {
			return;
		}
		$exporter = new Exporter();
		$exporter->export_feed( $feed, $form );
	}

	/**
	 * Handle form delete.
	 *
	 * @param int $form_id Form ID.
	 */
	public static function on_form_delete( $form_id ): void {
		if ( ! self::should_auto_export() ) {
			return;
		}
		$exporter = new Exporter();
		$exporter->archive_form( (int) $form_id );
	}

	/**
	 * Handle feed delete.
	 *
	 * @param int $feed_id Feed ID.
	 */
	public static function on_feed_delete( $feed_id ): void {
		if ( ! self::should_auto_export() ) {
			return;
		}
		$exporter = new Exporter();
		$exporter->archive_feed( (int) $feed_id );
	}
}
