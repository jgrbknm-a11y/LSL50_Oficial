# LSL50 — Arquitectura Unificada Premium

**Plataforma:** legendssoftball50.com  
**Checkpoint:** 2026-07-10  
**Stack activo:** PHP 8 + SQLite (prod local) · PDO abstraction → MySQL/PostgreSQL ready

---

## Visión

Tres módulos integrados bajo un **core de datos único** con recálculo en tiempo real:

```
┌─────────────────────────────────────────────────────────────────┐
│                    SITIO PÚBLICO (Frontend Premium)              │
│  index.php · news.php · assets/css/lsl50-public.css              │
└────────────────────────────┬────────────────────────────────────┘
                             │ lee
┌────────────────────────────▼────────────────────────────────────┐
│              CORE DB (SQLite → MySQL/PostgreSQL)                 │
│  teams · players · games · game_player_stats · game_play_events  │
│  player_stats · team_stats · ai_game_notes · schedule_entries    │
└─▲───────────────────────▲───────────────────────▲───────────────┘
  │                       │                       │
  │ POST closure          │ GET/POST state        │ admin CRUD
┌─┴──────────┐   ┌────────┴────────┐   ┌─────────┴──────────┐
│ SCORER APP │   │ LIVE CONTROL    │   │ ADMIN PANEL        │
│ :8090      │   │ CENTER :5050    │   │ :8080/admin        │
│ AppRouter  │   │ (sin cambios)   │   │ games · ai-pub     │
└─────┬──────┘   └─────────────────┘   └────────────────────┘
      │
      │ api/v1/* (JSON interno)
      └──────────────────────────────────────────────────────
```

---

## Estructura de carpetas (implementada / planificada)

```
LSL50_Official_Project/
├── ARCHITECTURE_LSL50.md          ← este documento
├── docs/
│   └── schema-unified.sql         ← DDL portable MySQL/PostgreSQL/SQLite
├── LSL50_Website_System/
│   ├── config.php                 ← bootstrap DB + recalc + settings
│   ├── index.php                  ← homepage premium
│   ├── news.php                   ← crónica IA + embed YouTube
│   ├── api/
│   │   ├── live-game-state.php    ← OBS / cuaderno (existente)
│   │   ├── control-center-players.php
│   │   └── v1/
│   │       ├── bootstrap.php      ← auth + JSON helpers
│   │       ├── standings.php
│   │       ├── leaders.php
│   │       └── game.php
│   ├── admin/
│   │   ├── games.php              ← + youtube_video_id
│   │   ├── ai-publisher.php
│   │   └── services/              ← legacy shim → src/Services
│   ├── public/assets/css/
│   │   └── lsl50-public.css       ← tema oscuro premium
│   ├── scorer/                    ← app independiente (tablet)
│   │   ├── index.php
│   │   ├── AppRouter.php
│   │   └── Controllers/
│   └── src/
│       ├── Repository/            ← acceso datos
│       ├── Domain/Rules/          ← lógica pura
│       ├── Services/
│       │   ├── StatsEngine.php    ← recalc standings/leaders
│       │   ├── AiNewsGenerator.php← crónica IA post-juego
│       │   └── GameClosurePipeline.php
│       └── Support/
│           └── YoutubeHelper.php
└── LSL50_Live_Control_Center/     ← sin cambios operativos
    └── control.html
```

---

## 1. Core DB — normalización

| Entidad | Tabla | Relaciones |
|---------|-------|------------|
| Equipos | `teams` | 1:N players, team_stats |
| Jugadores | `players` | N:1 team, 1:1 player_stats |
| Partidos | `games` | N:1 teams, 1:N game_player_stats, play_events |
| Box score | `game_player_stats` | por juego/jugador |
| Agregados | `player_stats`, `team_stats` | recalculados vía `StatsEngine` |
| Noticias IA | `ai_game_notes` | 1:1 game_id, video_url embed |
| Transmisión | `games.youtube_video_id` | ID YouTube del partido |

**Regla:** cualquier cambio en `game_player_stats` o cierre oficial → `lsl_recalc_player_stats` + `lsl_recalc_team_stats`.

---

## 2. Scorer App — API interna

| Endpoint | Método | Uso |
|----------|--------|-----|
| `/api/v1/standings.php` | GET | Posiciones en vivo |
| `/api/v1/leaders.php` | GET | Líderes ofensivos |
| `/api/v1/game.php?id=N` | GET | Detalle partido + stats |
| `/api/live-game-state.php` | GET | Overlay OBS (existente) |

Cierre en cuaderno → `GameClosurePipeline::afterGameClosed()` → DB + IA opcional.

---

## 3. AI Sports Writer

**Trigger:** al cerrar juego (`ClosureController`) si `ai_auto_generate_on_close=1`.

**Servicio:** `Lsl50\Services\AiNewsGenerator`
- Contexto: marcador, líderes del juego, PBP, pitcher ganador
- OpenAI Responses API o fallback local
- Guarda en `ai_game_notes`
- Si `ai_publish_mode=auto` → publica inmediatamente

---

## 4. YouTube Live Sync

- Campo admin: `games.youtube_video_id`
- Helper: `YoutubeHelper::embedUrl()`
- Homepage + `news.php`: iframe embed debajo de la crónica

---

## 5. Frontend premium

- CSS compartido: `#0F0F11` fondo, tarjetas `#1A1A1E`, bordes `#2A2A32`
- Scorecards simétricas, noticias multimedia con video
- Sin romper panel de control OBS existente

---

## Migración MySQL/PostgreSQL (fase 2) ✅

1. `DB_DRIVER=mysql` en `.env` (ver `.env.example`)
2. `docker compose up -d mysql` → aplica `docs/schema-mysql.sql`
3. `php LSL50_Website_System/tools/bootstrap_mysql.php`
4. `php LSL50_Website_System/tools/migrate_sqlite_to_mysql.php` (opcional, copia SQLite)
5. Capa PDO: `src/Support/Database.php` + `SqlDialect.php`
6. SQLite sigue siendo default sin `DB_DRIVER`

---

## Comandos de verificación

```bash
php LSL50_Website_System/tools/test_rebuild_rules.php
php -l LSL50_Website_System/src/Services/AiNewsGenerator.php
curl http://127.0.0.1:8080/api/v1/standings.php
```

---

## Estado de implementación

| Componente | Estado |
|------------|--------|
| Schema + youtube_video_id | ✅ Fase 1 |
| StatsEngine + Pipeline cierre | ✅ Fase 1 |
| AiNewsGenerator automático | ✅ Fase 1 |
| API v1 | ✅ Fase 1 |
| news.php + embed | ✅ Fase 1 |
| CSS premium público | ✅ Fase 1 |
| MySQL migration | 📋 Fase 2 |
| Scorer REST completo | 📋 Fase 2 |
