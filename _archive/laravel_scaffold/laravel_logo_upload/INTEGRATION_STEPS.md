# Integración completa con el sistema (paso a paso)

## 1) Copia estos archivos
- app/Http/Requests/Team/UploadTeamLogoRequest.php
- (Snippet) app/Http/Controllers/Api/TeamController.php -> añade el trait TeamLogoUpload y el método uploadLogo()
- routes/api.php -> pega la ruta POST /api/teams/{slug}/logo del snippet

## 2) Fusiona el método en TeamController
- Abre App/Http/Controllers/Api/TeamController.php
- Agrega: `use TeamLogoUpload;` arriba dentro de la clase, e incluye el trait (puedes pegar el método directamente si prefieres).
- Asegúrate de importar:
  ```php
  use App\Http\Requests\Team\UploadTeamLogoRequest;
  use Illuminate\Support\Facades\Storage;
  ```

## 3) Habilita archivos públicos
- En el servidor (Hostinger o donde corra tu app):
  ```bash
  php artisan storage:link
  ```
- Verifica que `FILESYSTEM_DISK=public` (o `FILESYSTEM_DRIVER`) esté configurado en `.env`
- Revisa `config/filesystems.php` -> disk `public` apunta a `storage/app/public` y `url` a `env('APP_URL').'/storage'`

## 4) Seguridad / Auth
- Protege la ruta de subida de logo con `auth:sanctum` o middleware de admin.
  ```php
  Route::middleware('auth:sanctum')->post('/teams/{slug}/logo', [TeamController::class, 'uploadLogo']);
  ```
- Si usas CORS para panel admin externo, ajusta `config/cors.php` para permitir tu dominio.

## 5) Probar con cURL
```bash
curl -X POST \
  -H "Accept: application/json" \
  -F "logo=@/ruta/local/logo_caribenos.png" \
  https://TU_DOMINIO/api/teams/caribenos/logo
```

## 6) Auto-creación de equipos
- Usa los endpoints de inscripción (team-registrations) para pre-registrar equipos.
- Al **aprobar** se crea el Team automáticamente y se prepara la carpeta `/storage/teams/{slug}`.
- Luego sube el logo vía `POST /api/teams/{slug}/logo`.

## 7) Integración con el Frontend (ejemplo)
- Lista equipos: GET /api/teams
- Detalle de un equipo: GET /api/teams/{slug}
- Mostrar logo: renderiza `branding.logo.primary`
- Si falta el logo, muestra un placeholder (ej. `/images/placeholders/team.png`).

## 8) Deploy en Hostinger (tips rápidos)
- Directorio público: apunta tu dominio a `public/` de Laravel.
- Permisos: `storage/` y `bootstrap/cache/` deben ser escribibles.
- Colas: si usas jobs (ProvisionTeamAssets), ejecuta `php artisan queue:work` o usa `supervisor` en hosting compatible.
- SSL: configura tu certificado y fuerza HTTPS (APP_URL con https).

## 9) Semillas/seed
- Genera equipos base: `php artisan db:seed --class=TeamsTableSeeder`
- Si editas `teams_index.json`, puedes regenerar `storage/seed/teams.json` con el helper Python local si lo necesitas.

## 10) Resumen de endpoints
- GET    /api/teams
- GET    /api/teams/{slug}
- POST   /api/teams
- PUT    /api/teams/{slug}
- PATCH  /api/teams/{slug}
- DELETE /api/teams/{slug}
- POST   /api/teams/{slug}/logo
- POST   /api/team-registrations        (público)
- GET    /api/team-registrations        (admin)
- POST   /api/team-registrations/{id}/approve (admin)
- POST   /api/team-registrations/{id}/reject  (admin)
