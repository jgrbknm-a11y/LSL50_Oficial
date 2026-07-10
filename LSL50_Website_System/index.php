<?php
require __DIR__ . "/config.php";

$pdo = db();
$season = active_season($pdo);
$seasonId = (int)$season["id"];
$leagueLogoUrl = lsl_setting($pdo, "league_logo_url", "");

function fmt_record_value($wins, $losses, $ties): string {
  $record = (int)$wins . "-" . (int)$losses;
  if ((int)$ties > 0) $record .= "-" . (int)$ties;
  return $record;
}

function fmt_public_avg($value): string {
  return number_format((float)$value, 3);
}

function fmt_public_date(?string $date): string {
  if (!$date) return "";
  try {
    return strtoupper((new DateTimeImmutable($date))->format("M j, Y"));
  } catch (Throwable $e) {
    return (string)$date;
  }
}

function public_leader(PDO $pdo, string $expr, string $where, string $order, bool $qualifiedRate = false): ?array {
  $qualification = "";
  if ($qualifiedRate) {
    $qualification = " AND (ps.AB + ps.BB + ps.HBP + ps.SH + ps.SF) >= (
      SELECT CAST((COUNT(*) * 3.1) + 0.999999 AS INTEGER)
      FROM games g
      WHERE (g.home_team_id=t.id OR g.away_team_id=t.id)
        AND EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id)
    )";
  }
  $sql = "SELECT p.first_name || ' ' || p.last_name player_name, p.number, t.name team_name, $expr leader_value
    FROM player_stats ps
    JOIN players p ON p.id=ps.player_id
    LEFT JOIN teams t ON t.id=p.team_id
    WHERE $where $qualification
    ORDER BY $order, ps.AB DESC, p.last_name, p.first_name
    LIMIT 1";
  $row = $pdo->query($sql)->fetch();
  return $row ?: null;
}

$standings = $pdo->query("SELECT t.name, t.logo_url, COALESCE(ts.wins,0) wins, COALESCE(ts.losses,0) losses, COALESCE(ts.ties,0) ties,
    COALESCE(ts.runs_for,0) runs_for, COALESCE(ts.runs_against,0) runs_against,
    (COALESCE(ts.runs_for,0) - COALESCE(ts.runs_against,0)) run_diff
  FROM teams t
  LEFT JOIN team_stats ts ON ts.team_id=t.id
  ORDER BY COALESCE(ts.wins,0) DESC, COALESCE(ts.ties,0) DESC, run_diff DESC, COALESCE(ts.runs_for,0) DESC, t.name")->fetchAll();

$latestDateStmt = $pdo->query("SELECT MAX(g.game_date)
  FROM games g
  WHERE COALESCE(g.season_id, $seasonId) = $seasonId
    AND (EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id) OR g.final_home > 0 OR g.final_away > 0)");
$latestGameDate = $latestDateStmt->fetchColumn();
$recentResults = [];
if ($latestGameDate) {
  $stmt = $pdo->prepare("SELECT g.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
    FROM games g
    JOIN teams ht ON ht.id=g.home_team_id
    JOIN teams at ON at.id=g.away_team_id
    WHERE COALESCE(g.season_id, $seasonId) = $seasonId AND g.game_date=?
      AND (EXISTS (SELECT 1 FROM game_player_stats gps WHERE gps.game_id=g.id) OR g.final_home > 0 OR g.final_away > 0)
    ORDER BY g.id");
  $stmt->execute([$latestGameDate]);
  $recentResults = $stmt->fetchAll();
}

$gameTotals = [];
if ($recentResults) {
  $ids = array_map(fn($g) => (int)$g["id"], $recentResults);
  $placeholders = implode(",", array_fill(0, count($ids), "?"));
  $totalsStmt = $pdo->prepare("SELECT game_id, team_id, COALESCE(SUM(MAX(H, dbl + tpl + HR)),0) hits, COALESCE(SUM(E),0) errors
    FROM game_player_stats
    WHERE game_id IN ($placeholders)
    GROUP BY game_id, team_id");
  $totalsStmt->execute($ids);
  foreach ($totalsStmt->fetchAll() as $row) {
    $gameTotals[(int)$row["game_id"]][(int)$row["team_id"]] = ["hits" => (int)$row["hits"], "errors" => (int)$row["errors"]];
  }
}

$nextGames = $pdo->query("SELECT s.*, ht.name home_name, ht.logo_url home_logo, at.name away_name, at.logo_url away_logo
  FROM schedule_entries s
  LEFT JOIN teams ht ON ht.id=s.home_team_id
  LEFT JOIN teams at ON at.id=s.away_team_id
  WHERE s.season_id=$seasonId AND s.stage='Regular' AND s.game_date >= date('now')
  ORDER BY s.game_date, s.game_time, s.id
  LIMIT 3")->fetchAll();

$featuredNews = $pdo->query("SELECT * FROM media
  WHERE featured=1
  ORDER BY COALESCE(week_start, created_at) DESC, order_index ASC, id DESC
  LIMIT 3")->fetchAll();

$aiNews = $pdo->query("SELECT n.*, g.game_date, ht.name home_name, at.name away_name
  FROM ai_game_notes n
  JOIN games g ON g.id=n.game_id
  JOIN teams ht ON ht.id=g.home_team_id
  JOIN teams at ON at.id=g.away_team_id
  WHERE n.status='published' AND n.season_id=$seasonId
  ORDER BY COALESCE(n.published_at, n.updated_at) DESC, n.id DESC
  LIMIT 3")->fetchAll();

$leaderCards = [
  ["label" => "Promedio", "abbr" => "AVG", "row" => public_leader($pdo, "ps.AVG", "ps.AB > 0", "ps.AVG DESC", true), "format" => "avg"],
  ["label" => "Hits", "abbr" => "H", "row" => public_leader($pdo, "ps.H", "ps.H > 0", "ps.H DESC"), "format" => "int"],
  ["label" => "Jonrones", "abbr" => "HR", "row" => public_leader($pdo, "ps.HR", "ps.HR > 0", "ps.HR DESC"), "format" => "int"],
  ["label" => "Impulsadas", "abbr" => "RBI", "row" => public_leader($pdo, "ps.RBI", "ps.RBI > 0", "ps.RBI DESC"), "format" => "int"],
];

$pitcherLeader = $pdo->query("SELECT p.first_name || ' ' || p.last_name player_name, p.number, t.name team_name, COUNT(*) leader_value
  FROM games g
  JOIN players p ON p.id=g.winning_pitcher_id
  LEFT JOIN teams t ON t.id=p.team_id
  WHERE COALESCE(g.season_id, $seasonId) = $seasonId
    AND g.winning_pitcher_id IS NOT NULL
    AND g.final_home != g.final_away
  GROUP BY p.id, p.number, p.first_name, p.last_name, t.name
  ORDER BY leader_value DESC, p.last_name, p.first_name
  LIMIT 1")->fetch();
if ($pitcherLeader) {
  $leaderCards[] = ["label" => "Pitcher ganador", "abbr" => "G", "row" => $pitcherLeader, "format" => "int"];
}

function display_leader_value(?array $card): string {
  if (!$card || empty($card["row"])) return "-";
  $value = $card["row"]["leader_value"] ?? 0;
  return ($card["format"] ?? "int") === "avg" ? fmt_public_avg($value) : (string)(int)$value;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Legends Softball League 50+</title>
  <style>
    :root{--navy:#061b3b;--blue:#0f3d75;--gold:#d7a72f;--red:#b42318;--ink:#152033;--muted:#667085;--line:#d9e0ea;--bg:#f4f6f8;--card:#fff}
    *{box-sizing:border-box} body{margin:0;background:#07111c;color:#f8fafc;font-family:Arial,Helvetica,sans-serif} a{color:inherit}
    .top{background:#111f2d;color:white;border-bottom:1px solid #2a3b4d}.bar{max-width:1180px;margin:0 auto;padding:18px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .brand{display:flex;align-items:center;gap:12px;font-weight:900;letter-spacing:.03em}.brand img{width:66px;height:66px;object-fit:contain}.brand strong{font-size:22px}.brand span{font-size:12px;color:#91a1b5;display:block;margin-top:2px}
    .nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{text-decoration:none;color:#dce7f5;font-weight:900;border-radius:8px;padding:9px 11px;background:#172738}.nav a:hover{background:#22384d}
    .hero{background:#08131e;color:white}.hero-inner{max-width:1180px;margin:0 auto;padding:28px 18px 18px}.hero-copy{margin-bottom:18px}.eyebrow{color:#25d366;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.14em}.hero h1{font-size:46px;line-height:1.02;margin:8px 0 12px;max-width:820px}.hero p{font-size:17px;color:#a8b4c5;margin:0;max-width:760px}.season-pill{display:inline-block;background:#111f2d;border:1px solid #33465a;border-radius:999px;padding:8px 12px;font-weight:900;margin-top:16px;color:#dce7f5}
    main{max-width:1180px;margin:0 auto;padding:22px 18px 42px}.grid{display:grid;gap:18px}.cols-2{grid-template-columns:1.08fr .92fr}.cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .card{background:#122232;border:1px solid #314254;border-radius:16px;padding:18px;color:#f8fafc;box-shadow:0 14px 28px rgba(0,0,0,.18)}.section-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:26px 0 14px}.section-title{font-size:31px;line-height:1.05;font-weight:950;margin:0;color:#fff}.section-link{color:#1e90ff;text-decoration:none;font-weight:900}.small{font-size:14px;color:#91a1b5}
    .scorecard{overflow:hidden;padding:0}.scorebar{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#09131d;border-bottom:1px solid #314254;padding:16px 18px}.scorebar strong{color:#25d366;letter-spacing:.12em}.scorebar span{color:#a8b4c5;font-weight:900;letter-spacing:.08em}.scorebody{padding:22px 18px}.score-main{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px}.score-team{text-align:center}.score-team img{width:92px;height:92px;object-fit:contain}.score-team h3{font-size:23px;margin:8px 0 4px;text-transform:uppercase}.record{color:#91a1b5;font-weight:900}.bigscore{font-size:54px;font-weight:950;white-space:nowrap}.bigscore .winner{color:#ff6b00}.bigscore .dash{color:#627086;margin:0 8px}.diamond{height:105px;display:grid;place-items:center;color:#536173;font-size:54px}.final-label{text-align:center;color:#ff6b00;font-weight:950;letter-spacing:.12em;margin-bottom:4px}.line-score{margin-top:18px;width:100%;border-collapse:collapse}.line-score th,.line-score td{border-bottom:1px solid #415267;padding:12px 4px;text-align:right}.line-score th:first-child,.line-score td:first-child{text-align:left}.line-score th{color:#7f8ea2;font-size:12px;letter-spacing:.12em}.line-score td{font-size:20px;font-weight:900}.line-score .accent{color:#ff6b00}
    .game-list{display:grid;gap:12px}.game-row{display:grid;grid-template-columns:76px 1fr auto;gap:12px;align-items:center;background:#0b1621;border:1px solid #203246;border-radius:12px;padding:12px}.game-date{color:#91a1b5;text-transform:uppercase;font-weight:900}.game-date b{display:block;color:#fff;font-size:24px}.teams-line{display:grid;gap:8px}.mini-team{display:grid;grid-template-columns:38px 1fr;gap:8px;align-items:center;font-weight:900}.mini-team img{width:38px;height:38px;object-fit:contain}.game-time{font-size:24px;font-weight:950;text-align:right;color:#fff}
    .news-grid{display:grid;gap:18px}.news-card{background:#122232;border:1px solid #314254;border-radius:16px;overflow:hidden}.news-thumb{height:168px;background:#243449;display:grid;place-items:center;color:#6e7e92;font-size:38px;font-weight:900}.news-thumb img{width:100%;height:100%;object-fit:cover}.tag{display:inline-block;background:#ff6b00;color:white;border-radius:8px;padding:8px 13px;font-weight:950;letter-spacing:.08em;margin-bottom:12px}.news-content{padding:18px}.news-title{font-size:28px;line-height:1.08;font-weight:950;color:#fff;margin:0 0 12px}.news-summary{color:#a8b4c5;font-size:17px;line-height:1.45;margin:0 0 14px}.meta{color:#7f8ea2;font-weight:800}
    table{width:100%;border-collapse:collapse} th,td{border-bottom:1px solid #314254;padding:9px;text-align:left;vertical-align:middle} th{font-size:12px;color:#7f8ea2;text-transform:uppercase} td strong{color:#fff}.team{display:grid;grid-template-columns:36px minmax(0,1fr);align-items:center;gap:9px;min-width:0}.team img{width:36px;height:36px;object-fit:contain}.team-name{font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.leader{border:1px solid #314254;border-radius:12px;padding:13px;background:#0b1621}.leader b{display:block;font-size:12px;color:#91a1b5;text-transform:uppercase}.leader .value{font-size:30px;font-weight:950;color:#ff6b00;margin:4px 0}.leader .name{font-weight:900}.empty{color:#91a1b5;font-size:16px;border:1px dashed #314254;border-radius:14px;padding:24px;text-align:center;background:#0b1621}.links{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{background:#1e90ff;color:white;text-decoration:none;border-radius:8px;padding:10px 12px;font-weight:900}.btn.secondary{background:#172738;color:#dce7f5}
    @media(max-width:860px){.cols-2,.cols-3{grid-template-columns:1fr}.hero h1{font-size:34px}.section-title{font-size:32px}.score-main{grid-template-columns:1fr auto 1fr}.score-team img{width:74px;height:74px}.score-team h3{font-size:19px}.bigscore{font-size:44px}.game-row{grid-template-columns:64px 1fr}.game-time{grid-column:2;text-align:left;font-size:20px}.news-title{font-size:27px}.brand strong{font-size:18px}}
  </style>
</head>
<body>
  <header class="top">
    <div class="bar">
      <div class="brand">
        <?php if ($leagueLogoUrl): ?><img src="<?= h($leagueLogoUrl) ?>" alt="LSL50"><?php endif; ?>
        <div><strong>LEGENDS SOFTBALL LEAGUE 50+</strong><span>Broward - Florida</span></div>
      </div>
      <nav class="nav">
        <a href="/admin/schedule.php">Calendario</a>
        <a href="/admin/leaders.php">Líderes</a>
        <a href="/admin/games.php">Juegos</a>
      </nav>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <div class="hero-copy">
        <div class="eyebrow">Portada oficial de la liga</div>
        <h1>Resultados, posiciones y líderes de la jornada</h1>
        <p>La portada se actualiza con los juegos anotados, las estadísticas del cuaderno oficial y las noticias destacadas cargadas en Media.</p>
        <span class="season-pill"><?= h($season["name"]) ?></span>
      </div>
      <?php if ($recentResults): ?>
        <?php foreach (array_slice($recentResults, 0, 1) as $game): ?>
          <?php
            $homeTotals = $gameTotals[(int)$game["id"]][(int)$game["home_team_id"]] ?? ["hits"=>0, "errors"=>0];
            $awayTotals = $gameTotals[(int)$game["id"]][(int)$game["away_team_id"]] ?? ["hits"=>0, "errors"=>0];
            $homeRecord = "0-0"; $awayRecord = "0-0";
            foreach ($standings as $s) {
              if ($s["name"] === $game["home_name"]) $homeRecord = fmt_record_value($s["wins"], $s["losses"], $s["ties"]);
              if ($s["name"] === $game["away_name"]) $awayRecord = fmt_record_value($s["wins"], $s["losses"], $s["ties"]);
            }
          ?>
          <article class="card scorecard">
            <div class="scorebar"><strong>ULTIMO RESULTADO</strong><span><?= h(fmt_public_date($game["game_date"])) ?></span></div>
            <div class="scorebody">
              <div class="score-main">
                <div class="score-team">
                  <?php if ($game["home_logo"]): ?><img src="<?= h($game["home_logo"]) ?>" alt=""><?php endif; ?>
                  <h3><?= h($game["home_name"]) ?></h3>
                  <div class="record"><?= h($homeRecord) ?></div>
                </div>
                <div class="bigscore"><span class="<?= (int)$game["final_home"] >= (int)$game["final_away"] ? "winner" : "" ?>"><?= (int)$game["final_home"] ?></span><span class="dash">-</span><span class="<?= (int)$game["final_away"] > (int)$game["final_home"] ? "winner" : "" ?>"><?= (int)$game["final_away"] ?></span></div>
                <div class="score-team">
                  <?php if ($game["away_logo"]): ?><img src="<?= h($game["away_logo"]) ?>" alt=""><?php endif; ?>
                  <h3><?= h($game["away_name"]) ?></h3>
                  <div class="record"><?= h($awayRecord) ?></div>
                </div>
              </div>
              <div class="diamond">◇</div>
              <div class="final-label">FINAL</div>
              <div class="small" style="text-align:center"><?= h($game["location"] ?: "Campo Principal") ?></div>
              <table class="line-score">
                <thead><tr><th>Equipo</th><th>C</th><th>H</th><th>E</th></tr></thead>
                <tbody>
                  <tr><td><?= h($game["home_name"]) ?></td><td class="accent"><?= (int)$game["final_home"] ?></td><td><?= (int)$homeTotals["hits"] ?></td><td><?= (int)$homeTotals["errors"] ?></td></tr>
                  <tr><td><?= h($game["away_name"]) ?></td><td><?= (int)$game["final_away"] ?></td><td><?= (int)$awayTotals["hits"] ?></td><td><?= (int)$awayTotals["errors"] ?></td></tr>
                </tbody>
              </table>
            </div>
          </article>
          <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <main>
    <section>
      <div class="section-head">
        <h2 class="section-title">Calendario de Juegos</h2>
        <a class="section-link" href="/admin/schedule.php">Ver calendario completo →</a>
      </div>
      <?php if ($nextGames): ?>
        <div class="card">
          <div class="game-list">
            <?php foreach ($nextGames as $game): ?>
              <div class="game-row">
                <div class="game-date"><?= h(strtoupper((new DateTimeImmutable($game["game_date"]))->format("D"))) ?><b><?= h((new DateTimeImmutable($game["game_date"]))->format("j")) ?></b></div>
                <div class="teams-line">
                  <div class="mini-team"><?php if ($game["home_logo"]): ?><img src="<?= h($game["home_logo"]) ?>" alt=""><?php endif; ?><span><?= h($game["home_label"] ?: $game["home_name"]) ?></span></div>
                  <div class="mini-team"><?php if ($game["away_logo"]): ?><img src="<?= h($game["away_logo"]) ?>" alt=""><?php endif; ?><span><?= h($game["away_label"] ?: $game["away_name"]) ?></span></div>
                </div>
                <div class="game-time"><?= h(date("g:i A", strtotime($game["game_time"]))) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="empty">No hay juegos programados próximamente.</div>
      <?php endif; ?>
    </section>

    <div class="grid cols-2" style="margin-top:22px">
      <section>
        <div class="section-head">
          <h2 class="section-title">Últimas Noticias</h2>
          <a class="section-link" href="/admin/ai-publisher.php">Publicador IA →</a>
        </div>
        <div class="news-grid">
          <?php if ($aiNews || $featuredNews): ?>
            <?php foreach ($aiNews as $note): ?>
              <article class="news-card">
                <div class="news-thumb">IA</div>
                <div class="news-content">
                  <span class="tag">NOTICIA</span>
                  <h3 class="news-title"><?= h($note["title"]) ?></h3>
                  <p class="news-summary"><?= h($note["summary"]) ?></p>
                  <div class="meta"><?= h(fmt_public_date($note["game_date"])) ?> · Legends Softball League +50</div>
                </div>
              </article>
            <?php endforeach; ?>
            <?php foreach ($featuredNews as $item): ?>
              <article class="news-card">
                <div class="news-thumb">
                  <?php if (($item["type"] ?? "") === "image"): ?><img src="<?= h($item["thumbnail_url"] ?: $item["url"]) ?>" alt=""><?php else: ?>NOTICIA<?php endif; ?>
                </div>
                <div class="news-content">
                  <span class="tag">NOTICIA</span>
                  <h3 class="news-title"><?= h($item["title"]) ?></h3>
                  <div class="meta"><?= h($item["week_start"] ?: date("Y-m-d", strtotime($item["created_at"] ?? "now"))) ?></div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty">Publica una nota IA o marca noticias destacadas en Media para mostrarlas aquí.</div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <h2 class="section-title">Posiciones</h2>
        <table>
          <thead><tr><th>Equipo</th><th>Récord</th><th>RF</th><th>RA</th><th>DIF</th></tr></thead>
          <tbody>
            <?php foreach ($standings as $row): ?>
              <tr>
                <td><div class="team"><?php if ($row["logo_url"]): ?><img src="<?= h($row["logo_url"]) ?>" alt=""><?php endif; ?><strong><?= h($row["name"]) ?></strong></div></td>
                <td><?= h(fmt_record_value($row["wins"], $row["losses"], $row["ties"])) ?></td>
                <td><?= (int)$row["runs_for"] ?></td>
                <td><?= (int)$row["runs_against"] ?></td>
                <td><?= (int)$row["run_diff"] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="height:18px"></div>

        <h2 class="section-title">Líderes</h2>
        <div class="grid cols-3">
          <?php foreach ($leaderCards as $card): ?>
            <div class="leader">
              <b><?= h($card["label"]) ?> (<?= h($card["abbr"]) ?>)</b>
              <div class="value"><?= h(display_leader_value($card)) ?></div>
              <?php if (!empty($card["row"])): ?>
                <div class="name"><?= h(($card["row"]["number"] ? "#" . $card["row"]["number"] . " " : "") . $card["row"]["player_name"]) ?></div>
                <div class="small"><?= h($card["row"]["team_name"] ?: "-") ?></div>
              <?php else: ?>
                <div class="small">Sin datos todavía</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="links">
          <a class="btn" href="/admin/leaders.php">Ver todos los líderes</a>
          <a class="btn secondary" href="/admin/games.php">Anotar jornada</a>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
