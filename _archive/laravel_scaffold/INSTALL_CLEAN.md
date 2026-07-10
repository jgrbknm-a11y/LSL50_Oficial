# LSL Full Stack Package — CLEAN Overlay
Generated at: 2025-10-20T22:48:03.672107Z

Extract this ZIP at the **root of your Laravel project** (same folder as `artisan`).

1) Paste API routes from `docs/snippets/*.php` into `routes/api.php`.
2) Register the event listener from `docs/snippets/EventServiceProvider.php` in `app/Providers/EventServiceProvider.php`.
3) Run:
   composer install --no-dev -o
   php artisan key:generate
   php artisan migrate
   php artisan db:seed --class=TeamsTableSeeder
   php artisan storage:link
4) (Optional) Start queues: php artisan queue:work