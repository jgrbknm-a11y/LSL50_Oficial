# LSL50 Continuation Point

## Latest Start Point — Post-Refactor Checkpoint

Checkpoint updated on `2026-07-10 21:15:00 EDT`.

**System status: 100% listo para uso operativo.** Refactor estructural completo, cuaderno modularizado, transmisión OBS integrada, auth admin, y suite de tests rebuild aprobada.

### Quick links

| Servicio | URL |
|----------|-----|
| Admin | http://127.0.0.1:8080/admin/login.php |
| Cuaderno (tablet) | http://127.0.0.1:8090/scorer/ |
| OBS Control Center | http://127.0.0.1:5050/control.html |
| API estado en vivo | http://127.0.0.1:8080/api/live-game-state.php |
| Homepage pública | http://127.0.0.1:8080/ |

Credenciales admin: project-root `.env` (hash bcrypt en SQLite `users`). PIN anotador: Admin → Anotador (`/admin/scorer.php`).

### Servidores locales

```bash
# Website + Admin (:8080)
cd /Users/joseramirez/Documents/LSL50_Official_Project
php -S 0.0.0.0:8080 -t LSL50_Website_System LSL50_Website_System/router.php

# Cuaderno tablet (:8090)
php -S 0.0.0.0:8090 -t LSL50_Website_System LSL50_Website_System/scorer/router.php

# OBS Control Center (:5050)
cd LSL50_Live_Control_Center && python3 server.py
```

### Tests rebuild — aprobados

Comando oficial:

```bash
php LSL50_Website_System/tools/test_rebuild_rules.php
```

Cubre: **DP** (6-4-3, 5-4-3), **WP/PB**, **forfeit** (GameClosure + standings), **GameFlow** (cambio de entrada tras 3 outs). Última ejecución: **23 passed, 0 failed**.

### Refactor `admin/games.php` — completado

- Eliminadas funciones duplicadas locales: `recalc_stats()`, `recalc_team_stats()`, `stat_int()`.
- `save_box` delega a `lsl_save_game_box()` en `config.php` (fuente canónica junto con `lsl_recalc_player_stats()` y `lsl_recalc_team_stats()`).
- `php -l LSL50_Website_System/admin/games.php` — OK.

### Fases completadas (1–8)

1. Git + `.gitignore` + `_archive/` Laravel legacy
2. Cuaderno modular: `scorer/assets/`, `src/Repository/`, `src/Domain/Rules/`, `scorer/views/`, `AppRouter.php` + Controllers
3. Auth admin: `AdminAuth.php`, `login.php`, sesión separada del scorer
4. E2E validado: juego **#7** (Cerveceros @ Sharks, 2–1, final por innings)
5. OBS integrado: `live-game-state.php` + botones **TRAER CUADERNO** / **AUTO CUADERNO** en Control Center
6. Commit raíz: `1f59e2a` — *Refactorizacion estructural completa de LSL50*

### Juegos de referencia

- **Game 5** — Caribeños vs Cerveceros (próximo juego abierto sugerido)
- **Game 7** — Cerveceros @ Sharks (E2E completo, 2–1 final)
- **Game 3** — Bucaneros vs Hispanos (cerrado, PDF: `output/pdf/lsl50_scorebook_game_3.pdf`)

### Próximo paso sugerido

Operar jornada en vivo: abrir game 5 en cuaderno, anotar, cerrar oficialmente, confirmar standings y sync OBS.

---

## Previous Start Point (2026-07-09)

## Tomorrow Start Point

Start tomorrow from the Scorer App lineup and play-by-play flow:

- Open `http://127.0.0.1:8090/scorer/?game_id=3&view=plays#plays` to continue testing the play-by-play scorer with the latest visual and double-play fixes.
- Scorer PIN remains in DB/settings (not documented in plaintext).
- Last verified screen: game `3`, Bucaneros vs Hispanos, play-by-play section, local preview on port `8090`.
- The scorer app now has separate views:
  - `Cuaderno`: `http://127.0.0.1:8090/scorer/?game_id=3&view=plays#plays`
  - `Lineup`: `http://127.0.0.1:8090/scorer/?game_id=3&view=lineups#scorerTabs`
  - `Estadísticas`: `http://127.0.0.1:8090/scorer/?game_id=3&view=stats#scorerTabs`
- The view switcher is now global above the game workspace and sticky near the top, so `Cuaderno`, `Lineup`, and `Estadísticas` appear even while viewing stats.
- Last verification before stopping:
  - `php -l LSL50_Website_System/scorer/index.php` passed with no syntax errors.
  - Browser verification passed for the three scorer views.
  - `Cuaderno`, `Lineup`, and `Estadísticas` each show the global view switcher.
  - Only the selected view is visible at a time.
  - Browser verification passed after the double-play refinement: 57 visible play buttons are available.
  - `DP 6-4-3` preview loads correctly as batter out, 2 outs on the play, and runner logic based on the current base/out situation.
  - `DP 5-2-3` preview loads correctly as runner from 3B out at home and batter out at 1B.
  - The preview now considers existing outs before the play, so a double play that completes the third out does not automatically credit a run from 3B.
- Latest important scoring-rule update:
  - `6-4-3`, `4-6-3`, and `5-4-3` mean the runner from 1B is out at second and the batter is out at first.
  - `5-2-3`, `1-2-3`, and `3-2-3` mean the runner from 3B is out at home and the batter is out at first.
  - Force plays `6-4`, `4-6`, `5-4` put the runner from 1B out and leave the batter on 1B as `FC`.
  - Force plays `5-2`, `1-2`, `3-2` put the runner from 3B out at home and leave the batter on 1B as `FC`.
  - If a double play closes the inning, other runner advances are not auto-scored.
- Latest visual/ownership update:
  - The official LSL50 logo now appears below the live diamond as an institutional software mark.
  - The cuaderno displays: `Software oficial - Derechos reservados LSL50`.
  - `php -l LSL50_Website_System/scorer/index.php` passed after adding the official mark.
- Important rules to implement next:
  - Regular season games: official limit is `1h 45m` or `7 innings`, whichever comes first.
  - Semifinals and final: may continue to extra innings when needed.
  - Rain suspension: if suspended by rain, a game is legal after `5 complete innings`.
  - Add game status/control for rain suspension, legal game, suspended game, resumed game, final by time, final by 7 innings, and final by extra innings.
  - Add an official scorebook consultation/export view so the cuaderno can be reviewed if a manager or league official requests verification.
- Visual redesign phase 1 was applied to the scorer notebook:
  - `Cuaderno de anotación` now uses a live-console visual style.
  - top of the cuaderno now has a professional scoreboard layout with visitor score, home score, inning, batting team, and visual outs.
  - situation details were consolidated into a cleaner game strip for batting team, current batter, and bases.
  - live diamond remains in the main scorer flow and now sits under the scoreboard/game strip.
  - current batter and result buttons were grouped into a two-column focus area on desktop.
  - play buttons received stronger visual hierarchy and spacing.
  - bitacora received a darker header and cleaner table styling.
  - `php -l LSL50_Website_System/scorer/index.php` passed after the redesign.
- Note: the old local PHP process on port `8080` did not refresh the new visual file during this pass. If the browser still shows the old layout, restart the local website server before visual QA.
- Visual redesign phase 2 polish was applied:
  - live diamond is larger on desktop and responsive on tablet/mobile.
  - base runner chips now truncate long names cleanly.
  - secondary play groups are more compact so common actions remain visually dominant.
  - error and production groups received warmer visual emphasis.
  - confirmation preview is more prominent with a stronger blue accent.
  - final save bar is darker and the `Guardar jugada` button is orange for clearer confirmation.
  - side utility panels and bitacora actions received cleaner shadows/spacing.
  - `php -l LSL50_Website_System/scorer/index.php` passed after phase 2.
- The main `Cuaderno` view now hides lineup and numeric stats so the anotador only sees the play workflow, possible play buttons, live diamond, quick undo/courtesy runner tools, and play log.
- The live diamond was moved out of the side panel and into the main scorekeeping flow, directly below the situation bar.
- Game `4`, Cubs vs Sharks, remains the already-clean play-by-play test reference.
- The scorer notebook screen was redesigned to be more professional and tablet-friendly:
  - the play-by-play cuaderno now appears before the manual stats review table
  - the main scoring workflow is separated into numbered steps
  - step 1: current batter, inning, half, and batting team
  - step 2: quick result buttons plus full result select
  - step 3: runner advancement by occupied base
  - step 4: final confirmation for batter destination, outs, out detail, RBI, runs, and notes
  - the current batter now appears as a clear locked card, not as a long visible player list
  - quick buttons include `Out`, `Hit`, `Doble`, `Triple`, `HR`, `BB`, `Golpeado`, `Error`, `K`, `SF`, and `WP`
  - the live diamond sits inside the main cuaderno flow, directly below the situation bar, so the scorer can confirm bases before saving
  - corredor emergente was moved into a secondary expandable panel
  - the save action is grouped in a final confirmation bar
- Additional digital-scorebook improvements were added after reviewing common scorekeeping patterns:
  - the play entry now has a top situation bar with score, batting team, next batter, and bases
  - the confirmation step now includes an automatic play preview before saving
  - the side panel now focuses on the latest saved play, quick undo, and corredor emergente
  - `Deshacer última jugada` remains available for quick correction without searching the bitacora
  - the flow follows a digital scorebook pattern: quick touch result, runner movement, preview, save
  - the scoring model keeps traditional scorebook detail: before/during/after play, outs, runner destinations, and notation codes
- Removed the scorecard-style `Hoja de anotación` panel because the visual direction was not approved.
- Expanded the scorer play buttons into a compact professional button panel:
  - buttons were reduced in size to fit more play outcomes
  - current total visible play buttons: 57
  - groups now include: `Hits`, `Bases y turnos especiales`, `Ponches y elevados`, `Líneas y popups`, `Roletazos`, `Forzados y doble play`, `Errores`, `Sacrificios y corredor`, `Producción`
  - added hit buttons: `1B`, `2B`, `3B`, `HR`
  - added base/special buttons: `BB`, `IBB`, `HBP`, `INT`, `PB`, `WP`
  - added strikeout/fly buttons: `K`, `Kc`, `F7`, `F8`, `F9`, `SF`
  - added line/pop buttons: `L4`, `L5`, `L6`, `P2`, `P3`, `P6`
  - added groundout buttons: `1-3`, `3U`, `4-3`, `5-3`, `6-3`, `FC`
  - added force/double-play buttons: `6-4`, `4-6`, `5-4`, `5-2`, `1-2`, `3-2`, `DP 6-4-3`, `DP 4-6-3`, `DP 5-4-3`, `DP 5-2-3`, `DP 1-2-3`, `DP 3-2-3`
  - added error buttons: `E1` through `E9`
  - added runner/sacrifice buttons: `SH`, `SF`, `SB`, `CS`, `PK`, `OBR`
  - added production buttons: `RBI +1`, `Carrera +1`
  - buttons with notes now auto-fill the notes field, such as `IBB`, `E6`, `CS`, `PK`
- The scorer now supports official scorebook-style out detail:
  - new `game_play_events.out_detail` field
  - play form field: `Detalle del out`
  - suggested codes include `K`, `F7`, `F8`, `F9`, `L4`, `L5`, `L6`, `P2`, `G4-3`, `G5-3`, `G6-3`, `6-4`, `4-6`, `6-4-3`, `4-6-3`, `5-4-3`, `1-6-3`, `SF`, `SH`, `FC`
  - scorer can also type a custom scorebook code
  - selecting `Ponche` auto-fills `K`
  - selecting `Sacrifice fly` auto-fills `SF`
  - selecting `Sacrifice bunt` auto-fills `SH`
  - non-out results clear/ignore the out detail on save
  - the bitacora now displays out codes beside the result, for example `Out (G6-3)` or `Ponche (K)`
- New lineup control has been added to the scorer notebook.
- The scorer now has a `Lineup oficial` section per team.
- Each team lineup supports up to 15 batting slots.
- For each batting slot the scorer can select:
  - active roster player
  - defensive/field position
- Lineup validation update:
  - official position list is now `P`, `C`, `1B`, `2B`, `3B`, `SF`, `LF`, `CF`, `CR`, `RF`, `DH`, `OTRO`
  - required field positions are `P`, `C`, `1B`, `2B`, `3B`, `SF`, `LF`, `CF`, `CR`, `RF`
  - the same player cannot be selected twice in one lineup
  - the same position cannot be assigned to two different players in one lineup, except `DH`
  - `DH` is the only position/turn role that can repeat
  - if a required field position is missing, the scorer shows an alert and blocks saving
  - validation is enforced both in the browser and on the server
- Lineup data is stored per game/team/order in the new `game_lineups` table.
- The main scoring notebook table now follows the saved batting order.
- Players in the lineup show their batting turn and defensive position.
- Players not included in the lineup remain available at the bottom of their team as `Fuera del lineup`.
- Substitution workflow is practical for the next pass: replace the player in the same batting-order slot and update the field position.
- The scorer now calculates:
  - current inning
  - top/bottom
  - batting team
  - current outs
  - next batter by lineup order
- The `Control de jugadas y corredores` section now has a visible turn alert:
  - shows current batting team
  - shows inning and top/bottom
  - shows current outs
  - shows next batter and lineup turn
  - if the last saved play made the third out, it changes to `Cambio de entrada confirmado`
- When a lineup exists, the scorer blocks a play if:
  - wrong team is batting
  - wrong inning/half is submitted
  - wrong batter is selected for that lineup turn
- When a lineup exists, the batter selector is visually locked to the current lineup turn.
- The scorer cannot manually select batters above or below the current turn.
- After saving the play, the next batter is activated automatically whether the batter was out or reached base.
- The scoring notebook now shows PA automatically for each player.
- PA follows baseball/softball scoring logic: `AB + BB + HBP + SH + SF`.
- Semifinal/final eligibility requires at least 3 legal games; each legal game requires at least 1 PA.
- The legal-game calculation now uses PA, not AB alone: `AB + BB + HBP + SH + SF > 0`.
- The incorrect test play was removed; the bitacora for game `4` is clean.
- The runner-control form now blocks mixed-team plays.
- Corredor emergente has been added:
  - visible as an expandable secondary tool beside the cuaderno
  - only activates when there is a runner on base
  - replaces the runner on the selected base without altering the batting order
  - saves as a special play-by-play event and does not count as a plate appearance
- Batter and runner lists filter automatically by the batting team.
- Result-to-batter destination now auto-fills correctly:
  - `1B`, `BB`, `HBP`, `E`, `FC` -> batter to `1B`
  - `2B` -> batter to `2B`
  - `3B` -> batter to `3B`
  - `HR` -> batter scores
  - `OUT`, `SF`, `SH`, `SB`, `WP`, `PB` -> batter out/no base
- The batter destination now has a visible automatic note in the scorer:
  - selecting `Hit sencillo` immediately marks the batter on 1B
  - the live diamond shades 1B yellow
  - the scorer can still change the destination to 2B, 3B, home, or out if the play requires it
- Home run handling was corrected:
  - selecting `Jonrón` sends the batter to home
  - every occupied runner destination is set to home
  - suggested runs and RBI become batter plus occupied runners
  - the live diamond clears all bases
  - the server also protects this rule if a runner destination arrives blank or as `Se quedó`
- Double-with-runner-on-first handling was corrected:
  - if result is `Doble` and there is a runner on 1B, the runner is automatically suggested to 3B
  - the scorer sees an alert to confirm whether the runner stayed at 3B, scored at Home, or was Out
  - the server protects this rule if the runner destination arrives blank, `Se quedó`, 1B, or 2B
  - existing test play was corrected after user rectification: Kelvin Joga double now scores Alex Almeida from 1B, with Kelvin staying on 2B
- WP/PB handling was corrected for the scorer:
  - `Wild pitch` and `Passed ball` are runner-advance events only
  - they do not put the batter on base
  - they do not consume the current batter's turn or count as a PA
  - runner advances are limited to 2B or 3B; no Home advance on WP/PB under the current league rule
  - saved test WP now shows Kelvin Joga advancing from 2B to 3B, with Jose Reyes continuing at bat
- HBP remains available as `Golpeado` and correctly puts the batter on 1B.
- The scorer play log now rebuilds the game box score automatically:
  - saved play events update the notebook stats table
  - deleting a play rebuilds the game stats again
  - BB, HBP, hits, doubles, triples, HR, R and RBI now appear in the cuaderno from the bitacora
  - verified: Jose Reyes shows `BB=1`, `PA=1`; Yordin Rodriguez shows `HBP=1`, `PA=1`
- Browser verification passed:
  - game `3` play screen now shows `Cuaderno de anotación` as a structured workbench
  - the cuaderno appears before the manual stats review table
  - the current batter card shows `Kelvin Joga | Turno #4`
  - the hidden batter selector remains locked by lineup when a current batter exists
  - quick result button test passed: selecting `Doble` set result to `2B`, batter destination to `2B`, and highlighted the `Doble` button
  - reset verification passed: result returned to `OUT`, batter destination returned to `OUT`, and `Out` button highlighted
  - situation bar verification passed: score, batting team, next batter, and bases display on the scorer screen
  - play preview verification passed: preview text updates with batter, result, destination, outs, runs, RBI, and runners
  - last-play panel verification passed: `Deshacer última jugada` appears when a play exists
  - scorecard removal verification passed: `.scorecard-panel` is gone and `Hoja de anotación` no longer appears
  - grouped compact button verification passed: 51 play buttons render
  - `E6` button verification passed: result `E`, notes `E6`, and only `E6` active
  - `CS` button verification passed: result `OUT`, detail `CS`, notes include `Atrapado robando`, and only `CS` active
  - screen was reloaded after testing; no test play was saved
  - `Lineup oficial` appears on `/scorer/?game_id=4&view=lineups#lineups`
  - two lineup forms appear, one for each team
  - lineup alert boxes exist
  - official positions appear in the lineup position dropdown
  - for game `3`, the scoring table shows Hispanos in lineup order 1-12 before players outside the lineup
  - for game `3`, the turn alert shows `Hispanos - Alta del inning 1`, next batter `Osmanis Verdecia`, turn `#1`, outs `0`
  - for game `3`, the batter field is disabled/locked on `#12 Osmanis Verdecia`, with hidden value submitted correctly
  - the notebook table includes a PA column
  - PA recalculates live when AB, BB, HBP, SH, or SF changes
  - corredor emergente block appears and is disabled when bases are empty
  - `Corredor emergente` is not available as a normal batting result
  - game state tiles show Inning 1, Alta, Sharks al bate, and "Sin lineup" until a lineup is saved
  - bitacora rows for game `4`: `0`
  - `Triple` sets batter destination to `3B`
  - switching teams leaves no opposing-team batter or runner enabled
- PHP syntax verification passed for `LSL50_Website_System/config.php` and `LSL50_Website_System/scorer/index.php`.
- Database migration verification passed: `game_lineups` table exists and `game_play_events.out_detail` exists.

Recommended first task tomorrow:

- Test a realistic full inning in `Control de jugadas y corredores`, including scorebook out details such as `K`, `F8`, `G6-3`, and `6-4-3`.
- Confirm that after 3 outs the system moves to the next half inning and presents the correct next batter from the other team's lineup.

## Start Here

Continue from the tested scorer-to-public-homepage flow.

Primary local admin link:

- http://127.0.0.1:8080/admin/index.php

Public league homepage:

- http://127.0.0.1:8080/

AI publisher control:

- http://127.0.0.1:8080/admin/ai-publisher.php

Scorer app link:

- http://127.0.0.1:8080/scorer/

Scorer PIN:

- Managed in Admin → Anotador (`/admin/scorer.php`). Optional override: `LSL50_SCORER_PIN` in project-root `.env`.

Scorer PIN management:

- http://127.0.0.1:8080/admin/scorer.php

## Last Completed Milestone

- Created the first official jornada from the schedule.
- Tested the Scorer App with `Cubs vs Sharks`.
- Saved official box score for `Cubs 2 - Sharks 0`.
- Confirmed standings updated: Cubs `1-0`, Sharks `0-1`.
- Generated and published the first Publicador IA note:
  - `Cubs supera a Sharks en la jornada LSL50`
- Redesigned the public homepage to match the current live-site style more closely:
  - dark sports theme
  - large latest-result scoreboard
  - compact game calendar cards
  - editorial news cards
  - standings and leaders
- Latest-result homepage logic now ignores unscored `0-0` games and uses only scored/stat-backed games.
- Replaced the current schedule with the professional reference schedule supplied by the user.
- Generated and verified the official one-page professional PDF format.
- The schedule includes 30 regular season games, 2 league off dates, 6 semifinal placeholders, and 1 final.
- Each team has exactly 10 regular season games.
- The generated dates run from 2026-06-14 through 2026-09-27.
- Official game times are `09:30 AM`, `11:30 AM`, and `01:30 PM`.
- The final is a single game.
- Generated and visually checked the schedule PDF:
  - `LSL50_Website_System/output/pdf/lsl50_schedule_season_1.pdf`
- Added the public homepage with automatic previous jornada results, featured news, standings, upcoming games, and leaders.
- Homepage game logos are standardized to the same size and same-side alignment.
- Added the `Publicador IA` admin module to prepare automated game notes, YouTube highlight clips, and review/auto-publish settings.
- Publicador IA is configured with `https://www.youtube.com/@LegendsSoftballLeague50`.
- AI game notes table is ready; published notes appear on the public homepage.
- Current publishing mode is review before publish. Without external API keys, note generation uses official box score stats and manual YouTube video URL entry.
- Backup of the previous generated schedule:
  - `LSL50_Website_System/data/schedule_entries_backup_before_reference_schedule.json`

## Current Server State

- Website/admin server is running locally on port `8080`.
- The tablet-facing server on port `8090` is prepared but was blocked by this environment from listening on `0.0.0.0`.
- Detected local network IP during setup: `192.168.1.111`.

## To Use From A Tablet

From the Mac/host terminal, launch the tablet-only scorer server with:

```bash
cd /Users/joseramirez/Documents/LSL50_Official_Project
php -S 0.0.0.0:8090 -t LSL50_Website_System LSL50_Website_System/scorer/router.php
```

Website + Admin (blocks `/data` and `.env`):

```bash
cd /Users/joseramirez/Documents/LSL50_Official_Project
php -S 0.0.0.0:8080 -t LSL50_Website_System LSL50_Website_System/router.php
```

Then open this from a tablet on the same Wi-Fi:

- http://192.168.1.111:8090/scorer/

## Suggested Next Step

Continue with one of these concrete next steps:

- Validate the new `Consulta` view and official scorebook PDF after closing a game.
- Finish testing the remaining first-jornada games: `Bucaneros vs Hispanos` and `Caribeños vs Cerveceros`.
- Review the public homepage visually after more scores/news are added.
- Add OpenAI and YouTube API keys when ready for deeper automation.

Current schedule links:

- Admin schedule: http://127.0.0.1:8080/admin/schedule.php
- Schedule PDF: http://127.0.0.1:8080/output/pdf/lsl50_schedule_season_1.pdf

Current teams loaded:

- Bucaneros
- Caribeños
- Cerveceros
- Cubs
- Hispanos
- Sharks

Current roster import status:

- Bucaneros: 18 players imported
- Caribeños: 16 players imported
- Cerveceros: 20 players imported
- Cubs: 19 players imported
- Hispanos: 18 players imported
- Sharks: 16 players imported
- Total imported players: 107

Roster import notes:

- Bucaneros source image has 12 players without visible birth date.
- Caribeños source had one duplicate Jose Rojas row; duplicate was skipped.
- Hispanos source has jersey number `23` assigned to two players.
- 48 imported players are under 50 based on provided birth dates; they remain in the roster and are marked red by the age validation.

Current test game state:

- Game `3`: Bucaneros vs Hispanos, `2026-06-14`, not scored yet.
- Game `4`: Cubs vs Sharks, `2026-06-14`, final `2-0`, stats saved.
- Game `5`: Caribeños vs Cerveceros, `2026-06-14`, not scored yet.
- AI note `1`: published for game `4`.

## Official Stats Rules Confirmed

- MLB-style hit rule is now applied in the scoring/stat pipeline.
- `H` is treated as the official total hit count.
- `2B`, `3B`, and `HR` are included inside official hits; if an extra-base hit is entered without enough `H`, the system uses the larger value between `H` and `2B+3B+HR`.
- Total bases follow MLB logic:
  - singles = `H - 2B - 3B - HR`
  - TB = `1B + 2*2B + 3*3B + 4*HR`
- AVG, OBP, SLG, and OPS leaders require MLB-style qualification: minimum `3.1` plate appearances per team game, rounded up.
- Counting departments such as H, RBI, R, HR, 2B, 3B, BB, SO, and SB do not require qualification.
- Admin leaders page was redesigned using the prior live-site reference:
  - search by player/team
  - full batting table with horizontal scroll on mobile
  - visible 3.1 PA rule
  - PA minimum by team
  - ranking cards by offensive department
  - pitcher wins section
- Publicador IA API step started:
  - OpenAI API key can be saved and tested from `/admin/ai-publisher.php`.
  - OpenAI note generation now uses the Responses API when a key exists, with local stats-based generation as fallback.
  - OpenAI model is editable from the admin page; default is `gpt-4.1-mini`.
  - YouTube API key can be saved from `/admin/ai-publisher.php`.
  - YouTube channel sync fetches recent videos from `@LegendsSoftballLeague50` and stores them for assignment to game notes.
  - Game note generation can now select a synced YouTube video or accept a manual URL.
- Scorebook research update:
  - Reviewed multiple YouTube scorekeeping references for baseball/softball scorebooks.
  - Added first official-scorebook expansion to the system: `HBP`, `SH`, `SF`, and `E`.
  - Tablet Scorer App and admin game notebook now show and save those fields.
  - Player stats now store and recalculate those fields.
  - OBP now uses MLB-style denominator `AB + BB + HBP + SF`.
  - PA qualification now uses `AB + BB + HBP + SH + SF`.
  - Public homepage boxscore now uses saved errors instead of always showing `0`.
- Runner advancement control added:
  - New `game_play_events` table stores play-by-play events.
  - Tablet Scorer App now includes `Control de jugadas y corredores`.
  - Each play can record inning, top/bottom, batting team, batter, result, batter destination, runners on 1B/2B/3B and their destinations, outs, RBI, runs scored, and notes.
  - This solves the problem of identifying whether a runner advanced on an out, error, walk, hit, extra-base hit, wild pitch/passed ball, or later scored on another batter's double.
  - Publicador IA now receives play-by-play context when generating notes.
- Live scorer diamond added:
  - Tablet Scorer App now shows a visual diamond in `Control de jugadas y corredores`.
  - Bases are shaded yellow when occupied.
  - The diamond reads saved play-by-play state and updates live as the scorer selects batter/runner destinations before saving.
  - Verified by selecting batter destination `1B`; first base highlighted yellow without saving data.
- Play-by-play log correction:
  - Removed the invalid test play from game `4` because it mixed Cubs runners inside a Sharks batting turn and marked a triple with the batter ending at `1B`.
  - Tablet Scorer App now validates that the batter and all selected runners belong to the batting team before saving a play.
  - The play form now filters batter and runner options by the selected batting team.
  - Result selection now auto-fills the batter destination: singles/walks/HBP/errors/FC to `1B`, doubles to `2B`, triples to `3B`, home runs to home, and outs/sacrifices/advance-only plays to out.
  - Browser verification passed on `/scorer/?game_id=4#plays`: the bitacora is clean, team filters prevent opposing players, and `Triple` sets the batter destination to `3B`.
- Forced runner advancement correction completed on `2026-07-08 21:12:37 EDT`:
  - BB and HBP now force runners automatically: runner on `1B` goes to `2B`, runner on `2B` goes to `3B` only when the force chain applies, and bases-loaded BB/HBP scores the runner from `3B`.
  - Existing game `3` HBP test was corrected so Yordin Rodriguez is on `1B`, Jose Reyes is on `2B`, and Kelvin Joga remains on `3B`.
  - Browser verification passed on `/scorer/?game_id=3&view=plays#plays`: the live diamond shows the current base state in the main cuaderno flow.
- Single-hit runner advancement correction completed on `2026-07-08 21:25:50 EDT`:
  - On `1B` result, the batter is placed on `1B` automatically.
  - Runner on `3B` is suggested to score, runner on `2B` is suggested to advance at least to `3B`, and runner on `1B` is suggested to advance to `2B`.
  - The scorer can still correct the runner destination to `Home`, `Out`, or another valid result before saving.
  - Browser verification passed on `/scorer/?game_id=3#plays` without saving a new play: selecting `Sencillo` showed Nestor Matheus on `1B`, Eduard Peralta on `3B`, Jose Reyes scoring, and suggested `1` run/RBI.
- Triple runner advancement correction completed on `2026-07-08 21:31:42 EDT`:
  - On `3B` result, the batter is placed on `3B` automatically.
  - Any runners already on base are suggested to score, with runs/RBI filled from the number of scoring runners.
  - Existing game `3` sequence was corrected after BB then triple: Brian Field, Nestor Matheus, and Eduard Peralta scored; Avelo Banez remained on `3B` with `3` RBI.
  - Browser verification passed on `/scorer/?game_id=3#plays`: score shows `Bucaneros 0 - Hispanos 8`, `1B`/`2B` are empty, and `3B` shows Avelo Banez.
- Three-out inning transition correction completed on `2026-07-08 21:37:23 EDT`:
  - Saved game `3` outs for Osmanis Verdecia, Orlando Hernandez, and Alex Almeida were corrected to `1` out each.
  - Future `OUT`, `SF`, and `SH` plays now default to `1` out when the scorer has not entered an out count.
  - Browser verification passed on `/scorer/?game_id=3#plays`: current state is `1` bottom, Bucaneros batting, next batter Isaac Carciente, bases empty, outs current `0`.
- Sacrifice fly advancement correction completed on `2026-07-08 21:44:44 EDT`:
  - On `SF` result, the batter is out, the play defaults to `1` out, and the runner on `3B` is suggested to score with `1` RBI/run.
  - Existing game `3` Michael Gonzalez SF was corrected: Isaac Carciente scored, Douglas Gonzalez remained on `2B`, and Michael Gonzalez received `1` RBI.
  - Browser verification passed on `/scorer/?game_id=3#plays`: score shows `Bucaneros 1 - Hispanos 8`, `2B` Douglas Gonzalez, and `3B` empty.
- Error with bases-loaded force correction completed on `2026-07-08 21:57:56 EDT`:
  - On `E` result, the batter is placed on `1B` and forced runners advance.
  - If bases are loaded, the runner on `3B` is forced to score, but RBI remains `0`.
  - Existing game `3` errors were corrected: Angel Martinez and Hugo Ortega scored on bases-loaded errors without RBI; BB with bases loaded still keeps `1` RBI.
  - Browser verification passed on `/scorer/?game_id=3#plays`: score shows `Bucaneros 7 - Hispanos 8`, bases show Jose Gonzalez on `1B`, Michael Gonzalez on `2B`, and Douglas Gonzalez on `3B`.
- Strikeout result added on `2026-07-08 22:06:52 EDT`:
  - Added `SO` / `Ponche` to the play result selector.
  - `SO` places the batter as out, defaults to `1` out, counts as `AB`, and increments batter `SO`.
  - Browser verification passed on `/scorer/?game_id=3#plays` without saving a play: selecting `Ponche` showed batter destination `OUT` and outs `1`.

## Checkpoint To Resume

Checkpoint created on `2026-07-08 22:09:15 EDT`.

Resume at:

- Tablet scorer: `/scorer/?game_id=3#plays`
- Scorer PIN: see Admin → Anotador (not stored in this doc)
- Test game: game `3`, Bucaneros vs Hispanos.
- Current stored score: Bucaneros `16` - Hispanos `8`.
- Latest saved play: event `45`, bottom 1st, Angel Martinez recorded as `OUT`, `1` out, Isaac Carciente remained on `3B`.
- Recent saved sequence:
  - event `38`: Lionel Martinez HR, Jose Gonzalez/Michael Gonzalez/Douglas Gonzalez scored, 4 RBI.
  - event `39`: Carlos Paez triple.
  - event `40`: Dennis Quillarque BB, Carlos stayed on `3B`.
  - event `41`: Jose Sojo HBP, Dennis to `2B`, Carlos stayed on `3B`.
  - event `42`: Roger Ramirez BB, Carlos scored, Dennis to `3B`, Jose Sojo to `2B`.
  - event `43`: Ender Davila HBP, Dennis scored, Roger to `2B`, Jose Sojo to `3B`.
  - event `44`: Isaac Carciente triple, Ender/Roger/Jose Sojo scored, Isaac to `3B`.
  - event `45`: Angel Martinez out, Isaac stayed on `3B`.

Rules now implemented and verified in the scoring notebook:

- `1B`: batter to `1B`; runner on `3B` scores; runner on `2B` advances at least to `3B`; runner on `1B` advances to `2B`.
- `2B`: batter to `2B`; runner on `1B` must reach at least `3B`.
- `3B`: batter to `3B`; all existing runners are suggested to score; RBI/runs auto-filled from scoring runners.
- `HR`: batter and all runners score; bases clear.
- `BB`/`HBP`: forced runners advance; bases-loaded runner from `3B` scores with RBI.
- `E`: batter to `1B`; forced runners advance; bases-loaded runner from `3B` scores with no RBI.
- `SF`: batter out; runner on `3B` scores; 1 RBI/run; 1 out.
- `SO`: batter out; 1 AB, 1 SO, 1 out.
- `OUT`/`SF`/`SH`/`SO`: default to 1 out if scorer leaves outs blank.
- `WP`/`PB`: runner-advance-only; does not embasar batter; runner may advance only to `2B`/`3B` by league rule already discussed.

Recommended next step:

- Continue testing the scorer from event `45`, especially inning transition after Bucaneros reaches 3 outs and whether runners left on base clear correctly on inning change.

## Checkpoint To Resume

Checkpoint created on `2026-07-09 22:38:55 EDT`.

Resume at:

- Tablet scorer: `/scorer/?view=plays#scorerTabs`.
- Current local test server used today: `http://127.0.0.1:8090/scorer/?view=plays#scorerTabs`.
- Scorer PIN: stored in `app_settings` / optional `LSL50_SCORER_PIN` (manage at `/admin/scorer.php`).
- Current active/open game after cleanup: game `5`, `Caribeños vs Cerveceros`.
- Game `3`, `Bucaneros vs Hispanos`, is now officially closed as `Final por 7 innings`.
- Game `3` closure note: `Cerrado por innings completos`.
- Official scorebook PDF regenerated: `LSL50_Website_System/output/pdf/lsl50_scorebook_game_3.pdf`.

Latest completed work:

- Date search was removed from the live `Cuaderno` and `Lineup` workflow.
- Date search now appears only in `Consulta`, using format `MM/DD/AA`, for game verification/audit.
- Game cards still show date and official game time beside the teams.
- Official closure was corrected so an open game no longer defaults to `Pendiente / sin cerrar`.
- When closing a non-forfeit game, the system rebuilds the box score from the play log before saving the official result.
- The closure button now reads `Cerrar juego y pasar al próximo` for open games.
- After closure, the scorer redirects to the next open game so the next scorekeeping session starts clean.
- The scorer router now serves official scorebook PDFs from `/output/pdf/` when running the tablet scorer server.
- Verified by authenticated local checks:
  - `/scorer/?view=plays#scorerTabs` opens the next open game after closed games.
  - `Cuaderno` and `Lineup` do not show `Buscar por fecha`.
  - `Consulta` shows `Buscar por fecha` and lists games for `06/14/26`.
  - Game `3` banner shows `Juego cerrado`, `Final por 7 innings`, and `Motivo del cierre`.
  - Open-game closure screens show `Cerrar juego y pasar al próximo`.
  - PDF route for `lsl50_scorebook_game_3.pdf` returns correctly.

Recommended next step:

- Start tomorrow by testing a fresh short workflow on game `5`, `Caribeños vs Cerveceros`: load/check lineup, record a few controlled plays, review stats, then close the game and confirm the scorer moves cleanly to the next open game or stays in consultation if no more open games remain.

## Checkpoint To Resume

Checkpoint created on `2026-07-09 20:27:34 EDT`.

Resume at:

- Tablet scorer: `/scorer/?game_id=3&view=lineups#scorerTabs` for official validation controls.
- Scorer PIN: stored in `app_settings` / optional `LSL50_SCORER_PIN` (manage at `/admin/scorer.php`).
- Test game: game `3`, Bucaneros vs Hispanos.
- Verified local URL: `http://127.0.0.1:8090/scorer/?game_id=3&view=lineups#scorerTabs`.

New validation features completed:

- Added official game-result fields to `games`: status, result type, official note, forfeit winner/loser, legal-game flag, completed innings, start/end timestamps.
- Added `game_borrowed_players` to register temporary players used only for one game.
- The scorer lineup screen now has:
  - `Jugador prestado para este juego`, for adding a player from another team to avoid forfeit.
  - `Validación oficial`, for normal final, time limit, innings limit, extra innings, rain legal/not legal, and forfeit.
  - Forfeit defaults to a legal final result with official 7-0 style score, editable from the screen.
- Borrowed players can be selected in the lineup for the team using them, without changing the player's official roster team.
- Rebuilt play-by-play stats now count a borrowed player's game stats for the team using him in that specific game.
- Team standings now include official forfeits even when there are no individual player stats.
- Browser validation passed: the scorer lineups screen shows `Jugador prestado para este juego`, `Validación oficial`, `Forfeit`, and `Lineup oficial`.

Recommended next step:

- Test adding one borrowed player in a non-official test game, placing him in a lineup, then removing him before final use.
- Test saving a forfeit validation and confirm standings update as expected.

## Validation Completed

Validation completed on `2026-07-09`:

- `SS` is now available in the lineup position selector.
- Required defensive positions now include `SS` while keeping `SF`.
- Simulated borrowed-player flow passed without altering real data:
  - A Sharks player was temporarily simulated as borrowed by Bucaneros.
  - The player appeared in the game roster under Bucaneros.
  - The player's original team remained Sharks.
- Simulated forfeit flow passed without altering real data:
  - A 7-0 forfeit updated standings logic with one win and one loss.
  - Official runs for/against were included in team standings.
- Browser validation passed at `/scorer/?game_id=3&view=lineups&v=validate2#scorerTabs`:
  - `Jugador prestado para este juego`
  - `Validación oficial`
  - `Forfeit`
  - `SS`
  - `SF`

## Official Game Closure Validation

Validation completed on `2026-07-09`:

- Official closure rules were hardened in the Scorer App:
  - Normal final, time limit, innings limit, and extra innings automatically become legal final games.
  - Rain legal requires at least 5 completed innings.
  - Rain suspended before legal-game threshold is stored as suspended and does not count in standings.
  - Forfeit automatically becomes legal final and keeps winner/loser teams.
- The scorer validation screen now displays the official rule note:
  - Regular/semi: 1h 45m or 7 innings, whichever comes first.
  - Rain: legal game from 5 completed innings.
  - Final/extra innings may extend.
- Transaction simulations passed without altering real data:
  - `normal`
  - `time_limit`
  - `innings_limit`
  - `extra_innings`
  - `rain_legal`
  - `rain_suspended`
  - `forfeit`
- Browser validation passed at `/scorer/?game_id=3&view=lineups&v=closure1#scorerTabs`.
