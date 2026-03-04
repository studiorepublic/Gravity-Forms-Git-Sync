<?php
/**
 * WP-CLI Import command for Gravity Forms Git Sync.
 *
 * @package GFGitSync
 */

namespace GFGitSync\CLI;

use GFGitSync\Core\Storage;
use GFGitSync\Core\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImportCommand
 */
class ImportCommand {

	/**
	 * Import forms and feeds from JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--form=<sr_key>]
	 * : Import only this form.
	 *
	 * [--mode=<mode>]
	 * : Import mode.
	 * ---
	 * default: sync
	 * options:
	 *   - sync
	 *   - create-only
	 * ---
	 *
	 * [--prune-feeds]
	 * : Delete feeds in DB that are not in JSON.
	 *
	 * [--dry-run]
	 * : Show what would be imported without making changes.
	 *
	 * [--allow-missing-secrets]
	 * : Allow unresolved placeholders (may break feeds).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gf-git-sync import --all
	 *     wp gf-git-sync import --form=contact-us --mode=sync
	 *     wp gf-git-sync import --dry-run
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function __invoke( $args, $assoc_args ): void {
		$form_sr_key = $assoc_args['form'] ?? null;
		$mode = $assoc_args['mode'] ?? 'sync';
		$prune_feeds = isset( $assoc_args['prune-feeds'] );
		$dry_run = isset( $assoc_args['dry-run'] );
		$allow_missing_secrets = isset( $assoc_args['allow-missing-secrets'] );

		$importer = new Importer( null, null, $allow_missing_secrets );
		$result = $importer->import_all( $form_sr_key, $mode, $prune_feeds, $dry_run );

		if ( $dry_run ) {
			\WP_CLI::log( 'Dry run: would import ' . $result['forms'] . ' form(s) and ' . $result['feeds'] . ' feed(s).' );
			return;
		}

		\WP_CLI::success( sprintf( 'Imported %d form(s) and %d feed(s).', $result['forms'], $result['feeds'] ) );
	}
}
