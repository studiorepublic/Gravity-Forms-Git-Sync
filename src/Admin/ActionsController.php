<?php
/**
 * Actions controller for Gravity Forms Git Sync.
 *
 * Handles export/import actions from the Sync Status page.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Admin;

use GFGitSync\Core\Storage;
use GFGitSync\Core\Exporter;
use GFGitSync\Core\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionsController
 */
class ActionsController {

	/**
	 * Register action hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
		add_action( 'wp_ajax_gf_git_sync_bulk_export', [ __CLASS__, 'ajax_bulk_export' ] );
		add_action( 'wp_ajax_gf_git_sync_bulk_import', [ __CLASS__, 'ajax_bulk_import' ] );
	}

	/**
	 * Handle redirect-based export/import actions.
	 */
	public static function handle_actions(): void {
		if ( ! isset( $_GET['action'] ) || strpos( $_GET['action'], 'gf_git_sync_' ) !== 0 ) {
			return;
		}
		$action = sanitize_text_field( $_GET['action'] );
		$sr_key = sanitize_text_field( $_GET['sr_key'] ?? '' );
		$form_id = (int) ( $_GET['form_id'] ?? 0 );

		if ( ! SyncStatusPage::can_access() ) {
			wp_die( esc_html__( 'Permission denied.', 'gravity-forms-git-sync' ) );
		}

		if ( $action === 'gf_git_sync_export' && $sr_key ) {
			check_admin_referer( 'gf_git_sync_export' );
			self::do_export( $sr_key, $form_id );
		}
		if ( $action === 'gf_git_sync_import' && $sr_key ) {
			check_admin_referer( 'gf_git_sync_import' );
			self::do_import( $sr_key, $form_id );
		}
	}

	/**
	 * Perform export for a form.
	 *
	 * @param string $sr_key  Form sr_key.
	 * @param int    $form_id Form ID (optional, for lookup).
	 */
	private static function do_export( string $sr_key, int $form_id ): void {
		$form_id = $form_id ?: self::find_form_id( $sr_key );
		if ( ! $form_id ) {
			wp_redirect( add_query_arg( 'gf_git_sync_error', 'form_not_found', self::redirect_base() ) );
			exit;
		}
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form ) {
			wp_redirect( add_query_arg( 'gf_git_sync_error', 'form_not_found', self::redirect_base() ) );
			exit;
		}
		$exporter = new Exporter();
		$ok = $exporter->export_form( $form );
		$status = $ok ? 'exported' : 'export_failed';
		wp_redirect( add_query_arg( 'gf_git_sync_status', $status, self::redirect_base() ) );
		exit;
	}

	/**
	 * Perform import for a form.
	 *
	 * @param string $sr_key  Form sr_key.
	 * @param int    $form_id Form ID (optional).
	 */
	private static function do_import( string $sr_key, int $form_id ): void {
		$importer = new Importer();
		$form_id = $importer->import_form( $sr_key, 'sync' );
		if ( ! $form_id ) {
			wp_redirect( add_query_arg( 'gf_git_sync_error', 'import_failed', self::redirect_base() ) );
			exit;
		}
		// Import feeds for this form.
		$storage = $importer->get_storage();
		$feeds_dir = $storage->get_base_path() . '/feeds';
		$feed_files = is_dir( $feeds_dir ) ? glob( $feeds_dir . '/*.feed.json' ) : [];
		foreach ( $feed_files ?? [] as $feed_path ) {
			$data = $storage->read_json( $feed_path );
			if ( $data && ( $data['form_sr_key'] ?? '' ) === $sr_key ) {
				$feed_sr_key = $data['sr_key'] ?? basename( $feed_path, '.feed.json' );
				$importer->import_feed( $feed_sr_key, $form_id, 'sync' );
			}
		}
		wp_redirect( add_query_arg( 'gf_git_sync_status', 'imported', self::redirect_base() ) );
		exit;
	}

	/**
	 * AJAX bulk export.
	 */
	public static function ajax_bulk_export(): void {
		check_ajax_referer( 'gf_git_sync', 'nonce' );
		if ( ! SyncStatusPage::can_access() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'gravity-forms-git-sync' ) ] );
		}
		$status = SyncStatusPage::compute_status( new Storage() );
		$count = 0;
		$exporter = new Exporter();
		foreach ( $status['rows'] as $row ) {
			if ( ( $row['can_export'] ?? false ) && ! empty( $row['form_id'] ) ) {
				$form = \GFAPI::get_form( $row['form_id'] );
				if ( $form && $exporter->export_form( $form ) ) {
					$count++;
				}
			}
		}
		wp_send_json_success( [ 'count' => $count ] );
	}

	/**
	 * AJAX bulk import.
	 */
	public static function ajax_bulk_import(): void {
		check_ajax_referer( 'gf_git_sync', 'nonce' );
		if ( ! SyncStatusPage::can_access() ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'gravity-forms-git-sync' ) ] );
		}
		$status = SyncStatusPage::compute_status( new Storage() );
		$count = 0;
		$importer = new Importer();
		foreach ( $status['rows'] as $row ) {
			if ( ( $row['can_import'] ?? false ) && ! empty( $row['sr_key'] ) ) {
				$form_id = $importer->import_form( $row['sr_key'], 'sync' );
				if ( $form_id ) {
					$count++;
				}
			}
		}
		wp_send_json_success( [ 'count' => $count ] );
	}

	/**
	 * Find form ID by sr_key.
	 *
	 * @param string $sr_key Sr key.
	 * @return int|null
	 */
	private static function find_form_id( string $sr_key ): ?int {
		$forms = \GFAPI::get_forms();
		foreach ( $forms as $form ) {
			$fk = $form['form_key'] ?? '';
			$gk = $form['gf_git_sync_sr_key'] ?? '';
			if ( ( $fk && $fk === $sr_key ) || $gk === $sr_key ) {
				return (int) $form['id'];
			}
			$title_slug = sanitize_key( sanitize_title( $form['title'] ?? '' ) );
			if ( $title_slug === $sr_key ) {
				return (int) $form['id'];
			}
		}
		return null;
	}

	/**
	 * Redirect base URL.
	 *
	 * @return string
	 */
	private static function redirect_base(): string {
		return admin_url( 'admin.php?page=gf-git-sync' );
	}
}
