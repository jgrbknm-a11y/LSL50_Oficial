
<?php
require __DIR__ . "/config.php";
$lock = __DIR__ . "/installed.lock";
if (file_exists($lock)) return;
try {
  $pdo = db();
  $pdo->exec(file_get_contents(__DIR__ . "/db/schema.sql"));
  $stmt = $pdo->prepare("SELECT COUNT(*) c FROM users WHERE email=?"); $stmt->execute([$GLOBALS['ADMIN_EMAIL']]);
  if (($stmt->fetch()['c'] ?? 0)==0){ $hash=password_hash($GLOBALS['ADMIN_PASS'], PASSWORD_BCRYPT); $pdo->prepare("INSERT INTO users (email,password_hash,role) VALUES (?,?, 'admin')")->execute([$GLOBALS['ADMIN_EMAIL'],$hash]); }
  $c = $pdo->query("SELECT COUNT(*) c FROM teams")->fetch()['c'] ?? 0;
  if ($c==0) { $pdo->exec(file_get_contents(__DIR__ . "/db/demo_seed.sql")); }
  $players = $pdo->query("SELECT DISTINCT player_id FROM game_player_stats")->fetchAll();
  foreach ($players as $row) {
    $pid=(int)$row['player_id'];
    $s=$pdo->prepare("SELECT COUNT(*) GP, COALESCE(SUM(AB),0) AB, COALESCE(SUM(H),0) H, COALESCE(SUM(dbl),0) dbl, COALESCE(SUM(tpl),0) tpl, COALESCE(SUM(R),0) R, COALESCE(SUM(RBI),0) RBI, COALESCE(SUM(HR),0) HR, COALESCE(SUM(BB),0) BB, COALESCE(SUM(SO),0) SO, COALESCE(SUM(SB),0) SB FROM game_player_stats WHERE player_id=?"); $s->execute([$pid]); $r=$s->fetch();
    $AB=(int)$r['AB']; $H=(int)$r['H']; $DBL=(int)$r['dbl']; $TPL=(int)$r['tpl']; $HR=(int)$r['HR']; $BB=(int)$r['BB'];
    $SNG=max($H-$DBL-$TPL-$HR,0); $TB=$SNG*1+$DBL*2+$TPL*3+$HR*4;
    $AVG=$AB>0?round($H/$AB,3):0; $OBP=($AB+$BB)>0?round(($H+$BB)/($AB+$BB),3):0; $SLG=$AB>0?round($TB/$AB,3):0;
    $pdo->prepare("INSERT INTO player_stats (player_id,games_played,AB,H,dbl,tpl,TB,R,RBI,HR,BB,SO,SB,AVG,OBP,SLG) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE games_played=VALUES(games_played),AB=VALUES(AB),H=VALUES(H),dbl=VALUES(dbl),tpl=VALUES(tpl),TB=VALUES(TB),R=VALUES(R),RBI=VALUES(RBI),HR=VALUES(HR),BB=VALUES(BB),SO=VALUES(SO),SB=VALUES(SB),AVG=VALUES(AVG),OBP=VALUES(OBP),SLG=VALUES(SLG)")->execute([$pid,(int)$r['GP'],$AB,$H,$DBL,$TPL,$TB,(int)$r['R'],(int)$r['RBI'],$HR,$BB,(int)$r['SO'],(int)$r['SB'],$AVG,$OBP,$SLG]);
  }
  file_put_contents($lock, date('c'));
} catch (Throwable $e) {}
