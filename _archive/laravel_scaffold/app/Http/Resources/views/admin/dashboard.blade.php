<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | LSL</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f9fafb;margin:0}
    header{background:#0a74da;color:#fff;padding:16px 20px;display:flex;justify-content:space-between;align-items:center}
    .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
    .card{background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.05);padding:20px;margin-bottom:16px}
    .btn{background:#ef4444;color:#fff;border:none;padding:8px 12px;border-radius:10px;cursor:pointer}
    h1{margin:0;font-size:20px}
    h2{margin:0 0 12px;font-size:18px}
  </style>
</head>
<body>
  <header>
    <h1>LSL — Panel de administración</h1>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button class="btn" type="submit">Cerrar sesión</button>
    </form>
  </header>

  <div class="wrap">
    <div class="card">
      <h2>Bienvenido, {{ auth()->user()->name }}</h2>
      <p>Tu cuenta es de <strong>Administrador</strong>. Desde aquí podrás gestionar ligas, temporadas, equipos y jugadores.</p>
    </div>

    <div class="card">
      <h2>Accesos rápidos</h2>
      <ul>
        <li>• Ligas y temporadas</li>
        <li>• Equipos y rosters</li>
        <li>• Juegos, box score y estadísticas</li>
      </ul>
      <p>(Estos módulos los activamos en el siguiente paso.)</p>
    </div>
  </div>
</body>
</html>