# Agent Notes – Gravity Forms Git Sync

## Overview

WordPress plugin that syncs Gravity Forms and add-on feeds to/from JSON files. Forms are identified by `sr_key` (stable key), not by form ID.

## Key Concepts

- **sr_key**: Stable identifier for a form. Source (in order): `gf_git_sync_sr_key` form setting, then `form_key`, then sanitised title slug.
- **Meta index** (`meta/index.json`): Maps `sr_key` → `db_id`, `json_path`, hashes. Used for sync status and import lookup.
- **Import matching**: Existing forms are matched by `sr_key` only—via meta index first, then `gf_git_sync_sr_key` in the database. `form_key` and form `id` are never used to avoid collisions on fresh imports.

## Import Flow (Importer.php)

1. Resolve canonical `sr_key` from JSON `sr_key` field or filename.
2. Set `gf_git_sync_sr_key` and `sr_key` on the form data; unset `id`.
3. Look up existing form via `find_form_by_sr_key($canonical_sr_key)` (meta, then `gf_git_sync_sr_key`).
4. If found and mode is `sync`: update. Otherwise: add new form.
5. Update meta index with `canonical_sr_key` → `db_id`.

## Feed Import

Feed JSON has `form_sr_key`. Match feeds to forms using the canonical sr_key from the form JSON (not the filename), so feeds align with the correct form when JSON and filename sr_keys differ.

## Files

- `src/Core/Importer.php` – Form and feed import
- `src/Core/Exporter.php` – Form and feed export
- `src/Admin/ActionsController.php` – Export/import actions, `find_form_id`
- `src/Admin/SyncStatusPage.php` – Admin UI, row computation

## Changelog

See CHANGELOG.md. Recent fix: import now uses `sr_key` meta exclusively for matching; `form_key` and form ID are no longer used.
