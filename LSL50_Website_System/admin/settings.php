<?php
declare(strict_types=1);

require __DIR__ . "/../config.php";
require_once __DIR__ . "/../src/autoload.php";
require_admin();

use Lsl50\Services\AppSettingsService;

$pdo = db();
$season = active_season($pdo);
$aiAutoGenerate = AppSettingsService::isAiAutoGenerateOnClose($pdo);
$publishMode = lsl_setting($pdo, "ai_publish_mode", "review");
$hasOpenAiKey = lsl_setting($pdo, "openai_api_key", "") !== "";
$csrfToken = admin_csrf_token();

include __DIR__ . "/../partials/header.php";
?>

<style>
  .settings-hero{
    display:grid;grid-template-columns:1.2fr .8fr;gap:20px;align-items:stretch;
    background:linear-gradient(135deg,#061b3b 0%,#0b2d5c 55%,#123f78 100%);
    color:#fff;border-radius:14px;padding:26px 28px;margin-bottom:22px;
    border:1px solid rgba(215,167,47,.35);box-shadow:0 18px 40px rgba(6,27,59,.18);
    position:relative;overflow:hidden;
  }
  .settings-hero::after{
    content:"";position:absolute;inset:auto -40px -80px auto;width:220px;height:220px;border-radius:50%;
    background:radial-gradient(circle,rgba(215,167,47,.28) 0%,rgba(215,167,47,0) 70%);
    pointer-events:none;
  }
  .settings-kicker{color:#f6d98d;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
  .settings-title{font-size:34px;line-height:1.05;margin:8px 0 10px;font-weight:950}
  .settings-sub{color:#c8d7ea;font-size:16px;line-height:1.5;max-width:54ch;margin:0}
  .settings-meta{display:flex;flex-direction:column;justify-content:center;gap:10px}
  .settings-pill{
    display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:10px 14px;
    background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
    font-size:13px;font-weight:800;color:#eef4ff;
  }
  .settings-pill b{color:#f6d98d}

  .settings-grid{display:grid;gap:18px}
  .settings-card{
    background:#fff;border:1px solid var(--line);border-radius:14px;padding:0;overflow:hidden;
    box-shadow:0 10px 30px rgba(16,24,40,.05);
  }
  .settings-card-head{
    padding:18px 20px;border-bottom:1px solid var(--line);
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);
  }
  .settings-card-head h2{margin:0;font-size:20px}
  .settings-card-head p{margin:4px 0 0;color:var(--muted);font-size:13px}
  .settings-card-body{padding:20px}

  .toggle-panel{
    display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;
    padding:18px;border-radius:12px;border:1px solid #e4e9f0;background:#fbfcfe;
    transition:border-color .25s ease,box-shadow .25s ease,transform .25s ease;
  }
  .toggle-panel.is-active{
    border-color:rgba(6,118,71,.35);background:linear-gradient(180deg,#f6fffb 0%,#f0fdf7 100%);
    box-shadow:0 10px 24px rgba(6,118,71,.08);
  }
  .toggle-panel.is-busy{opacity:.78;pointer-events:none}
  .toggle-panel.is-error{border-color:rgba(180,35,24,.35);background:#fff7f7}
  .toggle-copy h3{margin:0 0 6px;font-size:18px}
  .toggle-copy p{margin:0;color:var(--muted);font-size:14px;line-height:1.5;max-width:62ch}
  .toggle-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
  .toggle-badge{
    display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;
    font-size:12px;font-weight:900;background:#eef2f6;color:#475467;
  }
  .toggle-badge.on{background:#dcfae6;color:#067647}
  .toggle-badge.off{background:#fee4e2;color:#b42318}

  .lsl-switch{
    position:relative;width:68px;height:38px;border:0;padding:0;background:transparent;cursor:pointer;flex-shrink:0;
  }
  .lsl-switch:focus-visible{outline:3px solid rgba(215,167,47,.45);outline-offset:3px;border-radius:999px}
  .lsl-switch-track{
    display:block;width:100%;height:100%;border-radius:999px;background:#cbd5e1;
    box-shadow:inset 0 1px 2px rgba(15,23,42,.12);transition:background .28s cubic-bezier(.4,0,.2,1),box-shadow .28s ease;
  }
  .lsl-switch-thumb{
    position:absolute;top:4px;left:4px;width:30px;height:30px;border-radius:50%;background:#fff;
    box-shadow:0 4px 10px rgba(15,23,42,.18);transition:transform .28s cubic-bezier(.4,0,.2,1),box-shadow .28s ease;
  }
  .lsl-switch[aria-checked="true"] .lsl-switch-track{
    background:linear-gradient(135deg,#067647 0%,#12b76a 100%);
    box-shadow:inset 0 1px 2px rgba(6,78,59,.25),0 0 0 1px rgba(18,183,106,.25);
  }
  .lsl-switch[aria-checked="true"] .lsl-switch-thumb{
    transform:translateX(30px);box-shadow:0 6px 14px rgba(6,118,71,.28);
  }
  .lsl-switch.is-saving .lsl-switch-thumb{animation:lslPulse .9s ease infinite}
  @keyframes lslPulse{0%,100%{transform:translateX(var(--tx,0)) scale(1)}50%{transform:translateX(var(--tx,0)) scale(.94)}}
  .lsl-switch[aria-checked="true"].is-saving .lsl-switch-thumb{--tx:30px}

  .settings-toast{
    position:fixed;right:18px;bottom:18px;z-index:50;min-width:280px;max-width:360px;
    padding:14px 16px;border-radius:12px;color:#fff;font-weight:800;opacity:0;transform:translateY(12px);
    transition:opacity .25s ease,transform .25s ease;pointer-events:none;
    box-shadow:0 16px 40px rgba(16,24,40,.18);
  }
  .settings-toast.show{opacity:1;transform:translateY(0)}
  .settings-toast.ok{background:linear-gradient(135deg,#067647,#12b76a)}
  .settings-toast.err{background:linear-gradient(135deg,#b42318,#f04438)}

  .settings-links{display:grid;gap:12px}
  .settings-link{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:14px 16px;border-radius:10px;border:1px solid var(--line);text-decoration:none;color:inherit;
    background:#fff;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease;
  }
  .settings-link:hover{transform:translateY(-1px);border-color:#c7d2e0;box-shadow:0 8px 20px rgba(16,24,40,.06)}
  .settings-link strong{display:block;font-size:15px}
  .settings-link span{font-size:13px;color:var(--muted)}

  @media (max-width:760px){
    .settings-hero{grid-template-columns:1fr}
    .settings-title{font-size:28px}
    .toggle-panel{grid-template-columns:1fr}
    .lsl-switch{justify-self:flex-start}
  }
</style>

<div class="settings-hero">
  <div>
    <div class="settings-kicker">Panel Enterprise · Temporada <?= h((string)$season["name"]) ?></div>
    <h1 class="settings-title">Configuración del Sistema</h1>
    <p class="settings-sub">Automatizaciones inteligentes, integraciones IA y preferencias operativas del administrador LSL50.</p>
  </div>
  <div class="settings-meta">
    <div class="settings-pill">Publicación IA: <b><?= $publishMode === "auto" ? "Automática" : "Revisión manual" ?></b></div>
    <div class="settings-pill">OpenAI: <b><?= $hasOpenAiKey ? "Conectado" : "Pendiente" ?></b></div>
  </div>
</div>

<div class="settings-grid">
  <section class="settings-card" aria-labelledby="ai-settings-title">
    <div class="settings-card-head">
      <div>
        <h2 id="ai-settings-title">Automatización IA</h2>
        <p>Controla el comportamiento del Sports Writer al cerrar juegos oficiales.</p>
      </div>
    </div>
    <div class="settings-card-body">
      <div
        id="aiAutoGeneratePanel"
        class="toggle-panel <?= $aiAutoGenerate ? "is-active" : "" ?>"
        data-setting-key="<?= h(AppSettingsService::KEY_AI_AUTO_GENERATE_ON_CLOSE) ?>"
      >
        <div class="toggle-copy">
          <h3>Generar crónica al cerrar juego</h3>
          <p>
            Cuando un juego se marca como final, el pipeline ejecuta <strong>AiNewsGenerator</strong> automáticamente
            tras recalcular estadísticas. Desactívalo si prefieres generar notas manualmente desde el Publicador IA.
          </p>
          <div class="toggle-badges">
            <span id="aiAutoGenerateBadge" class="toggle-badge <?= $aiAutoGenerate ? "on" : "off" ?>">
              <?= $aiAutoGenerate ? "Activo" : "Inactivo" ?>
            </span>
            <span class="toggle-badge">Clave: ai_auto_generate_on_close</span>
          </div>
        </div>
        <button
          id="aiAutoGenerateToggle"
          type="button"
          class="lsl-switch"
          role="switch"
          aria-checked="<?= $aiAutoGenerate ? "true" : "false" ?>"
          aria-label="Generar crónica IA al cerrar juego"
        >
          <span class="lsl-switch-track" aria-hidden="true"></span>
          <span class="lsl-switch-thumb" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </section>

  <section class="settings-card">
    <div class="settings-card-head">
      <div>
        <h2>Integraciones relacionadas</h2>
        <p>Accesos directos a módulos conectados con esta automatización.</p>
      </div>
    </div>
    <div class="settings-card-body settings-links">
      <a class="settings-link" href="/admin/ai-publisher.php">
        <div>
          <strong>Publicador IA</strong>
          <span>Claves OpenAI/YouTube, estilo editorial y publicación de notas.</span>
        </div>
        <span aria-hidden="true">→</span>
      </a>
      <a class="settings-link" href="/admin/games.php">
        <div>
          <strong>Juegos y cierre oficial</strong>
          <span>Dispara el pipeline al finalizar partidos desde el cuaderno.</span>
        </div>
        <span aria-hidden="true">→</span>
      </a>
    </div>
  </section>
</div>

<div id="settingsToast" class="settings-toast" role="status" aria-live="polite"></div>

<script>
(() => {
  const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
  const toggle = document.getElementById("aiAutoGenerateToggle");
  const panel = document.getElementById("aiAutoGeneratePanel");
  const badge = document.getElementById("aiAutoGenerateBadge");
  const toast = document.getElementById("settingsToast");
  const settingKey = panel?.dataset.settingKey || "ai_auto_generate_on_close";
  let toastTimer = null;

  function showToast(message, ok = true) {
    if (!toast) return;
    toast.textContent = message;
    toast.className = "settings-toast show " + (ok ? "ok" : "err");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("show"), 2600);
  }

  function setVisualState(enabled, busy = false) {
    if (!toggle || !panel || !badge) return;
    toggle.setAttribute("aria-checked", enabled ? "true" : "false");
    toggle.classList.toggle("is-saving", busy);
    panel.classList.toggle("is-active", enabled);
    panel.classList.toggle("is-busy", busy);
    panel.classList.remove("is-error");
    badge.textContent = enabled ? "Activo" : "Inactivo";
    badge.classList.toggle("on", enabled);
    badge.classList.toggle("off", !enabled);
  }

  async function persistSetting(enabled) {
    const previous = toggle.getAttribute("aria-checked") === "true";
    setVisualState(enabled, true);
    try {
      const response = await fetch("/admin/api/setting.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify({
          key: settingKey,
          value: enabled ? "1" : "0",
        }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload?.error?.message || "No fue posible guardar la configuración.");
      }
      const saved = !!payload.setting?.value;
      setVisualState(saved, false);
      showToast(saved ? "Automatización IA activada." : "Automatización IA desactivada.", true);
    } catch (error) {
      setVisualState(previous, false);
      panel.classList.add("is-error");
      showToast(error.message || "Error al guardar.", false);
    }
  }

  toggle?.addEventListener("click", () => {
    if (panel.classList.contains("is-busy")) return;
    const next = toggle.getAttribute("aria-checked") !== "true";
    persistSetting(next);
  });
})();
</script>

<?php include __DIR__ . "/../partials/footer.php"; ?>
