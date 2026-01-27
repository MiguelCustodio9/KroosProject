<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* 🔐 Proteção da página */
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
$id_clube = $_SESSION['id_clube'];

/* 🏟️ Buscar APENAS o clube deste admin */
$stmt = $conn->prepare("
    SELECT nome_clube, sigla, cor, logotipo
    FROM clube
    WHERE id_clube = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_clube);
$stmt->execute();
$clube = $stmt->get_result()->fetch_assoc();

/* Segurança extra */
if (!$clube) {
    // sessão inválida ou clube apagado
    session_destroy();
    header('Location: login.php');
    exit;
}

/* 🎨 Dados reais do clube */
$nomeClube = $clube['nome_clube'];
$siglaClube = $clube['sigla'];
$corClube = $clube['cor']; // 🔥 NADA HARDCODED
$logoClube = $clube['logotipo']
    ? 'data:image/png;base64,' . base64_encode($clube['logotipo'])
    : null;
?>


<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | Admin Clube</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --club-color: <?= htmlspecialchars($corClube) ?>;
}

* {
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    margin: 0;
    background: #f5f6fa;
}

/* ===== TOP BAR ===== */
.topbar {
    height: 70px;
    background: var(--club-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    color: #fff;
}

.topbar img {
    height: 36px;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    top: 70px;
    left: 0;
    width: 72px;
    height: calc(100vh - 70px);
    background: var(--club-color);
    transition: width 0.25s;
    overflow: hidden;
}

.sidebar:hover {
    width: 220px;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    color: #fff;
    text-decoration: none;
}

.sidebar img {
    width: 26px;
}

/* ===== CONTENT ===== */
.main {
    margin-left: 72px;
    padding: 32px;
    transition: margin-left 0.25s;
}

.sidebar:hover ~ .main {
    margin-left: 220px;
}

/* ===== CARD ===== */
.card {
    background: #fff;
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,.1);
}

/* ===== TABS ===== */
.tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.tab {
    padding: 10px 18px;
    border-radius: 999px;
    background: #eee;
    cursor: pointer;
}

.tab.active {
    background: var(--club-color);
    color: #fff;
}

/* ===== INFO ===== */
.club-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.club-logo {
    width: 140px;
    height: 140px;
    border-radius: 20px;
    object-fit: contain;
}

/* EMPTY STATE */
.empty {
    padding: 40px;
    text-align: center;
    color: #888;
}
</style>
</head>

<body>

<!-- TOP BAR -->
<div class="topbar">
    <strong><?= htmlspecialchars($nomeClube) ?></strong>
    <img src="assets/kroos-logo-branco.png" alt="Kroos">
</div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="#"><img src="assets/clube.png"> Clube</a>
    <a href="#"><img src="assets/escaloes.png"> Escalões</a>
    <a href="#"><img src="assets/eventos.png"> Eventos</a>
    <a href="#"><img src="assets/calendario.png"> Calendário</a>
    <a href="#"><img src="assets/mensagens.png"> Mensagens</a>
    <a href="#"><img src="assets/home.png"> Página Principal</a>
</div>

<!-- MAIN -->
<div class="main">
    <div class="card">

        <!-- TABS -->
        <div class="tabs">
            <div class="tab active">Info</div>
            <div class="tab">Escalões</div>
            <div class="tab">Treinadores</div>
        </div>

        <!-- INFO -->
        <div class="club-header">
            <div>
                <p><strong>Nome:</strong> <?= htmlspecialchars($nomeClube) ?></p>
                <?php if (!empty($clube['data_fundação'])): ?>
                    <p><strong>Fundação:</strong> <?= htmlspecialchars($clube['data_fundação']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($logoClube): ?>
                <img src="<?= $logoClube ?>" class="club-logo">
            <?php endif; ?>
        </div>

        <div class="empty">
            Seleciona uma aba para ver conteúdo.
        </div>

    </div>
</div>

</body>
</html>
