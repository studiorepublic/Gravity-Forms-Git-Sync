# Changelog

All notable changes to Gravity Forms Git Sync are documented in this file.

## [Unreleased]

### Fixed

- **Import used form ID instead of sr_key** — On a fresh site, importing two forms caused the second to overwrite the first because matching sometimes used `form_key` (which can equal form ID) instead of `sr_key` meta. Import now: (1) uses `sr_key` from JSON content when present, otherwise filename; (2) always sets `gf_git_sync_sr_key` on imported forms for consistent lookup; (3) strips form `id` before add so it is never used for matching; (4) matches existing forms by meta and `gf_git_sync_sr_key` only—`form_key` is no longer used to avoid collisions. Feed import uses the canonical sr_key from the form JSON for matching.

## [1.0.5] - 2026-03-11

### Added

- **Visual diff for JSON/DB sync** — When a form is out of sync (`db_ahead`, `json_ahead`, or `conflicts`), a "View diff" action shows a side-by-side comparison of the database vs JSON file using WordPress's `wp_text_diff()`. Handles orphan JSON (only file) and missing JSON (only DB) with single-column previews. Diff styles added to `admin.css` (additions green, deletions red).

### Fixed

- **Feed import on form restore** — When importing a form from JSON (e.g. after removing it from the database), feeds for that form were not restored. Feed import now uses `allow_missing_secrets`, so feeds with unresolved placeholders (e.g. `{{STRIPE_SECRET_KEY}}`) still import; secrets can be configured in the addon settings afterward. Bulk import now also imports feeds for each form.

- **Export only synced one feed** — The Export button and bulk export only exported the form, not its feeds. Export now exports all feeds for each form via `export_feeds_for_form()`. Form save (auto-export) also exports all feeds. Added unique sr_key handling when multiple feeds share the same addon/name (appends _2, _3). Handles `get_feeds` returning WP_Error. The Feeds column used `get_feeds($form_id)` incorrectly (first param is feed_ids); fixed to `get_feeds(null, $form_id, null, null)`.

## [1.0.4] - 2026-03-04

## [1.0.3] - 2025-03-04

### Added

- **GitHub Action for releases** — Pushing a version tag (e.g. `v1.0.3`) triggers `.github/workflows/release.yml`, which builds the self-contained zip and creates the GitHub release with the asset attached. No manual zip upload needed.

## [1.0.2] - 2025-03-04

### Changed

- **Self-contained releases** — GitHub release zips now include the `vendor/` directory. No Composer is required when installing from a release. Added `scripts/build-release.sh` to build the zip; attach the output to each release. Plugin Update Checker uses `enableReleaseAssets()` to serve the zip instead of the source archive.

## [1.0.1] - 2025-03-04

### Added

- **View details on Plugins page** — The "View details" link in the Plugins screen now shows plugin info (description, installation, configuration, changelog) by providing data via the `plugins_api` filter. The plugin is not on WordPress.org, so the modal was previously empty. The "tested" value is set to the current WordPress version to avoid the "not been tested" warning.

### Fixed

- **View JSON on sub-sites** — The "View JSON" button on the Git Sync admin page now uses the correct URL for forms stored under `sites/{blog_id}/forms/` in multisite. Previously it always pointed at `forms/{basename}`, which failed for sub-site forms.

- **Sync status hash comparison** — Status comparison now hashes the same structure as the exporter (normalised form + masked secrets + sr_key/sr_meta) and compares decoded JSON content instead of raw file bytes, fixing false "DB ahead" / "JSON ahead" states when content was semantically identical.
