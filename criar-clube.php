<?php
session_start();
require_once __DIR__ . '/basedados.h';

$erro = '';

// Garante que existe sessão de validação activa (vem de criar-utilizador.php)
$id_validacao = $_SESSION['id_validacao'] ?? null;
if (!$id_validacao) {
    header('Location: criar-utilizador.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome          = trim($_POST['nome_clube']    ?? '');
    $sigla         = strtoupper(trim($_POST['sigla']  ?? ''));
    $cor           = strtoupper(trim($_POST['cor']    ?? ''));
    $data_fundacao = $_POST['data_fundacao']       ?? '';

    // Valida campos obrigatórios
    if ($nome === '' || $sigla === '' || $cor === '' || $data_fundacao === '') {
        $erro = 'Preenche todos os campos obrigatórios.';
    } elseif (empty($_FILES['logotipo']['tmp_name'])) {
        $erro = 'É obrigatório carregar um logótipo.';
    } else {

        // ── 1. Inserir clube ──────────────────────────────────────────────
        $codigo_clube = strtoupper(bin2hex(random_bytes(4)));
        $logotipo     = file_get_contents($_FILES['logotipo']['tmp_name']);

        $stmt = $conn->prepare("
            INSERT INTO clube
                (nome_clube, sigla, logotipo, cor, data_fundação, código_clube)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssssss', $nome, $sigla, $logotipo, $cor, $data_fundacao, $codigo_clube);

        if (!$stmt->execute()) {
            $erro = 'Erro ao criar clube: ' . $stmt->error;
        } else {

            $id_clube = $stmt->insert_id;

            // ── 2. Buscar dados do utilizador em validação ────────────────
            $stmtUser = $conn->prepare("
                SELECT nome_utilizador, foto_perfil, email_utilizador,
                       telefone_utilizador, primeiro_nome, último_nome,
                       data_nascimento, password
                FROM validação_utilizador
                WHERE id_validação = ?
                LIMIT 1
            ");
            $stmtUser->bind_param('i', $id_validacao);
            $stmtUser->execute();
            $user = $stmtUser->get_result()->fetch_assoc();

            if (!$user) {
                $erro = 'Sessão expirada. Por favor regista-te novamente.';
            } else {

                // ── 3. Criar utilizador admin_clube ───────────────────────
                $stmtInsert = $conn->prepare("
                    INSERT INTO utilizador
                        (nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador,
                         primeiro_nome, último_nome, data_nascimento, password,
                         tipo_utilizador, id_clube)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin_clube', ?)
                ");
                $stmtInsert->bind_param(
                    'ssssssssi',
                    $user['nome_utilizador'],
                    $user['foto_perfil'],
                    $user['email_utilizador'],
                    $user['telefone_utilizador'],
                    $user['primeiro_nome'],
                    $user['último_nome'],
                    $user['data_nascimento'],
                    $user['password'],
                    $id_clube
                );

                if (!$stmtInsert->execute()) {
                    $erro = 'Erro ao criar utilizador: ' . $stmtInsert->error;
                } else {

                    // ── 4. Actualizar sessão ──────────────────────────────
                    $_SESSION['id_utilizador']  = $stmtInsert->insert_id;
                    $_SESSION['tipo_utilizador'] = 'admin_clube';
                    $_SESSION['id_clube']        = $id_clube;

                    // ── 5. Limpar registo de validação ────────────────────
                    $stmtDel = $conn->prepare("
                        DELETE FROM validação_utilizador WHERE id_validação = ?
                    ");
                    $stmtDel->bind_param('i', $id_validacao);
                    $stmtDel->execute();
                    unset($_SESSION['id_validacao']);

                    header('Location: index-admin.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | Criar Clube</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: #fff;
    animation: pageIn 0.4s ease-out;
}

@keyframes pageIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.header {
    position: fixed;
    top: 32px;
    left: 40px;
}

.header img {
    height: 80px;
}

.main {
    min-height: 100vh;
    padding: 120px 80px 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.card {
    width: 100%;
    max-width: 1200px;
    padding: 72px;
    border-radius: 32px;
    border: 1px solid #e3e3e3;
    box-shadow: 0 16px 32px rgba(0,0,0,.06), 0 60px 120px rgba(0,0,0,.1);
}

/* ── Stepper ── */
.stepper-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 40px;
}

.steps {
    display: flex;
    align-items: center;
    gap: 18px;
}

.step {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #000;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.line {
    width: 64px;
    height: 3px;
    background: #000;
}

/* ── Grelha do formulário ── */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    margin-top: 40px;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 24px;
}

.input-group label {
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

input[type="text"],
input[type="date"] {
    padding: 16px 22px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f0f0f0;
    font-size: 15px;
    outline: none;
    width: 100%;
}

/* ── Logótipo ── */
.logo-label {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    margin-bottom: 12px;
    display: block;
}

.logo-box {
    width: 220px;
    height: 220px;
    background: #e6e6e6;
    border-radius: 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-direction: column;
    gap: 6px;
    position: relative;
    overflow: hidden;
    transition: background 0.2s;
}

.logo-box:hover {
    background: #d8d8d8;
}

.gallery-icon {
    width: 64px;
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
}

#logoText {
    position: relative;
    z-index: 1;
    font-size: 14px;
    color: #555;
    text-align: center;
}

#logoPreview {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: none;
    z-index: 2;
}

/* ── Color picker ── */
.color-section label {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    display: block;
    margin-bottom: 12px;
}

.color-picker {
    display: flex;
    align-items: center;
    gap: 12px;
}

.color-picker input[type="color"] {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    border: 1px solid #999;
    padding: 0;
    cursor: pointer;
    background: none;
}

.color-picker input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
.color-picker input[type="color"]::-webkit-color-swatch         { border: none; }

#colorHex {
    width: 130px;
    padding: 14px 18px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f0f0f0;
    font-family: monospace;
    font-size: 14px;
}

/* ── Erro ── */
.erro-msg {
    color: #cc0000;
    font-size: 14px;
    margin-bottom: 20px;
    padding: 12px 20px;
    background: #fff0f0;
    border-radius: 12px;
    border: 1px solid #f5c0c0;
}

/* ── Botão ── */
.btn-container {
    margin-top: 60px;
    display: flex;
    justify-content: flex-end;
}

.btn {
    width: 260px;
    padding: 18px;
    border-radius: 999px;
    border: none;
    background: #000;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}

.btn:hover { opacity: 0.8; }

@media (max-width: 900px) {
    .main       { padding: 120px 24px 40px; }
    .card       { padding: 96px 32px 40px;  }
    .form-grid  { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="header">
    <img src="assets/kroos-logo.png" alt="Kroos">
</div>

<div class="main">
    <div class="card">

        <div class="stepper-wrapper">
            <div class="steps">
                <div class="step">1</div>
                <div class="line"></div>
                <div class="step">2</div>
                <div class="line"></div>
                <div class="step">3</div>
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="erro-msg"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-grid">

                <!-- Coluna esquerda -->
                <div>
                    <div class="input-group">
                        <label>Nome do clube</label>
                        <input type="text" name="nome_clube" required
                               value="<?= htmlspecialchars($_POST['nome_clube'] ?? '') ?>">
                    </div>

                    <div class="input-group">
                        <label>Sigla / Acrónimo</label>
                        <input type="text" name="sigla" maxlength="5" required
                               value="<?= htmlspecialchars($_POST['sigla'] ?? '') ?>">
                    </div>

                    <div class="input-group">
                        <label>Data de fundação</label>
                        <input type="date" name="data_fundacao" required
                               value="<?= htmlspecialchars($_POST['data_fundacao'] ?? '') ?>">
                    </div>
                </div>

                <!-- Coluna direita -->
                <div>
                    <span class="logo-label">Logótipo</span>
                    <div class="logo-box" id="logoBox">
                        <img src="assets/image-gallery.png" class="gallery-icon" id="galleryIcon" alt="">
                        <span id="logoText">Carregar<br>ficheiro</span>
                        <img id="logoPreview" alt="Pré-visualização do logótipo">
                    </div>

                    <!-- Input de ficheiro fora do logo-box, mas ainda no form -->
                    <input type="file" name="logotipo" id="logoInput"
                           accept="image/*" style="display:none" required>

                    <div class="color-section">
                        <label>Escolher cor principal</label>
                        <div class="color-picker">
                            <input type="color" id="colorInput" value="#000000">
                            <input type="text"  id="colorHex"   name="cor" value="#000000" maxlength="7">
                        </div>
                    </div>
                </div>

            </div><!-- /.form-grid -->

            <div class="btn-container">
                <button class="btn" type="submit">Continuar</button>
            </div>

        </form>

    </div><!-- /.card -->
</div><!-- /.main -->

<script>
// ── Preview do logótipo ──────────────────────────────────────────────────
const logoBox     = document.getElementById('logoBox');
const logoInput   = document.getElementById('logoInput');
const logoPreview = document.getElementById('logoPreview');
const galleryIcon = document.getElementById('galleryIcon');
const logoText    = document.getElementById('logoText');

// Abre o seletor de ficheiros ao clicar na caixa
logoBox.addEventListener('click', () => logoInput.click());

logoInput.addEventListener('change', () => {
    const file = logoInput.files[0];
    if (!file) return;

    // URL.createObjectURL é mais rápido e fiável que FileReader para preview
    const objectUrl = URL.createObjectURL(file);
    logoPreview.src = objectUrl;
    logoPreview.style.display = 'block';
    galleryIcon.style.display = 'none';
    logoText.style.display    = 'none';
});

// ── Sincronização do color picker ────────────────────────────────────────
const colorInput = document.getElementById('colorInput');
const colorHex   = document.getElementById('colorHex');

colorInput.addEventListener('input', () => {
    colorHex.value = colorInput.value.toUpperCase();
});

colorHex.addEventListener('input', () => {
    const val = colorHex.value.trim();
    if (/^#([0-9A-Fa-f]{6})$/.test(val)) {
        colorInput.value = val;
    }
});
</script>

</body>
</html>