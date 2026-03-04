# Changelog

All notable changes to Gravity Forms Git Sync are documented in this file.

## [Unreleased]

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
