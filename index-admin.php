<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* ── Protecção da página ── */
if (
    !isset($_SESSION['id_utilizador']) ||
    !isset($_SESSION['tipo_utilizador']) ||
    $_SESSION['tipo_utilizador'] !== 'admin_clube' ||
    !isset($_SESSION['id_clube'])
) {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];
$id_clube      = $_SESSION['id_clube'];

/* ── Buscar dados do clube + estádio ── */
$stmt = $conn->prepare("
    SELECT c.nome_clube, c.sigla, c.cor, c.logotipo,
           c.data_fundação, c.sede_morada, c.cidade_clube,
           c.país_clube, c.telefone_clube, c.email_clube,
           c.website_clube, c.presidente_clube,
           e.nome_estádio
    FROM clube c
    LEFT JOIN estádio e ON e.id_clube = c.id_clube
    WHERE c.id_clube = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_clube);
$stmt->execute();
$clube = $stmt->get_result()->fetch_assoc();

if (!$clube) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* ── Variáveis de conveniência ── */
$nomeClube  = $clube['nome_clube'];
$siglaClube = $clube['sigla'];
$corClube   = $clube['cor'];
$logoClube  = $clube['logotipo']
    ? 'data:image/png;base64,' . base64_encode($clube['logotipo'])
    : null;

/* ── Formatar data de fundação para PT ── */
$dataFundacao = '';
if (!empty($clube['data_fundação'])) {
    $ts = strtotime($clube['data_fundação']);
    $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
              'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $dataFundacao = date('j', $ts) . ' de ' . $meses[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

/* ── Morada completa ── */
$morada = implode(', ', array_filter([
    $clube['sede_morada'],
    $clube['cidade_clube'],
    $clube['país_clube'],
]));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | <?= htmlspecialchars($nomeClube) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --club:   <?= htmlspecialchars($corClube) ?>;
    --sidebar-w: 68px;
    --topbar-h:  64px;
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

body { background: #f0f2f7; }

/* ══════════════════════════════════
   TOP BAR
══════════════════════════════════ */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--topbar-h);
    background: var(--club);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 0 calc(var(--sidebar-w) + 20px);
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.18);
}

.topbar-left {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.topbar-name {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}

.topbar-sigla {
    font-size: 12px;
    font-weight: 500;
    color: rgba(255,255,255,.75);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.topbar-logo {
    height: 28px;
    filter: brightness(0) invert(1);
}

.topbar-menu {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.topbar-menu span {
    display: block;
    width: 22px;
    height: 2px;
    background: rgba(255,255,255,.85);
    border-radius: 2px;
}

/* ══════════════════════════════════
   SIDEBAR
══════════════════════════════════ */
.sidebar {
    position: fixed;
    top: var(--topbar-h);
    left: 0;
    width: var(--sidebar-w);
    height: calc(100vh - var(--topbar-h));
    background: var(--club);
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    padding: 16px 0;
    z-index: 99;
    transition: width .22s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
    box-shadow: 2px 0 12px rgba(0,0,0,.10);
}

.sidebar:hover { width: 210px; }

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 10px 0;
    color: rgba(255,255,255,.78);
    text-decoration: none;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 500;
    border-left: 3px solid transparent;
    transition: background .15s, color .15s, border-color .15s;
}

.sidebar:hover a {
    justify-content: flex-start;
    padding: 10px 20px;
}

.sidebar a:hover,
.sidebar a.active {
    background: rgba(255,255,255,.13);
    color: #fff;
    border-left-color: #fff;
}

.sidebar a img {
    width: 34px;
    height: 34px;
    object-fit: contain;
    flex-shrink: 0;
    filter: brightness(0) invert(1);
    opacity: .85;
    transition: width .22s, height .22s, opacity .15s;
}

.sidebar:hover a img {
    width: 24px;
    height: 24px;
}

.sidebar a:hover img,
.sidebar a.active img { opacity: 1; }

.sidebar a span {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: opacity .18s, width .22s;
}

.sidebar:hover a span {
    opacity: 1;
    width: auto;
}

/* ══════════════════════════════════
   MAIN CONTENT
══════════════════════════════════ */
.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 28px 28px 40px;
    min-height: calc(100vh - var(--topbar-h));
    transition: margin-left .22s cubic-bezier(.4,0,.2,1);
}

.sidebar:hover ~ .main { margin-left: 210px; }

/* ══════════════════════════════════
   CARD
══════════════════════════════════ */
.card {
    background: #fff;
    border-radius: 20px;
    padding: 28px 32px 36px;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    position: relative;
}

/* ── Botão de edição ── */
.btn-edit {
    position: absolute;
    top: 24px;
    right: 28px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1.5px solid #ddd;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s, border-color .15s;
}

.btn-edit:hover { background: #f5f5f5; border-color: #bbb; }

.btn-edit svg { width: 16px; height: 16px; color: #555; }

/* ── Tabs ── */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 28px;
    border-bottom: 1.5px solid #ebebeb;
    padding-bottom: 0;
}

.tab {
    padding: 9px 22px;
    border-radius: 10px 10px 0 0;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    color: #666;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1.5px;
    transition: color .15s, border-color .15s;
}

.tab:hover { color: #222; }

.tab.active {
    color: var(--club);
    border-bottom-color: var(--club);
    font-weight: 600;
}

/* ── Painel tab ── */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── Info layout ── */
.info-layout {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 32px;
}

.info-fields { flex: 1; }

.info-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f3f3;
    font-size: 14.5px;
}

.info-row:last-child { border-bottom: none; }

.info-label {
    font-weight: 600;
    color: #222;
    min-width: 160px;
    flex-shrink: 0;
}

.info-value {
    color: #444;
}

.info-value.empty {
    color: #bbb;
    font-style: italic;
}

/* ── Logo do clube ── */
.club-logo-wrap {
    flex-shrink: 0;
    width: 160px;
    height: 160px;
    background: #f7f7f7;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid #ebebeb;
}

.club-logo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 12px;
}

.club-logo-placeholder {
    font-size: 32px;
    font-weight: 700;
    color: var(--club);
    letter-spacing: -1px;
}

/* ── Estado vazio para outras tabs ── */
.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #bbb;
    font-size: 14px;
}

.empty-state svg {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    opacity: .3;
}
</style>
</head>
<body>

<!-- ══ TOP BAR ══ -->
<div class="topbar">
    <div class="topbar-left">
        <span class="topbar-name"><?= htmlspecialchars($nomeClube) ?></span>
        <?php if ($siglaClube): ?>
            <span class="topbar-sigla"><?= htmlspecialchars($siglaClube) ?></span>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <img src="assets/kroos-logo-branco.png" class="topbar-logo" alt="Kroos">
        <button class="topbar-menu" aria-label="Menu" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </button>
    </div>
</div>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar" id="sidebar">
    <a href="#" class="active">
        <img src="assets/clube.png" alt="">
        <span>Clube</span>
    </a>
    <a href="#">
        <img src="assets/escaloes.png" alt="">
        <span>Escalões</span>
    </a>
    <a href="#">
        <img src="assets/eventos.png" alt="">
        <span>Eventos</span>
    </a>
    <a href="#">
        <img src="assets/calendario.png" alt="">
        <span>Calendário</span>
    </a>
    <a href="#">
        <img src="assets/mensagens.png" alt="">
        <span>Mensagens</span>
    </a>
    <a href="#">
        <img src="assets/home.png" alt="">
        <span>Página Principal</span>
    </a>
</div>

<!-- ══ MAIN ══ -->
<div class="main">
    <div class="card">

        <!-- Botão editar -->
        <button class="btn-edit" title="Editar informações do clube">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
        </button>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab(this,'tab-info')">Info</button>
            <button class="tab"        onclick="switchTab(this,'tab-escaloes')">Escalões</button>
            <button class="tab"        onclick="switchTab(this,'tab-treinadores')">Treinadores</button>
        </div>

        <!-- ── Painel Info ── -->
        <div class="tab-panel active" id="tab-info">
            <div class="info-layout">

                <div class="info-fields">

                    <div class="info-row">
                        <span class="info-label">Nome:</span>
                        <span class="info-value"><?= htmlspecialchars($nomeClube) ?></span>
                    </div>

                    <?php if (!empty($clube['nome_estádio'])): ?>
                    <div class="info-row">
                        <span class="info-label">Estádio:</span>
                        <span class="info-value"><?= htmlspecialchars($clube['nome_estádio']) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <span class="info-label">Data de Fundação:</span>
                        <span class="info-value <?= $dataFundacao ? '' : 'empty' ?>">
                            <?= $dataFundacao ?: 'Não definida' ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Morada:</span>
                        <span class="info-value <?= $morada ? '' : 'empty' ?>">
                            <?= $morada ? htmlspecialchars($morada) : 'Não definida' ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Telemóvel:</span>
                        <span class="info-value <?= !empty($clube['telefone_clube']) ? '' : 'empty' ?>">
                            <?= !empty($clube['telefone_clube'])
                                ? htmlspecialchars($clube['telefone_clube'])
                                : 'Não definido' ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value <?= !empty($clube['email_clube']) ? '' : 'empty' ?>">
                            <?php if (!empty($clube['email_clube'])): ?>
                                <a href="mailto:<?= htmlspecialchars($clube['email_clube']) ?>"
                                   style="color:var(--club);text-decoration:none;">
                                    <?= htmlspecialchars($clube['email_clube']) ?>
                                </a>
                            <?php else: ?>
                                Não definido
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if (!empty($clube['website_clube'])): ?>
                    <div class="info-row">
                        <span class="info-label">Website:</span>
                        <span class="info-value">
                            <a href="<?= htmlspecialchars($clube['website_clube']) ?>"
                               target="_blank" style="color:var(--club);text-decoration:none;">
                                <?= htmlspecialchars($clube['website_clube']) ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($clube['presidente_clube'])): ?>
                    <div class="info-row">
                        <span class="info-label">Presidente:</span>
                        <span class="info-value"><?= htmlspecialchars($clube['presidente_clube']) ?></span>
                    </div>
                    <?php endif; ?>

                </div><!-- /.info-fields -->

                <!-- Logo -->
                <div class="club-logo-wrap">
                    <?php if ($logoClube): ?>
                        <img src="<?= $logoClube ?>" alt="Logótipo de <?= htmlspecialchars($nomeClube) ?>">
                    <?php else: ?>
                        <span class="club-logo-placeholder"><?= htmlspecialchars($siglaClube) ?></span>
                    <?php endif; ?>
                </div>

            </div><!-- /.info-layout -->
        </div><!-- /#tab-info -->

        <!-- ── Painel Escalões ── -->
        <div class="tab-panel" id="tab-escaloes">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4M12 16h.01"/>
                </svg>
                <p>Ainda não há escalões criados.</p>
            </div>
        </div>

        <!-- ── Painel Treinadores ── -->
        <div class="tab-panel" id="tab-treinadores">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4M12 16h.01"/>
                </svg>
                <p>Ainda não há treinadores associados.</p>
            </div>
        </div>

    </div><!-- /.card -->
</div><!-- /.main -->

<script>
function switchTab(btn, panelId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(panelId).classList.add('active');
}

// Sidebar pin/unpin com o botão do menu (opcional)
let sidebarPinned = false;
function toggleSidebar() {
    sidebarPinned = !sidebarPinned;
    const s = document.getElementById('sidebar');
    s.style.width = sidebarPinned ? '210px' : '';
}
</script>

</body>
</html>