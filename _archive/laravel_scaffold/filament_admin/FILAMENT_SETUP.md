# Filament Admin — Configuración rápida

## 1) Instala Filament
```bash
composer require filament/filament:"^3.2"
php artisan filament:install
```

## 2) Copia estos archivos al proyecto
- app/Providers/Filament/AdminPanelProvider.php
- app/Filament/Resources/TeamResource.php (+ Pages)
- app/Filament/Resources/TeamRegistrationResource.php (+ Pages)

## 3) Registrar el Panel
En `config/filament.php`, asegúrate que el provider exista o añade el provider a `config/app.php` si no se auto-descubre:
```php
App\Providers\Filament\AdminPanelProvider::class,
```

## 4) Acceso
- Panel en `/admin`.
- Usa auth de Laravel (puedes proteger con policies o middleware).

## 5) Qué puedes hacer desde el Panel
- Gestionar **Equipos**: crear/editar/eliminar (con validaciones y slug auto).
- Gestionar **Inscripciones**: ver, **aprobar/rechazar** (dispara evento y jobs).
