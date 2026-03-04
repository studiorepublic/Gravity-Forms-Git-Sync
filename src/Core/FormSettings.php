<?php
/**
 * Form settings for Gravity Forms Git Sync.
 *
 * Adds sr_key and sr_meta fields to form settings.
 *
 * @package GFGitSync
 */

namespace GFGitSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormSettings
 */
class FormSettings {

	/**
	 * Register filters.
	 */
	public static function register(): void {
	add_filter( 'gform_form_settings_fields', [ __CLASS__, 'add_settings' ], 10, 2 );
}

	/**
	 * Add Git Sync settings section.
	 *
	 * @param array $fields Form settings fields.
	 * @param array $form   Form data.
	 * @return array
	 */
	public static function add_settings( array $fields, array $form ): array {
		$fields['gf_git_sync'] = [
			'title'  => __( 'Git Sync', 'gravity-forms-git-sync' ),
			'fields' => [
				[
					'name'          => 'gf_git_sync_sr_key',
					'label'         => __( 'Sync key (sr_key)', 'gravity-forms-git-sync' ),
					'type'          => 'text',
					'tooltip'       => __( 'Stable identifier for this form in JSON. Used for import/export matching across environments.', 'gravity-forms-git-sync' ),
					'default_value' => ! empty( $form['form_key'] ) ? $form['form_key'] : sanitize_key( sanitize_title( $form['title'] ?? '' ) ),
				],
				[
					'name'    => 'gf_git_sync_sr_meta',
					'label'   => __( 'Sync notes', 'gravity-forms-git-sync' ),
					'type'    => 'textarea',
					'tooltip' => __( 'Optional notes stored in form JSON (e.g. ownership, purpose).', 'gravity-forms-git-sync' ),
				],
			],
		];
		return $fields;
	}
}
