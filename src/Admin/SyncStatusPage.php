<?php
/**
 * Sync Status admin page for Gravity Forms Git Sync.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Admin;

use GFGitSync\Core\Storage;
use GFGitSync\Core\Hashing;
use GFGitSync\Core\Exporter;
use GFGitSync\Core\Importer;
use GFGitSync\Core\Transformers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SyncStatusPage
 */
class SyncStatusPage {

	/**
	 * Register admin page.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Add menu under Forms.
	 */
	public static function add_menu(): void {
		if ( ! self::can_access() ) {
			return;
		}
		add_submenu_page(
			'gf_edit_forms',
			__( 'Git Sync', 'gravity-forms-git-sync' ),
			__( 'Git Sync', 'gravity-forms-git-sync' ),
			self::get_capability(),
			'gf-git-sync',
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Page hook.
	 */
	public static function enqueue_assets( $hook ): void {
		if ( $hook !== 'forms_page_gf-git-sync' ) {
			return;
		}
		wp_enqueue_style(
			'gf-git-sync-admin',
			plugins_url( 'assets/admin.css', GF_GIT_SYNC_PLUGIN_FILE ),
			[],
			GF_GIT_SYNC_VERSION
		);
		wp_enqueue_script(
			'gf-git-sync-admin',
			plugins_url( 'assets/admin.js', GF_GIT_SYNC_PLUGIN_FILE ),
			[ 'jquery' ],
			GF_GIT_SYNC_VERSION,
			true
		);
		wp_localize_script( 'gf-git-sync-admin', 'gfGitSync', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gf_git_sync' ),
		] );
	}

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! self::can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gravity-forms-git-sync' ) );
		}

		$storage = new Storage();
		$status = self::compute_status( $storage );

		$writable = $storage->is_writable();
		$status_param = sanitize_text_field( $_GET['gf_git_sync_status'] ?? '' );
		$error_param = sanitize_text_field( $_GET['gf_git_sync_error'] ?? '' );
		?>
		<div class="wrap gf-git-sync-wrap">
			<h1><?php esc_html_e( 'Gravity Forms Git Sync', 'gravity-forms-git-sync' ); ?></h1>
			<?php if ( $status_param === 'exported' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form exported successfully.', 'gravity-forms-git-sync' ); ?></p></div>
			<?php elseif ( $status_param === 'imported' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form imported successfully.', 'gravity-forms-git-sync' ); ?></p></div>
			<?php elseif ( $status_param === 'export_failed' ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Export failed. Check that the base path is writable.', 'gravity-forms-git-sync' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error_param === 'form_not_found' ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Form not found.', 'gravity-forms-git-sync' ); ?></p></div>
			<?php elseif ( $error_param === 'import_failed' ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Import failed. Check for missing placeholders or invalid JSON.', 'gravity-forms-git-sync' ); ?></p></div>
			<?php endif; ?>
			<?php if ( is_multisite() ) : ?>
				<p class="gf-git-sync-site-context">
					<?php
					printf(
						/* translators: %s: site name or URL */
						esc_html__( 'Site: %s', 'gravity-forms-git-sync' ),
						'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong> (' . esc_html( get_site_url() ) . ')'
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! $writable ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Base path is not writable. Export actions are disabled.', 'gravity-forms-git-sync' ); ?></p>
					<p><code><?php echo esc_html( $storage->get_base_path() ); ?></code></p>
				</div>
			<?php endif; ?>

			<div class="gf-git-sync-toolbar">
				<select id="gf-git-sync-filter">
					<option value="all"><?php esc_html_e( 'All statuses', 'gravity-forms-git-sync' ); ?></option>
					<option value="synced"><?php esc_html_e( 'Synced', 'gravity-forms-git-sync' ); ?></option>
					<option value="db_ahead"><?php esc_html_e( 'DB ahead', 'gravity-forms-git-sync' ); ?></option>
					<option value="json_ahead"><?php esc_html_e( 'JSON ahead', 'gravity-forms-git-sync' ); ?></option>
					<option value="missing_json"><?php esc_html_e( 'Missing JSON', 'gravity-forms-git-sync' ); ?></option>
					<option value="orphan_json"><?php esc_html_e( 'Orphan JSON', 'gravity-forms-git-sync' ); ?></option>
				</select>
				<?php if ( $writable ) : ?>
					<button type="button" class="button" id="gf-git-sync-bulk-export"><?php esc_html_e( 'Export all DB ahead', 'gravity-forms-git-sync' ); ?></button>
				<?php endif; ?>
				<button type="button" class="button" id="gf-git-sync-bulk-import"><?php esc_html_e( 'Import all JSON ahead', 'gravity-forms-git-sync' ); ?></button>
			</div>

			<table class="wp-list-table widefat fixed striped gf-git-sync-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Form', 'gravity-forms-git-sync' ); ?></th>
						<th><?php esc_html_e( 'sr_key', 'gravity-forms-git-sync' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gravity-forms-git-sync' ); ?></th>
						<th><?php esc_html_e( 'Feeds', 'gravity-forms-git-sync' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'gravity-forms-git-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $status['rows'] as $row ) : ?>
						<tr data-status="<?php echo esc_attr( $row['status'] ); ?>">
							<td><?php echo esc_html( $row['title'] ); ?></td>
							<td><code><?php echo esc_html( $row['sr_key'] ?? '-' ); ?></code></td>
							<td>
								<span class="gf-git-sync-badge gf-git-sync-badge--<?php echo esc_attr( $row['status'] ); ?>">
									<?php echo esc_html( $row['status_label'] ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $row['feeds_summary'] ?? '' ); ?></td>
							<td>
								<?php if ( $row['can_export'] && $writable ) : ?>
									<a href="<?php echo esc_url( self::action_url( 'export', $row ) ); ?>" class="button button-small"><?php esc_html_e( 'Export', 'gravity-forms-git-sync' ); ?></a>
								<?php endif; ?>
								<?php if ( $row['can_import'] ) : ?>
									<a href="<?php echo esc_url( self::action_url( 'import', $row ) ); ?>" class="button button-small"><?php esc_html_e( 'Import', 'gravity-forms-git-sync' ); ?></a>
								<?php endif; ?>
								<?php if ( ! empty( $row['json_path'] ) && file_exists( $row['json_path'] ) ) : ?>
									<a href="<?php echo esc_url( self::file_url( $row['json_path'] ) ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'View JSON', 'gravity-forms-git-sync' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Compute sync status for all forms.
	 *
	 * @param Storage $storage Storage instance.
	 * @return array
	 */
	public static function compute_status( Storage $storage ): array {
		$forms = \GFAPI::get_forms();
		$meta = $storage->read_json( $storage->get_meta_path() ) ?? [ 'forms' => [], 'feeds' => [] ];
		$rows = [];
		$seen_sr_keys = [];

		foreach ( $forms as $form ) {
			$sr_key = self::get_form_sr_key( $form );
			$seen_sr_keys[ $sr_key ] = true;
			$row = self::compute_row( $form, $sr_key, $storage, $meta );
			$rows[] = $row;
		}

		// Orphan JSON: files without DB form.
		$forms_dir = $storage->get_base_path() . '/forms';
		if ( is_dir( $forms_dir ) ) {
			$files = glob( $forms_dir . '/*.form.json' );
			foreach ( $files ?? [] as $path ) {
				$sr_key = basename( $path, '.form.json' );
				if ( ! isset( $seen_sr_keys[ $sr_key ] ) ) {
					$data = $storage->read_json( $path );
					$rows[] = [
						'title'        => $data['title'] ?? $sr_key,
						'sr_key'       => $sr_key,
						'status'       => 'orphan_json',
						'status_label' => __( 'Orphan JSON', 'gravity-forms-git-sync' ),
						'feeds_summary'=> '',
						'can_export'   => false,
						'can_import'   => true,
						'json_path'    => $path,
					];
				}
			}
		}

		return [ 'rows' => $rows ];
	}

	/**
	 * Compute status row for a form.
	 *
	 * @param array   $form    Form data.
	 * @param string  $sr_key  Sr key.
	 * @param Storage $storage Storage.
	 * @param array   $meta    Meta index.
	 * @return array
	 */
	private static function compute_row( array $form, string $sr_key, Storage $storage, array $meta ): array {
		$form_path = $storage->get_form_path( $sr_key );
		$json_exists = file_exists( $form_path );
		$meta_entry = $meta['forms'][ $sr_key ] ?? [];

		$db_hash = Hashing::hash_form( Transformers::normalise_form( $form ) );
		$json_hash = $json_exists ? Hashing::hash_file( $form_path ) : null;
		$last_exported = $meta_entry['last_exported_hash'] ?? null;
		$last_imported = $meta_entry['last_imported_hash'] ?? null;

		$status = 'synced';
		$status_label = __( 'Synced', 'gravity-forms-git-sync' );
		$can_export = false;
		$can_import = false;

		if ( ! $json_exists ) {
			$status = 'missing_json';
			$status_label = __( 'Missing JSON', 'gravity-forms-git-sync' );
			$can_export = true;
		} elseif ( $json_hash !== $db_hash ) {
			if ( $last_exported === $db_hash ) {
				$status = 'json_ahead';
				$status_label = __( 'JSON ahead', 'gravity-forms-git-sync' );
				$can_import = true;
			} elseif ( $last_imported === $json_hash ) {
				$status = 'db_ahead';
				$status_label = __( 'DB ahead', 'gravity-forms-git-sync' );
				$can_export = true;
			} else {
				$status = 'conflicts';
				$status_label = __( 'Conflicts', 'gravity-forms-git-sync' );
				$can_export = true;
				$can_import = true;
			}
		}

		$feeds = \GFAPI::get_feeds( $form['id'] );
		$feeds_count = is_array( $feeds ) ? count( $feeds ) : 0;
		$feeds_summary = sprintf( _n( '%d feed', '%d feeds', $feeds_count, 'gravity-forms-git-sync' ), $feeds_count );

		return [
			'form_id'      => $form['id'],
			'title'        => $form['title'] ?? (string) $form['id'],
			'sr_key'       => $sr_key,
			'status'       => $status,
			'status_label' => $status_label,
			'feeds_summary'=> $feeds_summary,
			'can_export'   => $can_export,
			'can_import'   => $can_import,
			'json_path'    => $form_path,
		];
	}

	/**
	 * Get form sr_key.
	 *
	 * @param array $form Form data.
	 * @return string
	 */
	private static function get_form_sr_key( array $form ): string {
		if ( ! empty( $form['gf_git_sync_sr_key'] ) ) {
			return sanitize_key( (string) $form['gf_git_sync_sr_key'] );
		}
		if ( ! empty( $form['form_key'] ) ) {
			return sanitize_key( (string) $form['form_key'] );
		}
		$title = $form['title'] ?? 'form';
		return sanitize_key( sanitize_title( $title ) );
	}

	/**
	 * Build action URL.
	 *
	 * @param string $action Action name.
	 * @param array  $row   Row data.
	 * @return string
	 */
	private static function action_url( string $action, array $row ): string {
		return wp_nonce_url(
			add_query_arg( [
				'action'   => 'gf_git_sync_' . $action,
				'sr_key'  => $row['sr_key'] ?? '',
				'form_id' => $row['form_id'] ?? 0,
			], admin_url( 'admin.php' ) ),
			'gf_git_sync_' . $action
		);
	}

	/**
	 * Get file URL for viewing (theme path as relative).
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private static function file_url( string $path ): string {
		$theme = get_stylesheet_directory();
		if ( strpos( $path, $theme ) === 0 ) {
			return get_stylesheet_directory_uri() . '/sync/gravity-forms/forms/' . basename( $path );
		}
		return 'file://' . $path;
	}

	/**
	 * Check if user can access.
	 *
	 * @return bool
	 */
	public static function can_access(): bool {
		return current_user_can( self::get_capability() );
	}

	/**
	 * Get required capability.
	 *
	 * @return string
	 */
	private static function get_capability(): string {
		if ( defined( 'GF_GIT_SYNC_CAPABILITY' ) ) {
			return GF_GIT_SYNC_CAPABILITY;
		}
		if ( function_exists( 'gf_user_has_capability' ) && gf_user_has_capability( 'gform_full_access' ) ) {
			return 'gform_full_access';
		}
		return 'manage_options';
	}
}
