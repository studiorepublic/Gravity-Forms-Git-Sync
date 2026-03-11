<?php
/**
 * WP-CLI Export command for Gravity Forms Git Sync.
 *
 * @package GFGitSync
 */

namespace GFGitSync\CLI;

use GFGitSync\Core\Storage;
use GFGitSync\Core\Exporter;
use GFGitSync\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExportCommand
 */
class ExportCommand {

	/**
	 * Export forms and feeds to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--form=<sr_key>]
	 * : Export only this form (by sr_key).
	 *
	 * [--all]
	 * : Export all forms and their feeds.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gf-git-sync export --all
	 *     wp gf-git-sync export --form=contact-us
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function __invoke( $args, $assoc_args ): void {
		$form_sr_key = $assoc_args['form'] ?? null;
		$all = isset( $assoc_args['all'] );

		if ( ! $form_sr_key && ! $all ) {
			\WP_CLI::error( 'Specify --form=<sr_key> or --all.' );
		}

		$blog_ids = $this->get_blog_ids_to_export( $all );
		$total_forms = 0;
		$total_feeds = 0;

		foreach ( $blog_ids as $blog_id ) {
			if ( is_multisite() ) {
				switch_to_blog( $blog_id );
			}

			$result = $this->export_for_site( $form_sr_key, $blog_id );
			$total_forms += $result['forms'];
			$total_feeds += $result['feeds'];

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

		\WP_CLI::success( sprintf( 'Exported %d form(s) and %d feed(s).', $total_forms, $total_feeds ) );
	}

	/**
	 * Get blog IDs to export (current site only, or all sites in multisite when --all).
	 *
	 * @param bool $all Export all sites.
	 * @return int[]
	 */
	private function get_blog_ids_to_export( bool $all ): array {
		if ( ! is_multisite() || ! $all ) {
			return [ get_current_blog_id() ];
		}
		$sites = get_sites( [ 'number' => 10000, 'deleted' => 0 ] );
		$ids = [];
		foreach ( $sites as $site ) {
			$ids[] = (int) $site->blog_id;
		}
		return $ids;
	}

	/**
	 * Export forms and feeds for the current blog context.
	 *
	 * @param string|null $form_sr_key Optional form filter.
	 * @param int         $blog_id    Blog ID (for logging).
	 * @return array{forms: int, feeds: int}
	 */
	private function export_for_site( ?string $form_sr_key, int $blog_id ): array {
		$storage = new Storage();
		if ( ! $storage->is_writable() ) {
			\WP_CLI::warning( 'Site ' . $blog_id . ': Base path not writable: ' . $storage->get_base_path() );
			return [ 'forms' => 0, 'feeds' => 0 ];
		}

		$exporter = new Exporter();
		$forms = \GFAPI::get_forms();
		$exported = 0;
		$feed_count = 0;

		$site_label = is_multisite() ? ' [site ' . $blog_id . ']' : '';

		foreach ( $forms as $form ) {
			$sr_key = $this->get_form_sr_key( $form );
			if ( $form_sr_key && $sr_key !== $form_sr_key ) {
				continue;
			}
			if ( $exporter->export_form( $form ) ) {
				$exported++;
				\WP_CLI::log( 'Exported form: ' . $form['title'] . ' (' . $sr_key . ')' . $site_label );
			}
			$feed_count += $exporter->export_feeds_for_form( $form );
			if ( $form_sr_key ) {
				break;
			}
		}

		return [ 'forms' => $exported, 'feeds' => $feed_count ];
	}

	/**
	 * Get form sr_key.
	 *
	 * @param array $form Form data.
	 * @return string
	 */
	private function get_form_sr_key( array $form ): string {
		if ( ! empty( $form['gf_git_sync_sr_key'] ) ) {
			return sanitize_key( (string) $form['gf_git_sync_sr_key'] );
		}
		if ( ! empty( $form['form_key'] ) ) {
			return sanitize_key( (string) $form['form_key'] );
		}
		return sanitize_key( sanitize_title( $form['title'] ?? 'form' ) );
	}
}
