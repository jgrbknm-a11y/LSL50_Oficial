# Integración paso a paso: Autogenerar slug, colores, banderines, JSON y archivos del equipo

## 0) Requisitos previos
- Migración y Modelo `Team` (ya lo tienes).
- CRUD y API de `Team` (ya lo tienes).
- Módulo de Inscripciones (TeamRegistration) con aprobación y evento (ya lo tienes).
- `php artisan storage:link` para exponer /storage público.

## 1) Copiar nuevos archivos
- app/Jobs/GenerateTeamScaffold.php
- app/Services/TeamPaletteService.php
- app/Services/TeamFileWriter.php
- config/team_defaults.php
- routes/api.php -> snippet para `/api/public/teams/{slug}/json` (opcional)

## 2) Registrar el config
En `config/app.php` asegúrate que `config/team_defaults.php` esté disponible (Laravel lo carga automáticamente si el archivo existe).
Si quieres, haz `php artisan config:clear` para refrescar.

## 3) Actualizar el Listener
En `App/Listeners/CreateTeamFromRegistration.php`, añade:
```php
use App\Jobs\GenerateTeamScaffold;
// ...
GenerateTeamScaffold::dispatch($team->id);
```
(Mantén `ProvisionTeamAssets` si quieres el logo.png placeholder también.)

## 4) Cola de trabajos
- Asegúrate de correr `php artisan queue:work`.
- Si no usas colas, puedes cambiar los Jobs por servicios directos: `GenerateTeamScaffold::dispatchSync($team->id)`.

## 5) ¿Qué se genera automáticamente?
- **Slug único** (por `SlugService`) al aprobar la inscripción.
- **Colores** por defecto (de paleta rotativa) si el registro no los trae.
- `storage/app/public/teams/{slug}/team.json` con toda la ficha técnica.
- `storage/app/public/teams/{slug}/banner.png` (banderín / cubierta) placeholder.
- `storage/app/public/teams/{slug}/badge.png` (escudo cuadrado) placeholder.
- (Si usas `ProvisionTeamAssets`): `logo.png` placeholder y actualización de `branding.logo.primary`.

## 6) Consumo desde el frontend
- API Eloquent: `/api/teams/{slug}` (Controller) para datos en vivo.
- JSON estático: `/api/public/teams/{slug}/json` para cache/CDN o consumo directo.
- Assets: `/storage/teams/{slug}/banner.png`, `badge.png`, `logo.png`

## 7) Sobrescritura por el manager del equipo
- Usa el endpoint `POST /api/teams/{slug}/logo` para subir logo final.
- Crea endpoints similares para `banner` y `badge` si lo deseas.
- También puedes editar colores y descripciones vía `PUT /api/teams/{slug}`.

## 8) Seguridad / permisos
- Mantén creación de inscripciones pública (con reCAPTCHA).
- Aprobación/rechazo bajo `auth:sanctum` (solo admin).
- Upload de logos bajo `auth:sanctum` o rol *Team Manager*.

## 9) Flujo completo (resumen)
1. POST /api/team-registrations  → status=pending.
2. Admin aprueba → POST /api/team-registrations/{id}/approve
3. Listener crea Team (slug único) + encola GenerateTeamScaffold (+ ProvisionTeamAssets)
4. Se generan JSON y assets.
5. Frontend ya puede consumir `/api/teams/{slug}` o `/api/public/teams/{slug}/json`.

## 10) Personalización
- Edita `TeamPaletteService::$palettes` para paletas institucionales.
- Reemplaza placeholders en `config/team_defaults.php` por PNGs base64 propios.
