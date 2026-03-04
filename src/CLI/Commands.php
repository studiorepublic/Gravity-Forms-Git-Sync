<?php
/**
 * WP-CLI command registration for Gravity Forms Git Sync.
 *
 * @package GFGitSync
 */

namespace GFGitSync\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Commands
 */
class Commands {

	/**
	 * Register WP-CLI commands.
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'gf-git-sync export', [ new ExportCommand(), '__invoke' ] );
		\WP_CLI::add_command( 'gf-git-sync import', [ new ImportCommand(), '__invoke' ] );
		\WP_CLI::add_command( 'gf-git-sync validate', [ new ValidateCommand(), '__invoke' ] );
	}
}
