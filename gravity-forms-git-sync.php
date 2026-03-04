<?php
/**
 * Plugin Name: Gravity Forms Git Sync
 * Description: Store Gravity Forms and feeds as JSON in Git, sync via admin UI or WP-CLI.
 * Version: 1.0.3
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Studio Republic
 * License: GPL v2 or later
 * Text Domain: gravity-forms-git-sync
 *
 * @package GFGitSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GF_GIT_SYNC_VERSION', '1.0.3' );
define( 'GF_GIT_SYNC_PLUGIN_FILE', __FILE__ );
define( 'GF_GIT_SYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Populate View details modal for plugins not on WordPress.org.
 */
add_filter( 'plugins_api', 'gf_git_sync_plugins_api', 10, 3 );
function gf_git_sync_plugins_api( $result, $action, $args ) {
	if ( $action !== 'plugin_information' ) {
		return $result;
	}
	$slug = isset( $args->slug ) ? $args->slug : '';
	if ( $slug !== 'gravity-forms-git-sync' ) {
		return $result;
	}

	$readme = GF_GIT_SYNC_PLUGIN_DIR . 'readme.txt';
	$readme_content = file_exists( $readme ) ? file_get_contents( $readme ) : '';

	$info = new \stdClass();
	$info->name = 'Gravity Forms Git Sync';
	$info->slug = 'gravity-forms-git-sync';
	$info->version = GF_GIT_SYNC_VERSION;
	$info->author = 'Studio Republic';
	$info->author_profile = '';
	$info->requires = '6.0';
	$info->tested = get_bloginfo( 'version' );
	$info->requires_php = '8.1';
	$info->last_updated = gmdate( 'Y-m-d', filemtime( GF_GIT_SYNC_PLUGIN_FILE ) );
	$info->homepage = 'https://github.com/studiorepublic/Gravity-Forms-Git-Sync';
	$info->short_description = __( 'Store Gravity Forms and add-on feeds as JSON in Git. Sync via admin UI or WP-CLI.', 'gravity-forms-git-sync' );
	$info->sections = [
		'description'  => gf_git_sync_parse_readme_section( $readme_content, 'Description' ),
		'installation' => gf_git_sync_parse_readme_section( $readme_content, 'Installation' ),
		'configuration' => gf_git_sync_parse_readme_section( $readme_content, 'Configuration' ),
		'changelog'    => gf_git_sync_parse_readme_section( $readme_content, 'Changelog' ),
	];

	return $info;
}

/**
 * Parse a section from readme.txt.
 *
 * @param string $content Full readme content.
 * @param string $section Section name (e.g. Description, Installation).
 * @return string HTML.
 */
function gf_git_sync_parse_readme_section( $content, $section ) {
	if ( ! $content ) {
		return '<p>' . esc_html__( 'See README.md in the plugin directory.', 'gravity-forms-git-sync' ) . '</p>';
	}
	$section_header = "== {$section} ==";
	$pos = stripos( $content, $section_header );
	if ( $pos === false ) {
		return '';
	}
	$start = $pos + strlen( $section_header );
	$next = preg_match( '/\n== [^\n]+ ==\s*\n/', $content, $m, PREG_OFFSET_CAPTURE, $start )
		? $m[0][1]
		: strlen( $content );
	$text = trim( substr( $content, $start, $next - $start ) );
	$text = wp_kses( $text, [ 'a' => [ 'href' => [] ], 'code' => [], 'em' => [], 'strong' => [], 'br' => [] ] );
	return wpautop( $text );
}

/**
 * Deactivate the plugin (handles network-wide deactivation).
 */
function gf_git_sync_deactivate() {
	$plugin = plugin_basename( GF_GIT_SYNC_PLUGIN_FILE );
	$network_wide = is_multisite() && is_plugin_active_for_network( $plugin );
	deactivate_plugins( $plugin, false, $network_wide );
}

/**
 * Admin notice when Gravity Forms is missing.
 */
function gf_git_sync_missing_gf_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Gravity Forms Git Sync requires Gravity Forms to be installed and active.', 'gravity-forms-git-sync' ); ?></p>
	</div>
	<?php
}

/**
 * Bootstrap: defer until plugins_loaded so Gravity Forms has a chance to load first.
 * Plugin load order is alphabetical; gravity-forms-git-sync loads before gravityforms
 * otherwise, causing a false negative on the dependency check.
 */
add_action( 'plugins_loaded', 'gf_git_sync_bootstrap', 999 );
function gf_git_sync_bootstrap() {
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_init', 'gf_git_sync_deactivate' );
		add_action( 'admin_notices', 'gf_git_sync_missing_gf_notice' );
		return;
	}

	// Composer autoloader.
	$autoload = GF_GIT_SYNC_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoload ) ) {
		require_once $autoload;
	}

	gf_git_sync_init_update_checker();
	gf_git_sync_init();
}

/**
 * Plugin Update Checker (GitHub releases).
 */
function gf_git_sync_init_update_checker() {
	if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}
	$repo_url = apply_filters( 'gf_git_sync_update_repo_url', 'https://github.com/studiorepublic/Gravity-Forms-Git-Sync' );
	$checker  = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		$repo_url,
		GF_GIT_SYNC_PLUGIN_FILE,
		'gravity-forms-git-sync'
	);
	// Use release assets (self-contained zip with vendor) instead of source zip.
	$checker->getVcsApi()->enableReleaseAssets( '/\.zip$/' );
}

/**
 * Load plugin core.
 */
function gf_git_sync_init() {
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Storage.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Hashing.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Logger.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Locks.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Transformers.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/FieldMapper.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/FeedExporter.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Exporter.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/AutoExport.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/FormSettings.php';
	\GFGitSync\Core\FormSettings::register();
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/EnvResolver.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/FeedImporter.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Core/Importer.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Admin/SyncStatusPage.php';
	require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/Admin/ActionsController.php';
	\GFGitSync\Admin\SyncStatusPage::register();
	\GFGitSync\Admin\ActionsController::register();
	if ( class_exists( 'WP_CLI' ) ) {
		require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/CLI/ExportCommand.php';
		require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/CLI/ImportCommand.php';
		require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/CLI/ValidateCommand.php';
		require_once GF_GIT_SYNC_PLUGIN_DIR . 'src/CLI/Commands.php';
		\GFGitSync\CLI\Commands::register();
	}
	gf_git_sync_register_hooks();
}

/**
 * Register auto-export and other hooks.
 */
function gf_git_sync_register_hooks() {
	\GFGitSync\Core\AutoExport::register();
}
