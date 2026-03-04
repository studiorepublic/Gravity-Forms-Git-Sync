# Changelog

All notable changes to Gravity Forms Git Sync are documented in this file.

## [Unreleased]

### Fixed

- **View JSON on sub-sites** — The "View JSON" button on the Git Sync admin page now uses the correct URL for forms stored under `sites/{blog_id}/forms/` in multisite. Previously it always pointed at `forms/{basename}`, which failed for sub-site forms.

- **Sync status hash comparison** — Status comparison now hashes the same structure as the exporter (normalised form + masked secrets + sr_key/sr_meta) and compares decoded JSON content instead of raw file bytes, fixing false "DB ahead" / "JSON ahead" states when content was semantically identical.
