# Gravity Forms Git Sync

Store Gravity Forms and add-on feeds as deterministic JSON in Git. Sync via admin UI or WP-CLI. Updates via GitHub releases (Plugin Update Checker).

## Requirements

- WordPress 6+
- PHP 8.1+
- Gravity Forms 2.8+

## Installation

1. Install Gravity Forms.
2. Clone or download this plugin into `wp-content/plugins/gravity-forms-git-sync/`.
3. Run `composer install` in the plugin directory.
4. Activate via Plugins screen.

## Storage

JSON files are stored in the active theme by default:

```
wp-content/themes/YOUR-THEME/sync/gravity-forms/
  forms/       # {sr_key}.form.json
  feeds/       # {sr_key}.feed.json
  maps/        # {sr_key}.map.json (field ID mapping)
  meta/        # index.json (hashes for sync status)
  archive/     # Deleted forms/feeds (if GF_GIT_SYNC_ARCHIVE_DELETES)
```

On multisite, non-main sites use `sites/{blog_id}/` subfolders.

## Usage

### Admin UI

**Forms → Git Sync** — View sync status, export/import individual forms, bulk actions.

### WP-CLI

```bash
# Export all forms and feeds (multisite: exports from all sites)
wp gf-git-sync export --all

# Export a specific form (current site only)
wp gf-git-sync export --form=contact-us

# Import (sync mode)
wp gf-git-sync import

# Import with options
wp gf-git-sync import --form=donate --mode=sync --allow-missing-secrets

# Dry run
wp gf-git-sync import --dry-run

# Validate JSON
wp gf-git-sync validate
```

### Multisite

```bash
wp gf-git-sync export --all --url=concordia.test/seasonal/
wp gf-git-sync import --url=concordia.test/seasonal/
```

## Stable Keys (sr_key)

Forms are identified by `sr_key`. By default:

- Use `form_key` if set (GF 2.5+)
- Otherwise derive from form title slug

Feeds use `{form_sr_key}.{addon_slug}.{feed_name}`.

## Field Mapping

For feeds that reference field IDs, use `sr:` in the field Admin Label (e.g. `sr:email`). The plugin maps these to numeric IDs per environment.

## Placeholders

Secrets are replaced with placeholders on export. Resolve on import via:

1. Environment variables
2. WordPress constants (`GF_GIT_SYNC_STRIPE_SECRET_KEY`, etc.)
3. Optional `.env` (requires vlucas/phpdotenv)

## Configuration

| Constant | Default | Description |
|----------|---------|-------------|
| `GF_GIT_SYNC_BASE_PATH` | theme/sync/gravity-forms | Storage path |
| `GF_GIT_SYNC_AUTO_EXPORT` | true (non-prod) | Auto-export on save |
| `GF_GIT_SYNC_AUTO_EXPORT_ON_PROD` | false | Enable on production |
| `GF_GIT_SYNC_ARCHIVE_DELETES` | true | Archive deleted items |

## Manual Test Checklist

1. Create a new form → confirm JSON created automatically.
2. Edit form → confirm JSON updated.
3. Add Stripe feed → confirm feed JSON created.
4. Admin Sync screen → status reflects changes.
5. Click Export from admin → file updates.
6. Modify JSON in repo → admin shows JSON ahead; click Import → DB updates.
7. `wp gf-git-sync validate` → passes.
