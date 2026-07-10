# LSL50 Archive

Scaffolding and legacy material moved out of the active tree on 2026-07-09
(Phase 2 cleanup). Nothing here is required to run the current season stack:

- PHP + SQLite (`LSL50_Website_System/config.php`, `data/`)
- Admin (`admin/`)
- Digital Scorebook (`scorer/`)
- Live Control Center (`LSL50_Live_Control_Center/`)
- Active API: `api/control-center-players.php`

## Contents

| Folder | What it is |
|--------|------------|
| `laravel_scaffold/` | Laravel app tree, Filament packs, Composer/artisan helpers, Laravel `public/index.php` |
| `legacy_api/` | Old JSON-file API (`load/save/upsert/delete/upload`) |
| `legacy_installers/` | MySQL `bootstrap.php` + `db/schema.sql` installer path |
| `legacy_dumps/` | Static dumps (`assets.3774`, `app.9855`), empty stubs, composer caches |

To restore any piece: move it back under `LSL50_Website_System/` (or review before deleting permanently).
