<?php
/**
 * WP-CLI Validate command for Gravity Forms Git Sync.
 *
 * @package GFGitSync
 */

namespace GFGitSync\CLI;

use GFGitSync\Core\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ValidateCommand
 */
class ValidateCommand {

	/**
	 * Validate JSON structure and placeholders.
	 *
	 * ## OPTIONS
	 *
	 * [--form=<sr_key>]
	 * : Validate only this form.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gf-git-sync validate
	 *     wp gf-git-sync validate --form=contact-us
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function __invoke( $args, $assoc_args ): void {
		$form_sr_key = $assoc_args['form'] ?? null;
		$storage = new Storage();
		$errors = [];

		$forms_dir = $storage->get_base_path() . '/forms';
		$form_files = $form_sr_key
			? [ $storage->get_form_path( $form_sr_key ) ]
			: ( is_dir( $forms_dir ) ? glob( $forms_dir . '/*.form.json' ) : [] );

		if ( ! $form_files ) {
			\WP_CLI::warning( 'No form JSON files found.' );
			return;
		}

		foreach ( $form_files as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$data = $storage->read_json( $path );
			if ( ! $data ) {
				$errors[] = basename( $path ) . ': Invalid JSON';
				continue;
			}
			if ( empty( $data['sr_key'] ) ) {
				$errors[] = basename( $path ) . ': Missing sr_key';
			}
			if ( empty( $data['title'] ) ) {
				$errors[] = basename( $path ) . ': Missing title';
			}
		}

		$feeds_dir = $storage->get_base_path() . '/feeds';
		$feed_files = is_dir( $feeds_dir ) ? glob( $feeds_dir . '/*.feed.json' ) : [];
		foreach ( $feed_files ?? [] as $path ) {
			$data = $storage->read_json( $path );
			if ( ! $data ) {
				$errors[] = basename( $path ) . ': Invalid JSON';
			} elseif ( empty( $data['form_sr_key'] ) || empty( $data['addon_slug'] ) ) {
				$errors[] = basename( $path ) . ': Missing form_sr_key or addon_slug';
			}
		}

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $e ) {
				\WP_CLI::warning( $e );
			}
			\WP_CLI::halt( 1 );
		}

		\WP_CLI::success( 'Validation passed.' );
	}
}
