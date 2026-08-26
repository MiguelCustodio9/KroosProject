<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* ══════════════════════════════════
   Protecção da página
   NOTA: assume-se um tipo_utilizador 'admin_sistema'.
   Ajusta este bloco quando a autenticação de admin de sistema
   estiver definida no resto do projecto.
══════════════════════════════════ */
if (
    !isset($_SESSION['id_utilizador']) ||
    !isset($_SESSION['tipo_utilizador'])
) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['tipo_utilizador'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];

/* ── View activa (menu escolhido na sidebar) ── */
$viewsValidas = [
    'utilizadores',
    'competicoes',
    'jogadores',
    'clubes',
    'notificacoes_gestao',
    'definicoes',
    'home'
];
$viewMode = $_GET['view'] ?? 'home';
if (!in_array($viewMode, $viewsValidas, true)) {
    $viewMode = 'home';
}

/* ── Buscar perfil do utilizador (admin de sistema) ── */
$perfilUtilizador = [];
$stmtPerfil = $conn->prepare("
    SELECT nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador,
           primeiro_nome, `último_nome`, data_nascimento
    FROM utilizador
    WHERE id_utilizador = ?
    LIMIT 1
");
$stmtPerfil->bind_param("i", $id_utilizador);
$stmtPerfil->execute();
$perfilUtilizador = $stmtPerfil->get_result()->fetch_assoc() ?: [];

$fotoPerfilUtilizador = !empty($perfilUtilizador['foto_perfil'])
    ? 'data:image/png;base64,' . base64_encode($perfilUtilizador['foto_perfil'])
    : null;

$erro = '';
$sucesso = '';
if (isset($_SESSION['flash_sucesso'])) {
    $sucesso = $_SESSION['flash_sucesso'];
    unset($_SESSION['flash_sucesso']);
}
if (isset($_SESSION['flash_erro'])) {
    $erro = $_SESSION['flash_erro'];
    unset($_SESSION['flash_erro']);
}

/* ══════════════════════════════════
   AÇÕES POST (perfil / notificações)
   Ainda sem outras funcionalidades — só o essencial partilhado
   com o resto da plataforma.
══════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'editar_perfil') {
        $nomeUtilizador = trim($_POST['nome_utilizador'] ?? '');
        $emailUtilizador = trim($_POST['email'] ?? '');
        $primeiroNome = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome = trim($_POST['ultimo_nome'] ?? '');
        $telefoneUtilizador = trim($_POST['telemovel'] ?? '');
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');
        $fotoPerfilAjustada = trim($_POST['foto_perfil_ajustada'] ?? '');
        $novaFotoPerfil = null;

        if ($nomeUtilizador === '' || $primeiroNome === '' || $ultimoNome === '' || $emailUtilizador === '') {
            $erro = 'Preenche todos os campos obrigatórios do perfil.';
        } elseif (!filter_var($emailUtilizador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'O email do perfil não é válido.';
        } else {
            $stmtCheckPerfilEmail = $conn->prepare("
                SELECT id_utilizador FROM utilizador
                WHERE email_utilizador = ? AND id_utilizador <> ?
                LIMIT 1
            ");
            $stmtCheckPerfilEmail->bind_param("si", $emailUtilizador, $id_utilizador);
            $stmtCheckPerfilEmail->execute();
            if ($stmtCheckPerfilEmail->get_result()->fetch_assoc()) {
                $erro = 'Já existe outro utilizador com este email.';
            }
        }

        if (!$erro) {
            if ($fotoPerfilAjustada !== '') {
                if (!preg_match('/^data:(image\/(jpeg|png|webp));base64,([A-Za-z0-9+\/=]+)$/', $fotoPerfilAjustada, $matchesFotoAjustada)) {
                    $erro = 'Formato da foto ajustada inválido.';
                } else {
                    $binarioFotoAjustada = base64_decode($matchesFotoAjustada[3], true);
                    if ($binarioFotoAjustada === false) {
                        $erro = 'Não foi possível processar a foto ajustada.';
                    } elseif (strlen($binarioFotoAjustada) > 2 * 1024 * 1024) {
                        $erro = 'A foto de perfil ajustada deve ter no máximo 2MB.';
                    } else {
                        $infoFotoAjustada = @getimagesizefromstring($binarioFotoAjustada);
                        if ($infoFotoAjustada === false) {
                            $erro = 'A foto ajustada não é uma imagem válida.';
                        } else {
                            $novaFotoPerfil = $binarioFotoAjustada;
                        }
                    }
                }
            }

            if (!$erro) {
                if ($novaFotoPerfil !== null) {
                    $stmtUpdatePerfil = $conn->prepare("
                        UPDATE utilizador
                        SET nome_utilizador = ?, foto_perfil = ?, email_utilizador = ?,
                            telefone_utilizador = NULLIF(?, ''), primeiro_nome = ?,
                            `último_nome` = ?, data_nascimento = NULLIF(?, '')
                        WHERE id_utilizador = ?
                    ");
                    $stmtUpdatePerfil->bind_param(
                        "sssssssi",
                        $nomeUtilizador, $novaFotoPerfil, $emailUtilizador, $telefoneUtilizador,
                        $primeiroNome, $ultimoNome, $dataNascimento, $id_utilizador
                    );
                } else {
                    $stmtUpdatePerfil = $conn->prepare("
                        UPDATE utilizador
                        SET nome_utilizador = ?, email_utilizador = ?,
                            telefone_utilizador = NULLIF(?, ''), primeiro_nome = ?,
                            `último_nome` = ?, data_nascimento = NULLIF(?, '')
                        WHERE id_utilizador = ?
                    ");
                    $stmtUpdatePerfil->bind_param(
                        "ssssssi",
                        $nomeUtilizador, $emailUtilizador, $telefoneUtilizador,
                        $primeiroNome, $ultimoNome, $dataNascimento, $id_utilizador
                    );
                }

                if (!$stmtUpdatePerfil->execute()) {
                    $erro = 'Erro ao guardar as alterações do perfil.';
                } else {
                    $sucesso = 'Perfil atualizado com sucesso.';
                }
            }
        }
    }

    if ($acao === 'alterar_estado_notificacao' || $acao === 'marcar_notificacao_lida') {
        $idNotificacao = (int)($_POST['id_notificacao'] ?? 0);
        $estadoNotificacao = $_POST['estado'] ?? 'Lida';

        if ($acao === 'marcar_notificacao_lida') {
            $estadoNotificacao = 'Lida';
        }
        if (!in_array($estadoNotificacao, ['Lida', 'Nao Lida'], true)) {
            $estadoNotificacao = 'Lida';
        }

        if ($idNotificacao > 0) {
            $stmtMarcaLida = $conn->prepare("
                UPDATE notificacao
                SET estado = ?, lida_em = CASE WHEN ? = 'Lida' THEN NOW() ELSE NULL END
                WHERE id_notificacao = ? AND id_utilizador = ?
            ");
            $stmtMarcaLida->bind_param("ssii", $estadoNotificacao, $estadoNotificacao, $idNotificacao, $id_utilizador);
            $stmtMarcaLida->execute();
        }
        exit;
    }
}

/* ── Buscar notificações do utilizador ── */
$notificacoesUtilizador = [];
$stmtNotificacoes = $conn->prepare("
    SELECT id_notificacao, titulo, mensagem, tipo, estado, criada_em, lida_em, link_acao
    FROM notificacao
    WHERE id_utilizador = ?
    ORDER BY criada_em DESC
    LIMIT 20
");
$stmtNotificacoes->bind_param("i", $id_utilizador);
$stmtNotificacoes->execute();
$resNotificacoes = $stmtNotificacoes->get_result();
while ($row = $resNotificacoes->fetch_assoc()) {
    $notificacoesUtilizador[] = $row;
}

/* ── Menus de gestão (sidebar) ── */
$menusGestao = [
    'utilizadores'          => ['label' => 'Gestão de Utilizadores', 'icon' => 'assets/utilizadores.png'],
    'competicoes'           => ['label' => 'Gestão de Competições',  'icon' => 'assets/campeonato.png'],
    'jogadores'              => ['label' => 'Gestão de Jogadores',    'icon' => 'assets/jogadores.png'],
    'clubes'                 => ['label' => 'Gestão de Clubes',       'icon' => 'assets/clube.png'],
    'notificacoes_gestao'    => ['label' => 'Gestão de Notificações', 'icon' => 'assets/mensagens.png'],
    'definicoes'             => ['label' => 'Definições Gerais',      'icon' => 'assets/definicoes.png'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos Admin de Sistema</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --club: #000000;
    --sidebar-w: 68px;
    --topbar-h: 64px;
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow-y: auto;
}

body { background: #ffffff; color: #000000; }

body.layout-locked { overflow: hidden; }

/* ══════════════════════════════════
   TOP BAR
══════════════════════════════════ */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--topbar-h);
    background: #ffffff;
    border-bottom: 1px solid #eaeaea;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 0 calc(var(--sidebar-w) + 20px);
    z-index: 100;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: calc(-1 * var(--sidebar-w));
}

.topbar-club-logo {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: #f3f3f3;
    border: 1px solid #e2e2e2;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.topbar-club-logo--placeholder {
    color: #000;
    font-size: 11px;
    font-weight: 700;
}

.topbar-club-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.topbar-name {
    font-size: 14px;
    font-weight: 700;
    color: #000;
    line-height: 1.2;
}

.topbar-sigla {
    font-size: 12px;
    font-weight: 500;
    color: #6b7280;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.topbar-logo {
    height: 26px;
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
    background: #000;
    border-radius: 2px;
}

/* ══════════════════════════════════
   MENU SUPERIOR DIREITO
══════════════════════════════════ */
.topbar-user-menu-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.user-dropdown {
    position: absolute;
    top: calc(100% + 16px);
    right: 0;
    width: 230px;
    background: #ffffff;
    border: 1px solid #eaeaea;
    display: none;
    flex-direction: column;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(0,0,0,.12);
    z-index: 500;
}

.user-dropdown.active {
    display: flex;
}

.user-dropdown a {
    min-height: 58px;
    padding: 0 22px;
    display: flex;
    align-items: center;
    color: #000;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}

.user-dropdown a:hover {
    background: #f5f5f5;
}

.user-dropdown a.logout-link {
    color: #b00020;
    font-weight: 600;
}

.user-dropdown a.logout-link:hover {
    background: #fff1f1;
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
    background: #ffffff;
    border-right: 1px solid #eaeaea;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    padding: 16px 0;
    z-index: 99;
    transition: width .22s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}

.sidebar:hover { width: 230px; }

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 10px 0;
    color: #6b7280;
    text-decoration: none;
    white-space: nowrap;
    font-size: 13.5px;
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
    background: #f5f5f5;
    color: #000;
    border-left-color: #000;
    font-weight: 700;
}

.sidebar a .side-icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.sidebar a .side-icon img {
    filter: brightness(0) saturate(100%);
    opacity: .55;
    transition: opacity .15s;
}

.sidebar a:hover .side-icon img,
.sidebar a.active .side-icon img {
    opacity: 1;
}

.sidebar a span.side-label {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: opacity .18s, width .22s;
}

.sidebar:hover a span.side-label {
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

body.layout-locked .main {
    height: calc(100vh - var(--topbar-h));
    min-height: 0;
    overflow: hidden;
}

.sidebar:hover ~ .main { margin-left: 230px; }

/* ══════════════════════════════════
   CARD
══════════════════════════════════ */
.card {
    background: #fff;
    border: 1px solid #eaeaea;
    border-radius: 20px;
    padding: 28px 32px 36px;
    position: relative;
}

/* ── Painel de perfil ── */
.profile-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #eaeaea;
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.profile-shell.visible { display: block; }

.profile-header {
    background: #000;
    border-bottom: 1px solid #000;
    min-height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px 0 18px;
    color: #fff;
}

.profile-title {
    font-size: clamp(1.2rem, 2vw, 1.8rem);
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1;
}

.profile-panel {
    background: #fafafa;
    padding: 24px 22px 18px;
}

.profile-content {
    display: grid;
    grid-template-columns: 1.5fr 0.9fr;
    gap: 18px 28px;
    align-items: center;
}

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 18px;
    min-width: 0;
}

.profile-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.profile-field label {
    font-size: 12px;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.profile-field .profile-input {
    width: 100%;
    min-height: 56px;
    padding: 12px 16px;
    border-radius: 16px;
    border: 1px solid #dedede;
    background: #fff;
    color: #1f2b3d;
    font-weight: 600;
    font-size: 15px;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.profile-field .profile-input:focus {
    outline: none;
    border-color: #000;
    box-shadow: 0 0 0 4px rgba(0,0,0,0.06);
}

.profile-avatar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 220px;
    background: #fafafa;
    border: 3px solid #000;
    border-radius: 28px;
    padding: 18px 10px;
}

.profile-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 4px solid #000;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
    font-size: 56px;
    font-weight: 800;
    margin-bottom: 12px;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-avatar-button {
    width: 100%;
    max-width: 220px;
    border: none;
    border-radius: 18px;
    background: #000;
    color: #fff;
    padding: 14px 18px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.profile-adjust-tools {
    width: 100%;
    max-width: 220px;
    margin-top: 10px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
}

.profile-adjust-tools label {
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.profile-adjust-tools input[type="range"] {
    width: 100%;
    accent-color: #000;
}

.profile-save-button {
    border: none;
    border-radius: 16px;
    background: #000;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    padding: 14px 22px;
    cursor: pointer;
    transition: opacity .15s ease;
}

.profile-save-button:hover { opacity: .85; }

.profile-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
}

@media (max-width: 820px) {
    .profile-content { grid-template-columns: 1fr; }
    .profile-grid { grid-template-columns: 1fr; }
}

/* ── Painel de notificações ── */
.notifications-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #eaeaea;
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.notifications-shell.visible { display: block; }

.notifications-card { background: #fafafa; }

.notifications-tabs {
    display: flex;
    gap: 0;
    padding: 0;
    border-bottom: 1px solid #000;
    background: #000;
}

.notification-tab {
    border: none;
    border-left: 3px solid transparent;
    background: transparent;
    padding: 14px 22px;
    font-size: 14px;
    font-weight: 600;
    color: rgba(255,255,255,.7);
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s, padding-left .15s;
}

.notification-tab:hover,
.notification-tab.active {
    background: rgba(255,255,255,.12);
    color: #fff;
    border-left-color: #fff;
    padding-left: 26px;
}

.notifications-list { background: #fafafa; padding: 0 0 8px; }

.notification-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid #eee;
    background: transparent;
}

.notification-row:last-child { border-bottom: none; }

.notification-row.unread { background: #fff; }

.notification-row.read {
    background: #f0f0f0;
    border-left: 3px solid #000;
}

.notification-label { font-size: 16px; font-weight: 500; color: #1f2a37; }

.notification-row.read .notification-label { color: #7b8596; font-weight: 400; }

.notification-check {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #000;
    background: #fff;
    color: #000;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.notification-row.read .notification-check { background: #000; border-color: #000; color: #fff; }
.notification-row.unread .notification-check { background: #fff; border-color: #000; color: #000; }

/* ══════════════════════════════════
   ECRÃS DE GESTÃO (vazios por agora)
══════════════════════════════════ */
.screen-shell {
    background: #fff;
    border: 1px solid #eaeaea;
    border-radius: 20px;
    padding: 28px 32px 36px;
    display: none;
}
.screen-shell.visible { display: block; }

.trainer-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.trainer-page-title {
    font-size: 22px;
    font-weight: 800;
    color: #000;
    margin-bottom: 5px;
}

.trainer-page-subtitle { font-size: 13px; color: #6b7280; }

.btn-create {
    border: none;
    background: #000;
    color: #fff;
    padding: 11px 18px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s, transform .15s;
}

.btn-create:hover { opacity: .85; transform: translateY(-1px); }

.empty-state {
    padding: 70px 20px;
    text-align: center;
    color: #9aa0ae;
    font-size: 14px;
    border: 1.5px dashed #e2e2e2;
    border-radius: 18px;
}

.empty-state svg { width: 48px; height: 48px; margin-bottom: 14px; opacity: .35; }

.empty-state strong { display: block; color: #444; font-size: 15px; margin-bottom: 6px; }

/* ── Alerts ── */
.alert {
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.alert-close {
    border: none;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
    padding: 0 4px;
}

.alert-error { background: #fff1f1; color: #b00020; border: 1px solid #ffd0d0; }
.alert-success { background: #f2f2f2; color: #000; border: 1px solid #ddd; }

@media (max-width: 760px) {
    .messages-shell.visible { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ══ TOP BAR ══ -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-club-logo">
            <span class="topbar-club-logo--placeholder">SYS</span>
        </div>

        <div class="topbar-club-text">
            <span class="topbar-name">Kroos</span>
            <span class="topbar-sigla">Admin de Sistema</span>
        </div>
    </div>

    <div class="topbar-right">
        <img src="assets/kroos-logo.png" class="topbar-logo" alt="Kroos">

        <div class="topbar-user-menu-wrap">
            <button class="topbar-menu" type="button" aria-label="Menu" onclick="toggleUserMenu(event)">
                <span></span><span></span><span></span>
            </button>

            <div class="user-dropdown" id="userDropdown">
                <a href="#" onclick="event.preventDefault(); showProfileScreen(); toggleUserMenu(event);">
                    <span>Perfil</span>
                </a>

                <a href="#" onclick="event.preventDefault(); showNotificationsScreen(); toggleUserMenu(event);">
                    <span>Notificações</span>
                </a>

                <a href="logout.php" class="logout-link">
                    <span>Terminar sessão</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar" id="sidebar">
    <?php foreach ($menusGestao as $viewKey => $menu): ?>
    <a href="#" data-view="<?= $viewKey ?>" class="<?= $viewMode === $viewKey ? 'active' : '' ?>" onclick="event.preventDefault(); showScreen('<?= $viewKey ?>');">
        <span class="side-icon">
            <img src="<?= htmlspecialchars($menu['icon']) ?>" alt="" style="width:20px;height:20px;object-fit:contain;" onerror="this.style.display='none';">
        </span>
        <span class="side-label"><?= htmlspecialchars($menu['label']) ?></span>
    </a>
    <?php endforeach; ?>
    <a href="#" data-view="home" class="<?= $viewMode === 'home' ? 'active' : '' ?>" onclick="event.preventDefault(); showScreen('home');">
        <span class="side-icon">
            <img src="assets/home.png" alt="" style="width:20px;height:20px;object-fit:contain;" onerror="this.style.display='none';">
        </span>
        <span class="side-label">Página Principal</span>
    </a>
</div>

<!-- ══ MAIN ══ -->
<div class="main">
    <div class="card" id="dashboardCard">

        <!-- ══ PERFIL ══ -->
        <div class="profile-shell" id="profileScreen" aria-label="Painel de perfil">
            <div class="profile-header">
                <div class="profile-title">Perfil</div>
            </div>

            <div class="profile-panel">
                <form id="profileForm" method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="editar_perfil">
                    <div class="profile-content">
                        <div class="profile-grid">
                            <div class="profile-field">
                                <label>Nome Utilizador</label>
                                <input class="profile-input" type="text" value="<?= htmlspecialchars($perfilUtilizador['nome_utilizador'] ?? '') ?>" name="nome_utilizador">
                            </div>

                            <div class="profile-field">
                                <label>Email</label>
                                <input class="profile-input" type="email" value="<?= htmlspecialchars($perfilUtilizador['email_utilizador'] ?? '') ?>" name="email">
                            </div>

                            <div class="profile-field">
                                <label>Primeiro Nome</label>
                                <input class="profile-input" type="text" value="<?= htmlspecialchars($perfilUtilizador['primeiro_nome'] ?? '') ?>" name="primeiro_nome">
                            </div>

                            <div class="profile-field">
                                <label>Nº de Telemóvel</label>
                                <input class="profile-input" type="tel" value="<?= htmlspecialchars($perfilUtilizador['telefone_utilizador'] ?? '') ?>" name="telemovel">
                            </div>

                            <div class="profile-field">
                                <label>Último Nome</label>
                                <input class="profile-input" type="text" value="<?= htmlspecialchars($perfilUtilizador['último_nome'] ?? '') ?>" name="ultimo_nome">
                            </div>

                            <div class="profile-field">
                                <label>Data de Nascimento</label>
                                <input class="profile-input" type="date" value="<?= htmlspecialchars($perfilUtilizador['data_nascimento'] ?? '') ?>" name="data_nascimento">
                            </div>
                        </div>

                        <div class="profile-avatar-wrap">
                            <div class="profile-avatar">
                                <?php if ($fotoPerfilUtilizador): ?>
                                    <img id="profileAvatarPreview" src="<?= $fotoPerfilUtilizador ?>" alt="Foto de perfil">
                                <?php else: ?>
                                    <span id="profileAvatarInitial"><?= htmlspecialchars(strtoupper(substr($perfilUtilizador['nome_utilizador'] ?? 'U', 0, 1))) ?></span>
                                    <img id="profileAvatarPreview" src="" alt="Foto de perfil" style="display:none;">
                                <?php endif; ?>
                            </div>
                            <input id="fotoPerfilInput" type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <input id="fotoPerfilAjustadaInput" type="hidden" name="foto_perfil_ajustada" value="">
                            <button class="profile-avatar-button" id="btnEditarFotoPerfil" type="button">Editar Foto de Perfil</button>
                            <div class="profile-adjust-tools" id="profileAdjustTools" style="display:none;">
                                <label for="fotoPerfilZoom">Zoom</label>
                                <input id="fotoPerfilZoom" type="range" min="1" max="3" step="0.01" value="1">

                                <label for="fotoPerfilPosX">Horizontal</label>
                                <input id="fotoPerfilPosX" type="range" min="0" max="100" step="1" value="50">

                                <label for="fotoPerfilPosY">Vertical</label>
                                <input id="fotoPerfilPosY" type="range" min="0" max="100" step="1" value="50">
                            </div>
                            <small id="fotoPerfilHint" style="margin-top:8px; color:#6b7280; font-size:12px; text-align:center;">JPG, PNG ou WEBP (máx. 2MB)</small>
                            <small id="fotoPerfilErro" style="margin-top:6px; color:#b42318; font-size:12px; text-align:center; display:none;"></small>
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <button class="profile-save-button" type="submit" id="submitProfileBtn">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ NOTIFICAÇÕES ══ -->
        <div class="notifications-shell" id="notificationsScreen" aria-label="Painel de notificações">
            <div class="notifications-card">
                <div class="notifications-tabs">
                    <button class="notification-tab active" type="button">Geral</button>
                    <button class="notification-tab" type="button">Lidas</button>
                    <button class="notification-tab" type="button">Por ler</button>
                </div>

                <div class="notifications-list" id="notificationsList">
                    <?php if (empty($notificacoesUtilizador)): ?>
                        <div class="notification-row read">
                            <span class="notification-label">Sem notificações</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notificacoesUtilizador as $notificacao): ?>
                            <?php $estadoNotificacao = $notificacao['estado'] ?? 'Nao Lida'; ?>
                            <?php $naoLida = ($estadoNotificacao === 'Nao Lida'); ?>
                            <div class="notification-row <?= $naoLida ? 'unread' : 'read' ?>"
                                 data-id="<?= (int)($notificacao['id_notificacao'] ?? 0) ?>"
                                 data-state="<?= htmlspecialchars($estadoNotificacao) ?>"
                                 tabindex="0"
                                 role="button"
                                 aria-label="<?= htmlspecialchars($notificacao['titulo'] ?? 'Notificação') ?>">
                                <div class="notification-content">
                                    <span class="notification-label"><?= htmlspecialchars($notificacao['titulo'] ?? 'Notificação') ?></span>
                                    <small class="notification-message"><?= htmlspecialchars($notificacao['mensagem'] ?? '') ?></small>
                                </div>
                                <span class="notification-check">✓</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-error" role="alert">
                <span><?= htmlspecialchars($erro) ?></span>
                <button class="alert-close" type="button" aria-label="Fechar" onclick="closeAlert(this)">×</button>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert alert-success" role="status">
                <span><?= htmlspecialchars($sucesso) ?></span>
                <button class="alert-close" type="button" aria-label="Fechar" onclick="closeAlert(this)">×</button>
            </div>
        <?php endif; ?>

        <!-- ══ PÁGINA PRINCIPAL ══ -->
        <div class="screen-shell" id="screen-home">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Página Principal</h2>
                    <p class="trainer-page-subtitle">Painel de administração de sistema do Kroos.</p>
                </div>
            </div>
            <div class="empty-state">
                <strong>Bem-vindo, <?= htmlspecialchars($perfilUtilizador['primeiro_nome'] ?? 'Admin') ?>.</strong>
                Seleciona um menu de gestão na barra lateral.
            </div>
        </div>

        <!-- ══ ECRÃS DE GESTÃO (vazios) ══ -->
        <?php foreach ($menusGestao as $viewKey => $menu): ?>
        <div class="screen-shell" id="screen-<?= $viewKey ?>">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title"><?= htmlspecialchars($menu['label']) ?></h2>
                    <p class="trainer-page-subtitle">Ecrã em construção.</p>
                </div>
                <button class="btn-create" type="button" disabled style="opacity:.4;cursor:not-allowed;">+ Adicionar</button>
            </div>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4M12 16h.01"/>
                </svg>
                <strong>Sem funcionalidades disponíveis ainda</strong>
                Este menu vai conter a <?= htmlspecialchars(mb_strtolower($menu['label'])) ?>.
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<script>
/* Menu superior direito */
function toggleUserMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('userDropdown');
    if (menu) menu.classList.toggle('active');
}

document.addEventListener('click', function () {
    const menu = document.getElementById('userDropdown');
    if (menu) menu.classList.remove('active');
});

const userDropdown = document.getElementById('userDropdown');
if (userDropdown) {
    userDropdown.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}

function closeAlert(buttonEl) {
    if (!buttonEl) return;
    const alertEl = buttonEl.closest('.alert');
    if (alertEl) {
        alertEl.dataset.dismissed = '1';
        alertEl.style.display = 'none';
    }
}

/* Gestão de ecrãs */
const TODOS_OS_ECRAS = ['profileScreen', 'notificationsScreen', 'screen-home', 'screen-utilizadores', 'screen-competicoes', 'screen-jogadores', 'screen-clubes', 'screen-notificacoes_gestao', 'screen-definicoes'];

function hideAllScreens() {
    TODOS_OS_ECRAS.forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.display = 'none'; el.classList.remove('visible'); }
    });
}

function setActiveSidebar(view) {
    document.querySelectorAll('#sidebar a[data-view]').forEach(a => {
        a.classList.toggle('active', a.dataset.view === view);
    });
}

function setLayoutLock(locked) {
    document.body.classList.toggle('layout-locked', locked);
}

function showScreen(view) {
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar(view);

    const id = view === 'home' ? 'screen-home' : 'screen-' + view;
    const el = document.getElementById(id);
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }

    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.history.replaceState({}, '', url);
}

function showProfileScreen() {
    hideAllScreens();
    setLayoutLock(false);
    const el = document.getElementById('profileScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showNotificationsScreen() {
    hideAllScreens();
    setLayoutLock(false);
    const el = document.getElementById('notificationsScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

/* Perfil — guardar via fetch */
function saveProfileChanges() {
    const form = document.getElementById('profileForm');
    if (!form) return;

    const submitBtn = document.getElementById('submitProfileBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'A guardar...';
    }

    const formData = new FormData(form);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) throw new Error('Erro ao guardar perfil');
            if (submitBtn) submitBtn.textContent = 'Guardado';
            setTimeout(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Salvar alterações';
                }
            }, 1200);
        })
        .catch(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Tentar novamente';
            }
        });
}

const profileForm = document.getElementById('profileForm');
if (profileForm) {
    profileForm.addEventListener('submit', function (event) {
        event.preventDefault();
        exportAdjustedProfileImage();
        saveProfileChanges();
    });
}

/* Notificações — filtros e leitura */
function filterNotifications(filter = 'all') {
    document.querySelectorAll('.notification-row').forEach(row => {
        if (!row) return;
        const state = row.dataset.state || 'Nao Lida';
        const shouldShow = filter === 'all' ||
            (filter === 'read' && state === 'Lida') ||
            (filter === 'unread' && state === 'Nao Lida');
        row.style.display = shouldShow ? '' : 'none';
    });
}

function applyNotificationState(row, state) {
    if (!row) return;
    row.dataset.state = state;
    row.classList.remove('read', 'unread');
    row.classList.add(state === 'Lida' ? 'read' : 'unread');
}

function toggleNotificationState(idNotificacao, row) {
    if (!idNotificacao || !row) return;
    const currentState = row.dataset.state || 'Nao Lida';
    const nextState = currentState === 'Lida' ? 'Nao Lida' : 'Lida';

    const formData = new URLSearchParams();
    formData.append('acao', 'alterar_estado_notificacao');
    formData.append('id_notificacao', String(idNotificacao));
    formData.append('estado', nextState);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: formData.toString()
    }).then(() => applyNotificationState(row, nextState))
      .catch(() => applyNotificationState(row, nextState));
}

const notificationTabs = document.querySelectorAll('.notification-tab');
notificationTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        notificationTabs.forEach(btn => btn.classList.remove('active'));
        tab.classList.add('active');
        const text = tab.textContent.trim();
        if (text === 'Lidas') filterNotifications('read');
        else if (text === 'Por ler') filterNotifications('unread');
        else filterNotifications('all');
    });
});

document.querySelectorAll('.notification-row').forEach(row => {
    row.addEventListener('click', () => {
        const id = row.dataset.id;
        if (id) toggleNotificationState(id, row);
    });
    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const id = row.dataset.id;
            if (id) toggleNotificationState(id, row);
        }
    });
});

/* Foto de perfil — upload + ajuste (zoom / posição) */
const btnEditarFotoPerfil = document.getElementById('btnEditarFotoPerfil');
const fotoPerfilInput = document.getElementById('fotoPerfilInput');
const fotoPerfilErro = document.getElementById('fotoPerfilErro');
const fotoPerfilAjustadaInput = document.getElementById('fotoPerfilAjustadaInput');
const fotoPerfilZoom = document.getElementById('fotoPerfilZoom');
const fotoPerfilPosX = document.getElementById('fotoPerfilPosX');
const fotoPerfilPosY = document.getElementById('fotoPerfilPosY');
const profileAdjustTools = document.getElementById('profileAdjustTools');

let imagemOriginalParaAjuste = null;

function setFotoPerfilErro(msg) {
    if (!fotoPerfilErro) return;
    if (!msg) { fotoPerfilErro.style.display = 'none'; fotoPerfilErro.textContent = ''; return; }
    fotoPerfilErro.textContent = msg;
    fotoPerfilErro.style.display = 'block';
}

function applyPreviewTransform() {
    const preview = document.getElementById('profileAvatarPreview');
    if (!preview || !imagemOriginalParaAjuste) return;
    const zoom = Number(fotoPerfilZoom ? fotoPerfilZoom.value : 1);
    const x = Number(fotoPerfilPosX ? fotoPerfilPosX.value : 50);
    const y = Number(fotoPerfilPosY ? fotoPerfilPosY.value : 50);
    preview.style.objectFit = 'cover';
    preview.style.objectPosition = x + '% ' + y + '%';
    preview.style.transform = 'scale(' + zoom + ')';
    preview.style.transformOrigin = 'center center';
}

function exportAdjustedProfileImage() {
    if (!imagemOriginalParaAjuste || !fotoPerfilAjustadaInput) {
        if (fotoPerfilAjustadaInput) fotoPerfilAjustadaInput.value = '';
        return;
    }
    const zoom = Number(fotoPerfilZoom ? fotoPerfilZoom.value : 1);
    const x = Number(fotoPerfilPosX ? fotoPerfilPosX.value : 50) / 100;
    const y = Number(fotoPerfilPosY ? fotoPerfilPosY.value : 50) / 100;

    const canvasSize = 600;
    const canvas = document.createElement('canvas');
    canvas.width = canvasSize;
    canvas.height = canvasSize;
    const ctx = canvas.getContext('2d');
    if (!ctx) { fotoPerfilAjustadaInput.value = ''; return; }

    const iw = imagemOriginalParaAjuste.naturalWidth || imagemOriginalParaAjuste.width;
    const ih = imagemOriginalParaAjuste.naturalHeight || imagemOriginalParaAjuste.height;
    if (!iw || !ih) { fotoPerfilAjustadaInput.value = ''; return; }

    const baseScale = Math.max(canvasSize / iw, canvasSize / ih);
    const finalScale = baseScale * zoom;
    const drawW = iw * finalScale;
    const drawH = ih * finalScale;
    const extraX = Math.max(0, drawW - canvasSize);
    const extraY = Math.max(0, drawH - canvasSize);
    const offsetX = -extraX * x;
    const offsetY = -extraY * y;

    ctx.clearRect(0, 0, canvasSize, canvasSize);
    ctx.drawImage(imagemOriginalParaAjuste, offsetX, offsetY, drawW, drawH);
    fotoPerfilAjustadaInput.value = canvas.toDataURL('image/png', 0.92);
}

if (btnEditarFotoPerfil && fotoPerfilInput) {
    btnEditarFotoPerfil.addEventListener('click', () => fotoPerfilInput.click());

    fotoPerfilInput.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        setFotoPerfilErro('');
        const file = this.files[0];
        const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!tiposPermitidos.includes(file.type)) {
            this.value = '';
            setFotoPerfilErro('Formato inválido. Usa JPG, PNG ou WEBP.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            this.value = '';
            setFotoPerfilErro('Imagem demasiado grande. Máximo: 2MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const preview = document.getElementById('profileAvatarPreview');
            const initial = document.getElementById('profileAvatarInitial');
            const tempImage = new Image();
            tempImage.onload = () => {
                imagemOriginalParaAjuste = tempImage;
                if (profileAdjustTools) profileAdjustTools.style.display = 'grid';
                if (fotoPerfilZoom) fotoPerfilZoom.value = '1';
                if (fotoPerfilPosX) fotoPerfilPosX.value = '50';
                if (fotoPerfilPosY) fotoPerfilPosY.value = '50';
                applyPreviewTransform();
                exportAdjustedProfileImage();
            };
            tempImage.src = event.target.result;
            if (preview) { preview.src = event.target.result; preview.style.display = 'block'; }
            if (initial) initial.style.display = 'none';
        };
        reader.readAsDataURL(this.files[0]);
    });
}

[fotoPerfilZoom, fotoPerfilPosX, fotoPerfilPosY].forEach((inputEl) => {
    if (!inputEl) return;
    inputEl.addEventListener('input', () => {
        applyPreviewTransform();
        exportAdjustedProfileImage();
    });
});

/* Estado inicial conforme parâmetro ?view= */
document.addEventListener('DOMContentLoaded', function () {
    const initialView = <?= json_encode($viewMode) ?>;
    showScreen(initialView);
});
</script>

</body>
</html>