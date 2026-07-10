# ¿Dónde subir cada archivo? Guía de integración por carpetas

> Proyecto Laravel típico con estructura estándar.

## 1) Backend base (CRUD, Seeds, Jobs, Services, Inscripciones)
Copia desde `lsl_teams_autogen_package.zip` hacia tu proyecto:
- `app/Models/Team.php`
- `app/Http/Controllers/Api/TeamController.php`
- `app/Http/Requests/Team/StoreTeamRequest.php`
- `app/Http/Requests/Team/UpdateTeamRequest.php`
- `app/Http/Resources/TeamResource.php`
- `app/Policies/TeamPolicy.php` (opcional)
- `app/Jobs/ProvisionTeamAssets.php` (opcional, placeholder de logo)
- `app/Jobs/GenerateTeamScaffold.php` (autogenera JSON, banner, badge)
- `app/Services/SlugService.php`, `TeamPaletteService.php`, `TeamFileWriter.php`
- `database/migrations/*create_teams_table.php`
- `database/seeders/TeamsTableSeeder.php`
- `storage/seed/teams.json`
- `routes/api.php` (pegar los snippets CRUD y upload)
- `config/team_defaults.php`

## 2) Inscripciones (auto-creación de Team al aprobar)
Copia desde `laravel_autogen_registration`:
- `app/Models/TeamRegistration.php`
- `app/Http/Controllers/Api/TeamRegistrationController.php`
- `app/Http/Requests/Team/StoreTeamRegistrationRequest.php`
- `app/Events/TeamRegistrationApproved.php`
- `app/Listeners/CreateTeamFromRegistration.php`
- `database/migrations/*create_team_registrations_table.php`
- `routes/api.php` (pegar endpoints de inscripción y approve/reject)
- `app/Providers/EventServiceProvider.php` (registrar listener)

## 3) Uploads de media (logo, banner, badge)
Copia desde `laravel_logo_upload`:
- `app/Http/Requests/Team/UploadTeamLogoRequest.php`
- `app/Http/Requests/Team/UploadTeamBannerRequest.php`
- `app/Http/Requests/Team/UploadTeamBadgeRequest.php`
- En el `TeamController` agrega el **trait** con los métodos de upload o pega los métodos.
- `routes/api.php` (endpoints de upload)

## 4) Panel Admin (Filament)
Copia desde `lsl_filament_admin.zip`:
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Filament/Resources/TeamResource.php` y sus Pages
- `app/Filament/Resources/TeamRegistrationResource.php` y sus Pages

**Este archivo** (TeamResource con uploads) debe **reemplazar** el TeamResource anterior:
- `app/Filament/Resources/TeamResource.php`

## 5) Comandos imprescindibles
```bash
composer require filament/filament:"^3.2"
php artisan filament:install
php artisan migrate
php artisan db:seed --class=TeamsTableSeeder
php artisan storage:link
php artisan queue:work
```

## 6) Discos y rutas de archivos
- Los uploads usan el disco `public`. Asegúrate de tener el symlink con `php artisan storage:link`.
- Los archivos se guardan en: `storage/app/public/teams/{slug}/logo.png|jpg|webp|svg`, `banner.*`, `badge.*`.
- En BD se actualiza `branding.logo.primary`, `branding.banner.primary`, `branding.badge.primary` (columna JSON `branding`).

## 7) Uso en el Panel Admin
- En `/admin`, entra a **Teams** → **Edit** y verás los campos de **Logo**, **Banner** y **Badge**.
- Al subir archivos, Filament guarda en `storage/app/public/teams/{slug}` y actualiza el JSON `branding` automáticamente.
- Puedes copiar el URL público directo: `/storage/teams/{slug}/logo.png` (o la extensión subida).

## 8) Frontend
- Datos: `/api/teams/{slug}` o JSON público (si activado): `/api/public/teams/{slug}/json`.
- Imágenes: `/storage/teams/{slug}/logo.png`, `banner.*`, `badge.*`.
