=== Gravity Forms Git Sync ===

Contributors: concordia
Tags: gravity forms, git, sync, forms, export, import, version control
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Store Gravity Forms and add-on feeds as JSON in Git. Sync via admin UI or WP-CLI.

== Description ==

Gravity Forms Git Sync mirrors the ACF Local JSON workflow for Gravity Forms:

* Auto-export forms and feeds to JSON when saved in WP Admin
* Sync Status admin page (Forms → Git Sync) with export/import actions
* WP-CLI commands for CI/CD: `wp gf-git-sync export`, `wp gf-git-sync import`, `wp gf-git-sync validate`
* Deterministic JSON for clean Git diffs
* Stable field keys (sr:...) for cross-environment feed mapping
* Secret placeholders ({{STRIPE_SECRET_KEY}}, etc.) resolved on import

== Installation ==

1. Install and activate Gravity Forms.
2. Upload the plugin to wp-content/plugins/gravity-forms-git-sync.
3. Activate via Plugins screen.
4. Run `composer install` in the plugin directory (or use a pre-built release with vendor/).
5. JSON files are stored in the active theme: wp-content/themes/YOUR-THEME/sync/gravity-forms/

== Configuration ==

* `GF_GIT_SYNC_BASE_PATH` — Override storage path (default: theme/sync/gravity-forms)
* `GF_GIT_SYNC_AUTO_EXPORT` — Enable auto-export (default: true on non-production)
* `GF_GIT_SYNC_AUTO_EXPORT_ON_PROD` — Allow auto-export on production (default: false)
* `GF_GIT_SYNC_ARCHIVE_DELETES` — Archive deleted forms/feeds (default: true)

== Changelog ==

= 1.0.1 =
* View details on Plugins page (plugins_api filter for non-wp.org plugins)
* Fix View JSON URL for multisite sub-sites (sites/{blog_id}/forms/)
* Fix sync status hash comparison (false DB/JSON ahead states)

= 1.0.0 =
* Initial release
* Auto-export on form/feed save and delete
* Sync Status admin page
* WP-CLI export, import, validate commands
* Field mapping and addon registry (Stripe, Mailchimp, Webhooks)
* Multisite support
